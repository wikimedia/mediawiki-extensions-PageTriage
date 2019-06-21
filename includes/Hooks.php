<?php

namespace MediaWiki\Extension\PageTriage;

use Content;
use DatabaseUpdater;
use EchoEvent;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use Article;
use MediaWiki\Block\DatabaseBlock;
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
			return;
		}

		// If not a new record to pagetriage queue, do nothing.
		if ( !self::addToPageTriageQueue( $oldid, $newTitle, $user ) ) {
			return;
		}
		// Item was newly added to PageTriage queue in master DB, compile metadata.
		$acp = ArticleCompileProcessor::newFromPageId( [ $oldid ] );
		if ( $acp ) {
			// Since this is a title move, the only component requiring DB_MASTER will be
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
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 *
	 * @param WikiPage $wikiPage the WikiPage edited
	 * @param Revision|null $rev the new revision
	 * @param int $baseID the revision ID this was based on, if any
	 * @param User $user the editing user
	 */
	public static function onNewRevisionFromEditComplete( WikiPage $wikiPage, $rev, $baseID, $user ) {
		if ( !in_array( $wikiPage->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		if ( $rev && $rev->getParentId() ) {
			// Make sure $prev->getContent() is done post-send if possible
			DeferredUpdates::addCallableUpdate( function () use ( $rev, $wikiPage, $user ) {
				$prev = Revision::newFromId( $rev->getParentId() );
				if ( $prev && !$wikiPage->isRedirect() && $prev->getContent()->isRedirect() ) {
					// Add item to queue, if it's not already there.
					self::addToPageTriageQueue( $wikiPage->getId(), $wikiPage->getTitle(), $user );
				}
			} );
		}
	}

	/**
	 * New article is created, insert it into PageTriage Queue and compile metadata.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentInsertComplete
	 * @param WikiPage $article WikiPage created
	 * @param User $user User creating the article
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor Whether or not the edit was marked as minor
	 * @param bool $isWatch (No longer used)
	 * @param bool $section (No longer used)
	 * @param int $flags Flags passed to Article::doEdit()
	 * @param Revision $revision New Revision of the article
	 */
	public static function onPageContentInsertComplete(
		$article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision
	) {
		// Don't add to queue if not in a namespace of interest.
		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Add item to queue. Metadata compilation will get triggered in the LinksUpdate hook.
		self::addToPageTriageQueue( $article->getId(), $article->getTitle(), $user );
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
	 */
	public static function onPageContentSaveComplete(
		WikiPage $wikiPage, $user, $content, $summary,
		$minoredit, $watchthis, $sectionanchor, $flags, $revision,
		$status, $baseRevId
	) {
		self::flushUserStatusCache( $wikiPage->getTitle() );
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

		DeferredUpdates::addCallableUpdate( function () use ( $linksUpdate ) {
			// Validate the page ID from DB_MASTER, compile metadata from DB_MASTER and return.
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $linksUpdate->getTitle()->getArticleId() ],
				false,
				DB_MASTER
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
	 * @param User|null $user
	 * @param string|null $reviewed numeric string See PageTriage::getValidReviewedStatus()
	 * @return bool
	 */
	public static function addToPageTriageQueue( $pageId, $title, $user = null, $reviewed = null ) {
		global $wgUseRCPatrol, $wgUseNPPatrol;

		// Get draft information.
		$draftNsId = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageTriageDraftNamespaceId' );
		$isDraft = false !== $draftNsId && $title->inNamespace( $draftNsId );

		// Draft redirects are not patrolled or reviewed.
		if ( $isDraft && $title->isRedirect() ) {
			return false;
		}

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
				$isAutopatrolled = ( $wgUseRCPatrol || $wgUseNPPatrol ) &&
					!count( $title->getUserPermissionsErrors( 'autopatrol', $user ) );
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
			return $pageTriage->addToPageTriageQueue( $reviewed, $user );
		}
	}

	/**
	 * Flush user page/user talk page existance status, this function should
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
	private static function shouldShowNoIndex( $article ) {
		global $wgPageTriageNoIndexUnreviewedNewArticles;

		// If the __NOINDEX__ magic word is on a new article, then allow
		// it to work, regardless of namespace robot policies.
		if ( $article->mParserOutput instanceof ParserOutput
			&& $article->mParserOutput->getProperty( 'noindex' ) !== false
		) {
			// Short circuit since we know it will fail the next set
			// of tests as well.
			return self::isArticleNew( $article );
		}

		return $wgPageTriageNoIndexUnreviewedNewArticles
			&& PageTriageUtil::doesPageNeedTriage( $article )
			&& self::isArticleNew( $article );
	}

	/**
	 * Checks to see if an article is new, i.e. less than $wgPageTriageMaxAge
	 *
	 * Look in cache for the creation date, if not found, query the replica for the value
	 * of ptrp_created.
	 *
	 * @param Article $article Article to check
	 * @return bool
	 */
	private static function isArticleNew( $article ) {
		global $wgPageTriageMaxAge;
		$pageId = $article->getId();
		// Check cache for creation date.
		$metaDataObject = new ArticleMetadata( [ $pageId ] );
		$cacheData = $metaDataObject->getMetadataFromCache( $pageId );
		$pageCreationDateTime = $cacheData[ 'creation_date' ] ?? null;
		// If not found in cache, get from replica. The ptrp_created field is equivalent to
		// creation_date property set during article metadata compilation.
		if ( !$pageCreationDateTime ) {
			$dbr = wfGetDB( DB_REPLICA );
			$pageCreationDateTime = $dbr->selectField(
				'pagetriage_page',
				'ptrp_created',
				[ 'ptrp_page_id' => $pageId ]
			);
		}
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
	 */
	public static function onArticleViewFooter( $article, $patrolFooterShown ) {
		global $wgPageTriageEnableCurationToolbar;

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
			return;
		}

		// Don't show anything for user with no patrol right
		if ( !$article->getTitle()->quickUserCan( 'patrol' ) ) {
			return;
		}

		// Only show in defined namespaces
		if ( !in_array( $article->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Don't do anything if it's coming from Special:NewPages
		if ( $request->getVal( 'patrolpage' ) ) {
			return;
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
					// from DB_MASTER, since that component accesses the pagetriage_page table.
					$acp->configComponentDb(
						ArticleCompileProcessor::getSafeComponentDbConfigForCompilation()
					);
					$acp->compileMetadata();
				}
			}
			$article = Article::newFromID( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $article ) {
				PageTriageUtil::createNotificationEvent( $article, $user, 'pagetriage-mark-as-reviewed' );
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
	}

	/**
	 * Register modules that depend on other state
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( &$resourceLoader ) {
		$template = [
			'localBasePath' => __DIR__ . '/../modules',
			'remoteExtPath' => 'PageTriage/modules'
		];

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
			'class' => PageTriageMessagesModule::class,
			'dependencies' => [
				'mediawiki.api',
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
				'pagetriage-info-problem-recreated',
				'pagetriage-info-problem-recreated-desc',
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

		$viewsToolbarModule['templates'] = [
			'articleInfo.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfo.underscore',
			'articleInfoHistory.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfoHistory.underscore',
			'delete.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.delete.underscore',
			'mark.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.mark.underscore',
			'tags.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.tags.underscore',
			'toolbarView.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.toolbarView.underscore',
			'toolView.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.toolView.underscore',
			'wikilove.underscore' =>
				'ext.pageTriage.views.toolbar/ext.pageTriage.wikilove.underscore',
		];

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
	}

	/**
	 * @param DatabaseUpdater|null $updater
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
	}

	/**
	 * @param RecentChange $rc
	 * @param array &$models Models names to score
	 */
	public static function onORESCheckModels( RecentChange $rc, &$models ) {
		if ( !in_array( $rc->getAttribute( 'rc_type' ), [ RC_NEW, RC_EDIT ] ) ) {
			return;
		}

		if ( !ArticleMetadata::validatePageId(
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

}
