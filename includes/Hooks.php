<?php

namespace MediaWiki\Extension\PageTriage;

use Block;
use Content;
use DatabaseUpdater;
use EchoEvent;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use Article;
use DeferredUpdates;
use ExtensionRegistry;
use Html;
use LinksUpdate;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddDeletionTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddMaintenanceTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageMarkAsReviewedPresentationModel;
use MediaWiki\MediaWikiServices;
use MWTimestamp;
use ParserOutput;
use RecentChange;
use ResourceLoader;
use Revision;
use Status;
use Title;
use User;
use WikiPage;

class Hooks {

	/**
	 * Mark a page as unreviewed after moving the page from non-main(article) namespace to
	 * main(article) namespace
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleMoveComplete
	 * @param Title &$oldTitle old title object
	 * @param Title &$newTitle new title object
	 * @param User $user User doing the move
	 * @param int $oldid Page id of moved page
	 * @param int $newid Page id of created redirect, or 0 if suppressed
	 * @param string $reason Reason for the move
	 * @param Revision $revision Null revision created by the move
	 * @return bool
	 */
	public static function onTitleMoveComplete(
		Title &$oldTitle, Title &$newTitle, User $user, $oldid, $newid, $reason, Revision $revision
	) {
		// Delete cache for record if it's in pagetriage queue
		$articleMetadata = new ArticleMetadata( [ $oldid ] );
		$articleMetadata->flushMetadataFromCache();

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
			return true;
		}

		// New record to pagetriage queue, compile metadata
		if ( self::addToPageTriageQueue( $oldid, $newTitle, $user ) ) {
			$acp = ArticleCompileProcessor::newFromPageId( [ $oldid ] );
			if ( $acp ) {
				// safe to use slave db for data compilation for the
				// following components, BasicData is accessing pagetriage_page,
				// which is not safe to use slave db
				$config = [
						'LinkCount' => DB_REPLICA,
						'CategoryCount' => DB_REPLICA,
						'Snippet' => DB_REPLICA,
						'UserData' => DB_REPLICA,
						'DeletionTag' => DB_REPLICA
				];
				$acp->configComponentDb( $config );
				$acp->compileMetadata();
			}
		}

		return true;
	}

	/**
	 * Check if a page is created from a redirect page, then insert into it PageTriage Queue
	 * Note: Page will be automatically marked as triaged for users with autopatrol right
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 *
	 * @param WikiPage $wikiPage the WikiPage edited
	 * @param Revision|null $rev the new revision
	 * @param int $baseID the revision ID this was based on, if any
	 * @param User $user the editing user
	 *
	 * @return bool
	 */
	public static function onNewRevisionFromEditComplete( WikiPage $wikiPage, $rev, $baseID, $user ) {
		if ( !in_array( $wikiPage->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return true;
		}

		if ( $rev && $rev->getParentId() ) {
			// Make sure $prev->getContent() is done post-send if possible
			DeferredUpdates::addCallableUpdate( function () use ( $rev, $wikiPage, $user ) {
				$prev = $rev->getPrevious();
				if ( $prev && !$wikiPage->isRedirect() && $prev->getContent()->isRedirect() ) {
					self::addToPageTriageQueue(
						$wikiPage->getId(),
						$wikiPage->getTitle(), $user );
				}
			} );
		}

		return true;
	}

	/**
	 * When a new article is created, insert it into the PageTriage Queue
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleInsertComplete
	 * @param WikiPage $article WikiPage created
	 * @param User $user User creating the article
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor Whether or not the edit was marked as minor
	 * @param bool $isWatch (No longer used)
	 * @param bool $section (No longer used)
	 * @param int $flags Flags passed to Article::doEdit()
	 * @param Revision $revision New Revision of the article
	 * @return bool
	 */
	public static function onPageContentInsertComplete(
		$article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision
	) {
		// Don't add to queue if not in a namespace of interest.
		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return true;
		}

		self::addToPageTriageQueue( $article->getId(), $article->getTitle(), $user );

		return true;
	}

	/**
	 * Flush user status cache on a successful save.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $minoredit
	 * @param bool $watchthis
	 * @param string $sectionanchor
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int $baseRevId
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete(
		WikiPage $wikiPage, $user, $content, $summary,
		$minoredit, $watchthis, $sectionanchor, $flags, $revision,
		$status, $baseRevId
	) {
		self::flushUserStatusCache( $wikiPage->getTitle() );
		return true;
	}

	/**
	 * Update metadata when link information is updated. This is also run after every page save.
	 * @param LinksUpdate $linksUpdate
	 * @return bool
	 */
	public static function onLinksUpdateComplete( LinksUpdate $linksUpdate ) {
		if ( !in_array( $linksUpdate->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return true;
		}

		DeferredUpdates::addCallableUpdate( function () use ( $linksUpdate ) {
			// false will enforce a validation against pagetriage_page table
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $linksUpdate->getTitle()->getArticleId() ], false, DB_MASTER );

			if ( $acp ) {
				$acp->registerLinksUpdate( $linksUpdate );
				$acp->compileMetadata();
			}
		} );
		return true;
	}

	/**
	 * Remove the metadata we added when the article is deleted.
	 *
	 * 'ArticleDeleteComplete': after an article is deleted
	 * @param WikiPage $article the WikiPage that was deleted
	 * @param User $user the user that deleted the article
	 * @param string $reason the reason the article was deleted
	 * @param int $id id of the article that was deleted
	 * @return true
	 */
	public static function onArticleDeleteComplete( $article, $user, $reason, $id ) {
		self::flushUserStatusCache( $article->getTitle() );

		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return true;
		}

		// delete everything
		$pageTriage = new PageTriage( $id );
		$pageTriage->deleteFromPageTriage();
		return true;
	}

	/**
	 * Add page to page triage queue, check for autopatrol right if reviewed is not set
	 *
	 * This method should only be called from this class and its closures
	 *
	 * @param int $pageId
	 * @param Title $title
	 * @param User|null $user
	 * @param string|null $reviewed numeric string See PageTriage::getValidReviewedStatus()
	 * @return bool
	 */
	public static function addToPageTriageQueue( $pageId, $title, $user = null, $reviewed = null ) {
		global $wgUseRCPatrol, $wgUseNPPatrol;

		$pageTriage = new PageTriage( $pageId );

		// action taken by system
		if ( is_null( $user ) ) {
			if ( is_null( $reviewed ) ) {
				$reviewed = '0';
			}
			return $pageTriage->addToPageTriageQueue( $reviewed );
		// action taken by a user
		} else {
			// set reviewed if it's not set yet
			if ( is_null( $reviewed ) ) {
				$draftNsId = MediaWikiServices::getInstance()
					->getMainConfig()
					->get( 'PageTriageDraftNamespaceId' );
				if ( ( $wgUseRCPatrol || $wgUseNPPatrol )
					&& !count( $title->getUserPermissionsErrors( 'autopatrol', $user ) )
					&& ( $draftNsId && !$title->inNamespace( $draftNsId ) )
				) {
					// Set as reviewed if the user has the autopatrol right
					// and they're not creating a Draft.
					$reviewed = 3;
				} else {
					// If they have no autopatrol right and are not making an explicit review,
					// set to unreviewed (as the system would, in this situation).
					return $pageTriage->addToPageTriageQueue( '0' );
				}
			}
			return $pageTriage->addToPageTriageQueue( $reviewed, $user );
		}
	}

	/**
	 * Add last time user visited the triage page to preferences.
	 * @param User $user User object
	 * @param array &$preferences array Preferences object
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		$preferences['pagetriage-lastuse'] = [
			'type' => 'api',
		];

		return true;
	}

	/**
	 * Flush user page/user talk page exsitance status, this function should
	 * be called when a page gets created/deleted/moved/restored
	 * @param Title $title
	 */
	private static function flushUserStatusCache( $title ) {
		global $wgMemc;

		if ( in_array( $title->getNamespace(), [ NS_USER, NS_USER_TALK ] ) ) {
			$wgMemc->delete( PageTriageUtil::userStatusKey( $title->getText() ) );
		}
	}

	/**
	 * Determines whether to set noindex for the article specified
	 *
	 * Returns true if all of the following are true:
	 *   1. The page includes a template that triggers noindexing
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
	private static function shouldShowNoIndex( $article ) {
		global $wgPageTriageNoIndexUnreviewedNewArticles, $wgPageTriageNoIndexTemplates;

		// See if article includes any templates that should trigger noindexing
		// TODO: This system is a bit hacky and unintuitive. At some point we
		// may want to switch to a system based on the __NOINDEX__ magic word.
		if ( $wgPageTriageNoIndexTemplates && $article->mParserOutput instanceof ParserOutput ) {
			// Properly format the template names to match what getTemplates() returns
			$noIndexTemplates = array_map(
				[ static::class, 'formatTemplateName' ],
				$wgPageTriageNoIndexTemplates
			);

			// getTemplates returns all transclusions, not just NS_TEMPLATE
			$allTransclusions = $article->mParserOutput->getTemplates();

			$templates = isset( $allTransclusions[NS_TEMPLATE] ) ?
				$allTransclusions[NS_TEMPLATE] :
				[];

			foreach ( $noIndexTemplates as $noIndexTemplate ) {
				if ( isset( $templates[ $noIndexTemplate ] ) ) {
					// The noindex template feature is restricted to new articles
					// to minimize the potential for abuse.
					if ( self::isArticleNew( $article ) ) {
						return true;
					} else {
						// Short circuit since we know it will fail the next set
						// of tests as well.
						return false;
					}
				}
			}
		}

		if ( $wgPageTriageNoIndexUnreviewedNewArticles &&
			PageTriageUtil::doesPageNeedTriage( $article ) &&
			self::isArticleNew( $article )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks to see if an article is new, i.e. less than $wgPageTriageMaxAge
	 * @param Article $article Article to check
	 * @return bool
	 */
	private static function isArticleNew( $article ) {
		global $wgPageTriageMaxAge;

		$pageId = $article->getId();

		// Get timestamp for article creation (typically from cache)
		$metaDataObject = new ArticleMetadata( [ $pageId ] );
		$metaData = $metaDataObject->getMetadata();
		if ( $metaData && isset( $metaData[ $pageId ][ 'creation_date' ] ) ) {
			$pageCreationDateTime = $metaData[ $pageId ][ 'creation_date' ];

			// Get the age of the article in days
			$timestamp = new MWTimestamp( $pageCreationDateTime );
			$dateInterval = $timestamp->diff( new MWTimestamp() );
			$articleDaysOld = $dateInterval->format( '%a' );

			// If it's younger than the maximum age, return true.
			if ( $articleDaysOld < $wgPageTriageMaxAge ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Formats a template name to match the format returned by getTemplates()
	 * @param string $template
	 * @return string
	 */
	private static function formatTemplateName( $template ) {
		$template = ucfirst( trim( $template ) );
		$template = str_replace( ' ', '_', $template );
		return $template;
	}

	/**
	 * Handler for hook ArticleViewFooter, this will determine whether to load
	 * curation toolbar or 'mark as reviewed'/'reviewed' text
	 *
	 * @param Article $article Article object to show link for.
	 * @param bool $patrolFooterShown whether the patrol footer is shown
	 * @return bool
	 */
	public static function onArticleViewFooter( $article, $patrolFooterShown ) {
		global $wgPageTriageMarkPatrolledLinkExpiry, $wgPageTriageEnableCurationToolbar;

		$context = $article->getContext();
		$user = $context->getUser();
		$outputPage = $context->getOutput();
		$request = $context->getRequest();

		// Overwrite the noindex rule defined in Article::view(), this also affects main namespace
		if ( self::shouldShowNoIndex( $article ) ) {
			$outputPage->setRobotPolicy( 'noindex,nofollow' );
		}

		// Only logged in users can review
		if ( !$user->isLoggedIn() ) {
			return true;
		}

		// Don't show anything for user with no patrol right
		if ( !$article->getTitle()->quickUserCan( 'patrol' ) ) {
			return true;
		}

		// Only show in defined namespaces
		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return true;
		}

		// Don't do anything if it's coming from Special:NewPages
		if ( $request->getVal( 'patrolpage' ) ) {
			return true;
		}

		// If the user hasn't visited Special:NewPagesFeed lately, don't do anything
		$lastUseExpired = false;
		$lastUse = $user->getOption( 'pagetriage-lastuse' );
		if ( $lastUse ) {
			$lastUse = wfTimestamp( TS_UNIX, $lastUse );
			$now = wfTimestamp( TS_UNIX, wfTimestampNow() );
			$periodSince = $now - $lastUse;
		}
		if ( !$lastUse || $periodSince > $wgPageTriageMarkPatrolledLinkExpiry ) {
			$lastUseExpired = true;
		}

		// See if the page is in the PageTriage page queue
		// If it isn't, $needsReview will be null
		// Also, users without the autopatrol right can't review their own pages
		$needsReview = PageTriageUtil::doesPageNeedTriage( $article );
		if ( !is_null( $needsReview )
			&& !( $user->getId() == $article->getOldestRevision()->getUser()
				&& !$user->isAllowed( 'autopatrol' )
			)
		) {
			if ( $wgPageTriageEnableCurationToolbar || $request->getVal( 'curationtoolbar' ) === 'true' ) {
				// Load the JavaScript for the curation toolbar
				$outputPage->addModules( 'ext.pageTriage.toolbarStartup' );
				// Set the config flags in JavaScript
				$globalVars = [
					'wgPageTriagelastUseExpired' => $lastUseExpired,
					'wgPageTriagePagePrefixedText' => $article->getTitle()->getPrefixedText()
				];
				$outputPage->addJsConfigVars( $globalVars );
			} else {
				if ( $needsReview ) {
					// show 'Mark as reviewed' link
					$msg = wfMessage( 'pagetriage-markpatrolled' )->text();
					$msg = Html::element(
						'a',
						[ 'href' => '#', 'class' => 'mw-pagetriage-markpatrolled-link' ],
						$msg
					);
				} else {
					// show 'Reviewed' text
					$msg = wfMessage( 'pagetriage-reviewed' )->escaped();
				}
				$outputPage->addModules( [ 'ext.pageTriage.article' ] );
				$html = Html::rawElement( 'div', [ 'class' => 'mw-pagetriage-markpatrolled' ], $msg );
				$outputPage->addHTML( $html );
			}
		}

		return true;
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
	 * @return bool
	 */
	public static function onMarkPatrolledComplete( $rcid, &$user, $wcOnlySysopsCanPatrol ) {
		$rc = RecentChange::newFromId( $rcid );

		if ( $rc ) {
			if ( !in_array( $rc->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
				return true;
			}

			$pt = new PageTriage( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $pt->addToPageTriageQueue( '2', $user, true /* fromRc */ ) ) {
				// Compile metadata for new page triage record
				$acp = ArticleCompileProcessor::newFromPageId( [ $rc->getAttribute( 'rc_cur_id' ) ] );
				if ( $acp ) {
					// page just gets added to pagetriage queue and hence not safe to use slave db
					// for BasicData since it's accessing pagetriage_page table
					$config = [
						'LinkCount' => DB_REPLICA,
						'CategoryCount' => DB_REPLICA,
						'Snippet' => DB_REPLICA,
						'UserData' => DB_REPLICA,
						'DeletionTag' => DB_REPLICA
					];
					$acp->configComponentDb( $config );
					$acp->compileMetadata();
				}
			}
			$article = Article::newFromID( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $article ) {
				PageTriageUtil::createNotificationEvent( $article, $user, 'pagetriage-mark-as-reviewed' );
			}
		}

		return true;
	}

	/**
	 * Update Article metadata when a user gets blocked
	 *
	 * 'BlockIpComplete': after an IP address or user is blocked
	 * @param Block $block the Block object that was saved
	 * @param User $performer the user who did the block (not the one being blocked)
	 * @return bool
	 */
	public static function onBlockIpComplete( $block, $performer ) {
		PageTriageUtil::updateMetadataOnBlockChange( $block );
		return true;
	}

	/**
	 * Send php config vars to js via ResourceLoader
	 *
	 * @param array &$vars variables to be added to the output of the startup module
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$pageTriageCurationModules = $config->get( 'PageTriageCurationModules' );
		$talkPageNoteTemplate = $config->get( 'TalkPageNoteTemplate' );
		$pageTriageDraftNamespaceId = $config->get( 'PageTriageDraftNamespaceId' );

		// check if WikiLove is enabled
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiLove' ) ) {
			$pageTriageCurationModules['wikiLove'] = [
				// depends on WikiLove extension
				'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#WikiLove',
				'namespace' => [ NS_MAIN, NS_USER ],
			];
		}

		$vars['wgPageTriageCurationModules'] = $pageTriageCurationModules;
		$vars['pageTriageNamespaces'] = PageTriageUtil::getNamespaces();
		$vars['wgPageTriageDraftNamespaceId'] = $pageTriageDraftNamespaceId;
		$vars['wgTalkPageNoteTemplate'] = $talkPageNoteTemplate;
		return true;
	}

	/**
	 * Register modules that depend on other state
	 *
	 * @param ResourceLoader &$resourceLoader
	 * @return bool true
	 */
	public static function onResourceLoaderRegisterModules( &$resourceLoader ) {
		global $wgPageTriageDeletionTagsOptionsContentLanguageMessages;

		$template = [
			'localBasePath' => __DIR__.'/../modules',
			'remoteExtPath' => 'PageTriage/modules'
		];

		$messagesModule = [
			'class' => 'MediaWiki\Extension\PageTriage\PageTriageMessagesModule',
			'contentLanguageMessages' => array_merge(
				[
					'pagetriage-mark-mark-talk-page-notify-topic-title',
					'pagetriage-mark-unmark-talk-page-notify-topic-title',
					'pagetriage-tags-talk-page-notify-topic-title',
				],
				$wgPageTriageDeletionTagsOptionsContentLanguageMessages
			),
		];

		$resourceLoader->register( 'ext.pageTriage.messages', $messagesModule );

		$toolBaseClass = [
			'ext.pageTriage.views.toolbar/ext.pageTriage.toolView.js', // abstract class first
		];

		// Individual tools on toolbar
		$tools = [
			'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfo.js', // article metadata
			'ext.pageTriage.views.toolbar/ext.pageTriage.minimize.js', // minimize
			'ext.pageTriage.views.toolbar/ext.pageTriage.tags.js', // tagging
			'ext.pageTriage.views.toolbar/ext.pageTriage.mark.js', // mark as reviewed
			'ext.pageTriage.views.toolbar/ext.pageTriage.next.js', // next article
			'ext.pageTriage.views.toolbar/ext.pageTriage.delete.js', // mark for deletion
		];

		$afterTools = [
			'ext.pageTriage.views.toolbar/ext.pageTriage.toolbarView.js', // overall toolbar view last
			'external/jquery.effects.core.js',
			'external/jquery.effects.squish.js',
		];

		$viewsToolbarModule = $template + [
			'dependencies' => [
				'mediawiki.jqueryMsg',
				'mediawiki.messagePoster',
				'mediawiki.Title',
				'ext.pageTriage.models',
				'ext.pageTriage.util',
				'jquery.badge.external',
				'jquery.ui.button',
				'jquery.ui.draggable',
				'jquery.spinner',
				'jquery.client',
				'ext.pageTriage.externalTagsOptions',
				'ext.pageTriage.externalDeletionTagsOptions',
				'ext.pageTriage.messages',
			],
			'styles' => [
				'ext.pageTriage.css', // stuff that's shared across all views
				'ext.pageTriage.views.toolbar/ext.pageTriage.toolbarView.css',
				'ext.pageTriage.views.toolbar/ext.pageTriage.toolView.css',
				'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfo.css',
				'ext.pageTriage.views.toolbar/ext.pageTriage.mark.css',
				'ext.pageTriage.views.toolbar/ext.pageTriage.tags.css',
				'ext.pageTriage.views.toolbar/ext.pageTriage.delete.css'
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
				'pagetriage-info-problem-no-references',
				'pagetriage-info-problem-no-references-desc',
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
				'pagetriage-add-a-note-reviewer',
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
			$tools[] = 'ext.pageTriage.views.toolbar/ext.pageTriage.wikilove.js';
			$viewsToolbarModule['styles'][] = 'ext.pageTriage.views.toolbar/ext.pageTriage.wikilove.css';
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

		$viewsToolbarModule['scripts'] = array_merge(
			$toolBaseClass,
			$tools,
			$afterTools
		);

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
	 * @return bool
	 */
	public static function onLocalUserCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for page reviews.
		if ( !$autocreated ) {
			$user->setOption( 'echo-subscriptions-email-page-review', true );
			$user->saveSettings();
		}
		return true;
	}

	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [ 'pagetriage_log', 'ptrl_user_id' ];
		$updateFields[] = [ 'pagetriage_page', 'ptrp_last_reviewed_by' ];

		return true;
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater = null ) {
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

		return true;
	}
}
