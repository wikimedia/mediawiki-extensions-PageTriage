<?php

namespace MediaWiki\Extension\PageTriage;

use Article;
use DatabaseUpdater;
use DeferredUpdates;
use EchoEvent;
use ExtensionRegistry;
use Html;
use LinksUpdate;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddDeletionTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddMaintenanceTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageMarkAsReviewedPresentationModel;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use MWTimestamp;
use ParserOutput;
use RecentChange;
use Title;
use User;
use Wikimedia\Rdbms\Database;
use WikiPage;

class Hooks {

	/**
	 * Mark a page as unreviewed after moving the page from non-main(article) namespace to
	 * main(article) namespace
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageMoveComplete
	 * @param LinkTarget $oldTitle old title object
	 * @param LinkTarget $newTitle new title object
	 * @param UserIdentity $user User doing the move
	 * @param int $oldid Page id of moved page
	 * @param int $newid Page id of created redirect, or 0 if suppressed
	 * @param string $reason Reason for the move
	 * @param RevisionRecord $revisionRecord Null revision created by the move
	 */
	public static function onPageMoveComplete(
		LinkTarget $oldTitle,
		LinkTarget $newTitle,
		UserIdentity $user,
		$oldid,
		$newid,
		$reason,
		RevisionRecord $revisionRecord
	) {
		// Delete cache for record if it's in pagetriage queue
		$articleMetadata = new ArticleMetadata( [ $oldid ] );
		$articleMetadata->flushMetadataFromCache();

		$oldTitle = Title::newFromLinkTarget( $oldTitle );
		$newTitle = Title::newFromLinkTarget( $newTitle );

		// Delete user status cache
		self::flushUserStatusCache( $oldTitle );
		self::flushUserStatusCache( $newTitle );

		$oldNamespace = $oldTitle->getNamespace();
		$newNamespace = $newTitle->getNamespace();
		// Do nothing further on if
		// 1. the page move is within the same namespace or
		// 2. the new page is not in either the main or draft namespaces
		$draftNsId = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageTriageDraftNamespaceId' );
		if (
			$oldNamespace === $newNamespace
			|| !in_array( $newNamespace, [ NS_MAIN, $draftNsId ], true )
		) {
			return;
		}

		// If not a new record to pagetriage queue, do nothing.
		if ( !self::addToPageTriageQueue( $oldid, $newTitle, $user ) ) {
			return;
		}
		// Item was newly added to PageTriage queue in master DB, compile metadata.
		$acp = ArticleCompileProcessor::newFromPageId( [ $oldid ] );
		if ( $acp ) {
			// Since this is a title move, the only component requiring DB_PRIMARY will be
			// BasicData.
			$acp->configComponentDb(
				ArticleCompileProcessor::getSafeComponentDbConfigForCompilation()
			);
			$acp->compileMetadata();
		}
	}

	/**
	 * Check if a page is created from a redirect page, then insert into it PageTriage Queue
	 * Note: Page will be automatically marked as triaged for users with autopatrol right
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RevisionFromEditComplete
	 *
	 * @param WikiPage $wikiPage the WikiPage edited
	 * @param RevisionRecord $rev the new revision
	 * @param int $baseID the revision ID this was based on, if any
	 * @param UserIdentity $user the editing user
	 */
	public static function onRevisionFromEditComplete( WikiPage $wikiPage, $rev, $baseID, $user ) {
		if ( !in_array( $wikiPage->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		if ( $rev && $rev->getParentId() ) {
			// Make sure $prev->getContent() is done post-send if possible
			DeferredUpdates::addCallableUpdate( function () use ( $rev, $wikiPage, $user ) {
				$prevRevRecord = MediaWikiServices::getInstance()
					->getRevisionLookup()
					->getRevisionById( $rev->getParentId() );
				if ( $prevRevRecord &&
					!$wikiPage->isRedirect() &&
					$prevRevRecord->getContent( SlotRecord::MAIN )->isRedirect()
				) {
					// Add item to queue, if it's not already there.
					self::addToPageTriageQueue(
						$wikiPage->getId(),
						$wikiPage->getTitle(),
						$user
					);
				}
			} );
		}
	}

	/**
	 * Page saved, flush cache
	 *
	 * If new article is created, insert it into PageTriage Queue and compile metadata.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @param WikiPage $wikiPage WikiPage created
	 * @param UserIdentity $user User creating the article
	 * @param string $summary Edit summary/comment
	 * @param int $flags Flags passed to Article::doEdit()
	 * @param RevisionRecord $revisionRecord New Revision of the article
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $user,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord
	) {
		$title = $wikiPage->getTitle();

		self::flushUserStatusCache( $title );

		if ( !( $flags & EDIT_NEW ) ) {
			// Don't add to queue if its not a new page
			return;
		}

		// Don't add to queue if not in a namespace of interest.
		if ( !in_array( $title->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Add item to queue. Metadata compilation will get triggered in the LinksUpdate hook.
		self::addToPageTriageQueue(
			$wikiPage->getId(),
			$title,
			$user
		);
	}

	/**
	 * Update metadata when link information is updated.
	 *
	 * This is also run after every page save.
	 *
	 * Note that this hook can be triggered by a GET request (rollback action, until T88044 is
	 * sorted out), in which case master DB connections and writes on GET request can occur.
	 *
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdateComplete( LinksUpdate $linksUpdate ) {
		if ( !in_array( $linksUpdate->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( static function () use ( $linksUpdate ) {
			// Validate the page ID from DB_PRIMARY, compile metadata from DB_PRIMARY and return.
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $linksUpdate->getTitle()->getArticleID() ],
				false,
				DB_PRIMARY
			);
			if ( $acp ) {
				$acp->registerLinksUpdate( $linksUpdate );
				$acp->compileMetadata();
			}
		} );
	}

	/**
	 * Remove the metadata we added when the article is deleted.
	 *
	 * 'ArticleDeleteComplete': after an article is deleted
	 * @param WikiPage $article the WikiPage that was deleted
	 * @param User $user the user that deleted the article
	 * @param string $reason the reason the article was deleted
	 * @param int $id id of the article that was deleted
	 */
	public static function onArticleDeleteComplete( $article, $user, $reason, $id ) {
		self::flushUserStatusCache( $article->getTitle() );

		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Delete everything
		$pageTriage = new PageTriage( $id );
		$pageTriage->deleteFromPageTriage();
	}

	/**
	 * Add page to page triage queue, check for autopatrol right if reviewed is not set
	 *
	 * This method should only be called from this class and its closures
	 *
	 * @param int $pageId
	 * @param Title $title
	 * @param UserIdentity|null $userIdentity
	 * @param string|null $reviewed numeric string See PageTriage::getValidReviewedStatus()
	 * @return bool
	 */
	public static function addToPageTriageQueue( $pageId, $title, $userIdentity = null, $reviewed = null ) {
		global $wgUseRCPatrol, $wgUseNPPatrol;

		// Get draft information.
		$draftNsId = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageTriageDraftNamespaceId' );
		$isDraft = $draftNsId !== false && $title->inNamespace( $draftNsId );

		// Draft redirects are not patrolled or reviewed.
		if ( $isDraft && $title->isRedirect() ) {
			return false;
		}

		$pageTriage = new PageTriage( $pageId );

		// action taken by system
		if ( $userIdentity === null ) {
			if ( $reviewed === null ) {
				$reviewed = '0';
			}
			return $pageTriage->addToPageTriageQueue( $reviewed );
		// action taken by a user
		} else {
			// set reviewed if it's not set yet
			if ( $reviewed === null ) {
				$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
				$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
					->getPermissionErrors( 'autopatrol', $user, $title );
				$isAutopatrolled = ( $wgUseRCPatrol || $wgUseNPPatrol ) &&
					!count( $permissionErrors );
				if ( $isAutopatrolled && !$isDraft ) {
					// Set as reviewed if the user has the autopatrol right
					// and they're not creating a Draft.
					$reviewed = 3;
				} else {
					// If they have no autopatrol right and are not making an explicit review,
					// set to unreviewed (as the system would, in this situation).
					return $pageTriage->addToPageTriageQueue( '0' );
				}
			}
			return $pageTriage->addToPageTriageQueue( $reviewed, $userIdentity );
		}
	}

	/**
	 * Flush user page/user talk page existance status, this function should
	 * be called when a page gets created/deleted/moved/restored
	 * @param Title $title
	 */
	private static function flushUserStatusCache( $title ) {
		if ( in_array( $title->getNamespace(), [ NS_USER, NS_USER_TALK ] ) ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cache->delete( PageTriageUtil::userStatusKey( $title->getText() ) );
		}
	}

	/**
	 * Determines whether to set noindex for the article specified
	 *
	 * Returns true if all of the following are true:
	 *   1. The page includes __NOINDEX__
	 *   2. The page was at some point in the triage queue
	 *   3. The page is younger than the maximum age for "new pages"
	 * or all of the following are true:
	 *   1. $wgPageTriageNoIndexUnreviewedNewArticles is true
	 *   2. The page is in the triage queue and has not been triaged
	 *   3. The page is younger than the maximum age for "new pages"
	 * Note that we always check the age of the page last since that is
	 * potentially the most expensive check (if the data isn't cached).
	 *
	 * @param Article $article
	 * @return bool
	 */
	private static function shouldShowNoIndex( Article $article ) {
		global $wgPageTriageNoIndexUnreviewedNewArticles;

		// If the __NOINDEX__ magic word is on a new article, then allow
		// it to work, regardless of namespace robot policies.
		if ( $article->mParserOutput instanceof ParserOutput
			&& $article->mParserOutput->getPageProperty( 'noindex' ) !== null
		) {
			// Short circuit since we know it will fail the next set
			// of tests as well.
			return self::isPageNew( $article->getPage() );
		}

		return $wgPageTriageNoIndexUnreviewedNewArticles
			&& PageTriageUtil::doesPageNeedTriage( $article->getPage() )
			&& self::isPageNew( $article->getPage() );
	}

	/**
	 * Checks to see if an article is new, i.e. less than $wgPageTriageMaxAge
	 *
	 * Look in cache for the creation date, if not found, query the replica for the value
	 * of ptrp_created.
	 *
	 * @param WikiPage $wikiPage WikiPage to check
	 * @return bool
	 */
	private static function isPageNew( WikiPage $wikiPage ) {
		global $wgPageTriageMaxAge;

		$pageId = $wikiPage->getId();
		if ( !$pageId ) {
			return false;
		}

		// Check cache for creation date
		$fname = __METHOD__;
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$pageCreationDateTime = $cache->getWithSetCallback(
			$cache->makeKey( 'pagetriage-page-created', $pageId ),
			$cache::TTL_DAY,
			static function ( $oldValue, &$ttl, array &$setOpts ) use ( $pageId, $fname ) {
				// The ptrp_created field is equivalent to creation_date
				// property set during article metadata compilation.
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				return $dbr->selectField(
					'pagetriage_page',
					'ptrp_created',
					[ 'ptrp_page_id' => $pageId ],
					$fname
				);
			},
			[ 'version' => PageTriage::CACHE_VERSION ]
		);

		// If still not found, return false.
		if ( !$pageCreationDateTime ) {
			return false;
		}

		// Get the age of the article in days
		$timestamp = new MWTimestamp( $pageCreationDateTime );
		$dateInterval = $timestamp->diff( new MWTimestamp() );
		$articleDaysOld = $dateInterval->format( '%a' );
		if ( $articleDaysOld < $wgPageTriageMaxAge ) {
			// If it's younger than the maximum age, return true.
			return true;
		}

		return false;
	}

	/**
	 * Handler for hook ArticleViewFooter, this will determine whether to load
	 * curation toolbar or 'mark as reviewed'/'reviewed' text
	 *
	 * @param Article $article Article object to show link for.
	 * @param bool $patrolFooterShown whether the patrol footer is shown
	 */
	public static function onArticleViewFooter( Article $article, $patrolFooterShown ) {
		global $wgPageTriageEnableCurationToolbar;

		$wikiPage = $article->getPage();
		$title = $wikiPage->getTitle();
		$context = $article->getContext();
		$user = $context->getUser();
		$outputPage = $context->getOutput();
		$request = $context->getRequest();

		// Overwrite the noindex rule defined in Article::view(), this also affects main namespace
		if ( self::shouldShowNoIndex( $article ) ) {
			$outputPage->setRobotPolicy( 'noindex,nofollow' );
		}

		// Only logged in users can review
		if ( !$user->isRegistered() ) {
			return;
		}

		// Don't show anything for user with no patrol right
		$permManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permManager->quickUserCan( 'patrol', $user, $title ) ) {
			return;
		}

		// Only show in defined namespaces
		if ( !in_array( $title->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Don't do anything if it's coming from Special:NewPages
		if ( $request->getVal( 'patrolpage' ) ) {
			return;
		}

		// See if the page is in the PageTriage page queue
		// If it isn't, $needsReview will be null
		// Also, users without the autopatrol right can't review their own pages
		$needsReview = PageTriageUtil::doesPageNeedTriage( $wikiPage );
		$revStore = MediaWikiServices::getInstance()->getRevisionStore();
		if ( $needsReview !== null
			&& (
				!$user->equals( $revStore->getFirstRevision( $title )->getUser( RevisionRecord::RAW ) )
				|| $permManager->userHasRight( $user, 'autopatrol' )
			)
		) {
			if ( $wgPageTriageEnableCurationToolbar || $request->getVal( 'curationtoolbar' ) === 'true' ) {
				// Load the JavaScript for the curation toolbar
				$outputPage->addModules( 'ext.pageTriage.toolbarStartup' );
			} else {
				if ( $needsReview ) {
					// show 'Mark as reviewed' link
					$msg = $context->msg( 'pagetriage-markpatrolled' )->text();
					$msg = Html::element(
						'a',
						[ 'href' => '#', 'class' => 'mw-pagetriage-markpatrolled-link' ],
						$msg
					);
				} else {
					// show 'Reviewed' text
					$msg = $context->msg( 'pagetriage-reviewed' )->escaped();
				}
				$outputPage->addModules( [ 'ext.pageTriage.article' ] );
				$html = Html::rawElement( 'div', [ 'class' => 'mw-pagetriage-markpatrolled' ], $msg );
				$outputPage->addHTML( $html );
			}
		} elseif ( $needsReview === null && !$title->isMainPage() ) {
			// Page is potentially usable, but not in the queue, allow users to add it manually
			// Option is not shown if the article is the main page
			$outputPage->addModules( 'ext.PageTriage.enqueue' );
		}
	}

	/**
	 * Sync records from patrol queue to triage queue
	 *
	 * 'MarkPatrolledComplete': after an edit is marked patrolled
	 * $rcid: ID of the revision marked as patrolled
	 * $user: user (object) who marked the edit patrolled
	 * $wcOnlySysopsCanPatrol: config setting indicating whether the user
	 * must be a sysop to patrol the edit
	 * @param int $rcid
	 * @param User &$user
	 * @param bool $wcOnlySysopsCanPatrol
	 */
	public static function onMarkPatrolledComplete( $rcid, &$user, $wcOnlySysopsCanPatrol ) {
		$rc = RecentChange::newFromId( $rcid );

		if ( $rc ) {
			if ( !in_array( $rc->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
				return;
			}

			$pt = new PageTriage( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $pt->addToPageTriageQueue( '2', $user, true ) ) {
				// Compile metadata for new page triage record.
				$acp = ArticleCompileProcessor::newFromPageId( [ $rc->getAttribute( 'rc_cur_id' ) ] );
				if ( $acp ) {
					// Page was just inserted into PageTriage queue, so we need to compile BasicData
					// from DB_PRIMARY, since that component accesses the pagetriage_page table.
					$acp->configComponentDb(
						ArticleCompileProcessor::getSafeComponentDbConfigForCompilation()
					);
					$acp->compileMetadata();
				}
			}
			$title = Title::newFromID( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $title ) {
				PageTriageUtil::createNotificationEvent(
					$title,
					$user,
					'pagetriage-mark-as-reviewed'
				);
			}
		}
	}

	/**
	 * Update Article metadata when a user gets blocked.
	 *
	 * 'BlockIpComplete': after an IP address or user is blocked
	 * @param DatabaseBlock $block the block object that was saved
	 * @param User $performer the user who did the block (not the one being blocked)
	 */
	public static function onBlockIpComplete( $block, $performer ) {
		PageTriageUtil::updateMetadataOnBlockChange( $block );
	}

	/**
	 * Send php config vars to js via ResourceLoader
	 *
	 * @param array &$vars variables to be added to the output of the startup module
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$pageTriageDraftNamespaceId = $config->get( 'PageTriageDraftNamespaceId' );
		$vars['pageTriageNamespaces'] = PageTriageUtil::getNamespaces();
		$vars['wgPageTriageDraftNamespaceId'] = $pageTriageDraftNamespaceId;
	}

	/**
	 * Register modules that depend on other state
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( &$resourceLoader ) {
		$viewsToolbarModule = [
			'localBasePath' => __DIR__ . '/../modules',
			'remoteExtPath' => 'PageTriage/modules',
			'packageFiles' => [
				'ext.pageTriage.views.toolbar/ToolbarView.js', // entry point
				'ext.pageTriage.views.toolbar/ToolView.js', // abstract base class
				'ext.pageTriage.views.toolbar/articleInfo.js', // article metadata
				'ext.pageTriage.views.toolbar/minimize.js', // minimize
				'ext.pageTriage.views.toolbar/tags.js', // tagging
				'ext.pageTriage.views.toolbar/mark.js', // mark as reviewed
				'ext.pageTriage.views.toolbar/next.js', // next article
				'ext.pageTriage.views.toolbar/delete.js', // mark for deletion
				[
					'name' => 'ext.pageTriage.views.toolbar/contentLanguageMessages.json',
					'callback' => static function ( RL\Context $context, \Config $config ) {
						$keys = array_merge(
							[
								'pagetriage-mark-mark-talk-page-notify-topic-title',
								'pagetriage-mark-unmark-talk-page-notify-topic-title',
								'pagetriage-feedback-from-new-page-review-process-title',
								'pagetriage-feedback-from-new-page-review-process-message',
								'pagetriage-note-sent-talk-page-notify-topic-title',
								'pagetriage-note-sent-talk-page-notify-topic-title-reviewer',
								'pagetriage-tags-talk-page-notify-topic-title'
							],
							$config->get( 'PageTriageDeletionTagsOptionsContentLanguageMessages' )
						);
						$messages = [];
						foreach ( $keys as $key ) {
							$messages[$key] = $context->msg( $key )->inContentLanguage()->plain();
						}
						return $messages;
					}
				],
				[
					'name' => 'ext.pageTriage.views.toolbar/config.json',
					'callback' => static function ( RL\Context $context, \Config $config ) {
						$pageTriageCurationModules = $config->get( 'PageTriageCurationModules' );
						if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiLove' ) ) {
							$pageTriageCurationModules['wikiLove'] = [
								// depends on WikiLove extension
								'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#WikiLove',
								'namespace' => [ NS_MAIN, NS_USER ],
							];
						}
						return [
							'PageTriageCurationModules' => $pageTriageCurationModules,
							'PageTriageEnableCopyvio' => $config->get( 'PageTriageEnableCopyvio' ),
							'PageTriageEnableOresFilters' => $config->get( 'PageTriageEnableOresFilters' ),
							'TalkPageNoteTemplate' => $config->get( 'TalkPageNoteTemplate' ),
						];
					}
				],
				'external/jquery.badge.js', // Merged into this RL module, see T221269
			],
			'dependencies' => [
				'mediawiki.api',
				'mediawiki.jqueryMsg',
				'mediawiki.messagePoster',
				'mediawiki.Title',
				'moment',
				'ext.pageTriage.util',
				'oojs-ui.styles.icons-alerts',
				'jquery.ui',
				'jquery.spinner',
				'jquery.client',
				'ext.pageTriage.externalTagsOptions',
			],
			'styles' => [
				'external/jquery.badge.css', // Merged into this RL module, see T221269
				'ext.pageTriage.css', // stuff that's shared across all views
				'ext.pageTriage.views.toolbar/ToolbarView.css',
				'ext.pageTriage.views.toolbar/ToolView.less',
				'ext.pageTriage.views.toolbar/articleInfo.css',
				'ext.pageTriage.views.toolbar/mark.css',
				'ext.pageTriage.views.toolbar/tags.less',
				'ext.pageTriage.views.toolbar/delete.less'
			],
			'templates' => [
				'articleInfo.underscore' =>
					'ext.pageTriage.views.toolbar/articleInfo.underscore',
				'articleInfoHistory.underscore' =>
					'ext.pageTriage.views.toolbar/articleInfoHistory.underscore',
				'delete.underscore' =>
					'ext.pageTriage.views.toolbar/delete.underscore',
				'mark.underscore' =>
					'ext.pageTriage.views.toolbar/mark.underscore',
				'tags.underscore' =>
					'ext.pageTriage.views.toolbar/tags.underscore',
				'ToolbarView.underscore' =>
					'ext.pageTriage.views.toolbar/ToolbarView.underscore',
				'ToolView.underscore' =>
					'ext.pageTriage.views.toolbar/ToolView.underscore',
				'wikilove.underscore' =>
					'ext.pageTriage.views.toolbar/wikilove.underscore',
			],
			'messages' => [
				'pagetriage-creation-dateformat',
				'pagetriage-user-creation-dateformat',
				'pagetriage-mark-as-reviewed',
				'pagetriage-mark-as-unreviewed',
				'pagetriage-info-title',
				'pagetriage-byline',
				'pagetriage-byline-new-editor',
				'pagetriage-articleinfo-byline',
				'pagetriage-articleinfo-byline-new-editor',
				'pipe-separator',
				'pagetriage-edits',
				'pagetriage-editcount',
				'pagetriage-author-bot',
				'pagetriage-no-author',
				'pagetriage-info-problem-header',
				'pagetriage-info-history-header',
				'pagetriage-info-history-show-full',
				'pagetriage-info-help',
				'pagetriage-info-problem-non-autoconfirmed',
				'pagetriage-info-problem-non-autoconfirmed-desc',
				'pagetriage-info-problem-blocked',
				'pagetriage-info-problem-blocked-desc',
				'pagetriage-info-problem-no-categories',
				'pagetriage-info-problem-no-categories-desc',
				'pagetriage-info-problem-orphan',
				'pagetriage-info-problem-orphan-desc',
				'pagetriage-info-problem-recreated',
				'pagetriage-info-problem-recreated-desc',
				'pagetriage-info-problem-no-references',
				'pagetriage-info-problem-no-references-desc',
				'pagetriage-info-problem-copyvio',
				'pagetriage-info-problem-copyvio-desc',
				'pagetriage-info-timestamp-date-format',
				'pagetriage-info-timestamp-time-format',
				'pagetriage-info-tooltip',
				'pagetriage-toolbar-collapsed',
				'pagetriage-toolbar-linktext',
				'pagetriage-toolbar-learn-more',
				'pagetriage-mark-as-reviewed-helptext',
				'pagetriage-mark-as-unreviewed-helptext',
				'pagetriage-mark-as-reviewed-error',
				'pagetriage-mark-as-unreviewed-error',
				'pagetriage-markpatrolled',
				'pagetriage-markunpatrolled',
				'pagetriage-note-reviewed',
				'pagetriage-note-not-reviewed',
				'pagetriage-note-deletion',
				'pagetriage-next-tooltip',
				'sp-contributions-talk',
				'contribslink',
				'comma-separator',
				'unknown-error',
				'pagetriage-add-a-note-creator',
				'pagetriage-add-a-note-creator-required',
				'pagetriage-add-a-note-for-options-label',
				'pagetriage-add-a-note-for-option-creator',
				'pagetriage-add-a-note-for-option-reviewer',
				'pagetriage-button-add-a-note-to-creator',
				'pagetriage-button-send-a-note',
				'pagetriage-add-a-note-reviewer',
				'pagetriage-message-for-creator-default-note',
				'pagetriage-message-for-reviewer-placeholder',
				'pagetriage-personal-default-note',
				'pagetriage-special-contributions',
				'pagetriage-tagging-error',
				'pagetriage-del-log-page-missing-error',
				'pagetriage-del-log-page-adding-error',
				'pagetriage-del-talk-page-notify-error',
				'pagetriage-del-discussion-page-adding-error',
				'pagetriage-page-status-reviewed',
				'pagetriage-page-status-reviewed-anonymous',
				'pagetriage-page-status-unreviewed',
				'pagetriage-page-status-autoreviewed',
				'pagetriage-page-status-delete',
				'pagetriage-dot-separator',
				'pagetriage-articleinfo-stat',
				'pagetriage-has-talkpage-feedback',
				'pagetriage-has-talkpage-feedback-link',
				'pagetriage-bytes',
				'pagetriage-edits',
				'pagetriage-categories',
				'pagetriage-add-tag-confirmation',
				'pagetriage-tag-deletion-error',
				'pagetriage-toolbar-close',
				'pagetriage-toolbar-minimize',
				'pagetriage-tag-warning-notice'
			],
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiLove' ) ) {
			$viewsToolbarModule['packageFiles'][] =
				'ext.pageTriage.views.toolbar/wikilove.js';
			$viewsToolbarModule['styles'][] = 'ext.pageTriage.views.toolbar/wikilove.css';
			$viewsToolbarModule['dependencies'][] = 'ext.wikiLove.init';
			$viewsToolbarModule['messages'] = array_merge( $viewsToolbarModule['messages'], [
				'pagetriage-wikilove-page-creator',
				'pagetriage-wikilove-edit-count',
				'pagetriage-wikilove-helptext',
				'pagetriage-wikilove-no-recipients',
				'pagetriage-wikilove-tooltip',
				'wikilove',
				'wikilove-button-send',
			] );
		}

		$resourceLoader->register( 'ext.pageTriage.views.toolbar', $viewsToolbarModule );
	}

	/**
	 * Add PageTriage events to Echo
	 *
	 * @param array &$notifications array a list of enabled echo events
	 * @param array &$notificationCategories array details for echo events
	 * @param array &$icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		global $wgPageTriageEnabledEchoEvents;

		if ( $wgPageTriageEnabledEchoEvents ) {
			$notificationCategories['page-review'] = [
				'priority' => 8,
				'tooltip' => 'echo-pref-tooltip-page-review',
			];
		}

		if ( in_array( 'pagetriage-mark-as-reviewed', $wgPageTriageEnabledEchoEvents ) ) {
			$notifications['pagetriage-mark-as-reviewed'] = [
				'presentation-model' => PageTriageMarkAsReviewedPresentationModel::class,
				'category' => 'page-review',
				'group' => 'neutral',
				'section' => 'message',
			];
		}
		if ( in_array( 'pagetriage-add-maintenance-tag', $wgPageTriageEnabledEchoEvents ) ) {
			$notifications['pagetriage-add-maintenance-tag'] = [
				'presentation-model' => PageTriageAddMaintenanceTagPresentationModel::class,
				'category' => 'page-review',
				'group' => 'neutral',
				'section' => 'alert',
			];
		}
		if ( in_array( 'pagetriage-add-deletion-tag', $wgPageTriageEnabledEchoEvents ) ) {
			$notifications['pagetriage-add-deletion-tag'] = [
				'presentation-model' => PageTriageAddDeletionTagPresentationModel::class,
				'category' => 'page-review',
				'group' => 'negative',
				'section' => 'alert',
			];
			$icons['trash'] = [
				'path' => 'PageTriage/echo-icons/trash.svg'
			];
		}

		return true;
	}

	/**
	 * Add users to be notified on an echo event
	 * @param EchoEvent $event
	 * @param array &$users
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			// notify the page creator/starter
			case 'pagetriage-mark-as-reviewed':
			case 'pagetriage-add-maintenance-tag':
			case 'pagetriage-add-deletion-tag':
				if ( !$event->getTitle() ) {
					break;
				}

				$pageId = $event->getTitle()->getArticleID();

				$articleMetadata = new ArticleMetadata( [ $pageId ], false, DB_REPLICA );
				$metaData = $articleMetadata->getMetadata();

				if ( !$metaData ) {
					break;
				}

				if ( $metaData[$pageId]['user_id'] ) {
					$users[$metaData[$pageId]['user_id']] = User::newFromId( $metaData[$pageId]['user_id'] );
				}
				break;
		}
		return true;
	}

	/**
	 * Handler for LocalUserCreated hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 */
	public static function onLocalUserCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for page reviews.
		if ( !$autocreated ) {
			$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
			$userOptionsManager->setOption( $user, 'echo-subscriptions-email-page-review', true );
		}
	}

	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [ 'pagetriage_log', 'ptrl_user_id' ];
		$updateFields[] = [ 'pagetriage_page', 'ptrp_last_reviewed_by' ];
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$base = __DIR__ . "/../sql";
		// tables
		$updater->addExtensionTable( 'pagetriage_tags', $base . '/PageTriageTags.sql' );
		$updater->addExtensionTable( 'pagetriage_page_tags', $base . '/PageTriagePageTags.sql' );
		$updater->addExtensionTable( 'pagetriage_page', $base . '/PageTriagePage.sql' );
		$updater->addExtensionTable( 'pagetriage_log', $base . '/PageTriageLog.sql' );

		// patches
		$updater->addExtensionIndex(
			'pagetriage_page',
			'ptrp_reviewed_updated',
			$base . '/PageTriagePagePatch.sql'
		);
		$updater->dropExtensionField(
			'pagetriage_log',
			'ptrl_comment',
			$base . '/PageTriageLogPatch_Drop_ptrl_comment.sql'
		);
		$updater->modifyExtensionTable(
			'pagetriage_tags',
			$base . '/PageTriageTagsPatch.sql'
		);
		$updater->modifyExtensionTable(
			'pagetriage_tags',
			$base . '/PageTriageTagsPatch-AfC.sql'
		);
		$updater->modifyExtensionTable(
			'pagetriage_tags',
			$base . '/PageTriageTagsPatch-copyvio.sql'
		);
		$updater->modifyExtensionTable(
			'pagetriage_tags',
			$base . '/PageTriageTagsPatch-recreated.sql'
		);
		$updater->dropExtensionIndex(
			'pagetriage_page_tags',
			'ptrpt_page_tag_id',
			$base . '/PageTriagePageTagsPatch-pk.sql'
		);
	}

	/**
	 * @param RecentChange $rc
	 * @param array &$models Models names to score
	 */
	public static function onORESCheckModels( RecentChange $rc, &$models ) {
		if ( !in_array( $rc->getAttribute( 'rc_type' ), [ RC_NEW, RC_EDIT ] ) ) {
			return;
		}

		if ( !ArticleMetadata::validatePageIds(
			[ $rc->getTitle()->getArticleID() ], DB_REPLICA
		) ) {
			return;
		}

		// Ensure all pages in the PageTriage queue
		// are scored for both models regardless of namespace.
		foreach ( [ 'articlequality', 'draftquality' ] as $model ) {
			if ( !in_array( $model, $models ) ) {
				$models[] = $model;
			}
		}
	}

	/**
	 * ListDefinedTags, ChangeTagsListActive and ChangeTagsAllowedAdd hook handler.
	 * Registers this extension's tags.
	 *
	 * @param string[] &$tags
	 */
	public static function onDefinedTags( &$tags ) {
		$tags[] = 'pagetriage';
	}
}
