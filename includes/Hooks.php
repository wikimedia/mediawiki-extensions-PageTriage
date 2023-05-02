<?php

namespace MediaWiki\Extension\PageTriage;

use ApiDisabled;
use Article;
use Config;
use DeferredUpdates;
use EchoEvent;
use ExtensionRegistry;
use IBufferingStatsdDataFactory;
use ManualLogEntry;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddDeletionTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageAddMaintenanceTagPresentationModel;
use MediaWiki\Extension\PageTriage\Notifications\PageTriageMarkAsReviewedPresentationModel;
use MediaWiki\Hook\BlockIpCompleteHook;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\MarkPatrolledCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\UnblockUserCompleteHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleViewFooterHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;
use MediaWiki\WikiMap\WikiMap;
use MWTimestamp;
use ParserOutput;
use RecentChange;
use User;
use Wikimedia\Rdbms\Database;
use WikiPage;

class Hooks implements
	ApiMain__moduleManagerHook,
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	ChangeTagsAllowedAddHook,
	PageMoveCompleteHook,
	RevisionFromEditCompleteHook,
	PageSaveCompleteHook,
	LinksUpdateCompleteHook,
	ArticleViewFooterHook,
	PageDeleteCompleteHook,
	MarkPatrolledCompleteHook,
	BlockIpCompleteHook,
	UnblockUserCompleteHook,
	ResourceLoaderGetConfigVarsHook,
	LocalUserCreatedHook
{

	private const TAG_NAME = 'pagetriage';

	/** @var Config */
	private Config $config;
	/** @var QueueManager */
	private QueueManager $queueManager;

	/** @var RevisionLookup */
	private RevisionLookup $revisionLookup;

	/** @var IBufferingStatsdDataFactory */
	private IBufferingStatsdDataFactory $statsdDataFactory;

	/** @var PermissionManager */
	private PermissionManager $permissionManager;

	/** @var RevisionStore */
	private RevisionStore $revisionStore;

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/** @var UserOptionsManager */
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param Config $config
	 * @param RevisionLookup $revisionLookup
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param PermissionManager $permissionManager
	 * @param RevisionStore $revisionStore
	 * @param TitleFactory $titleFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param QueueManager $queueManager
	 */
	public function __construct(
		Config $config,
		RevisionLookup $revisionLookup,
		IBufferingStatsdDataFactory $statsdDataFactory,
		PermissionManager $permissionManager,
		RevisionStore $revisionStore,
		TitleFactory $titleFactory,
		UserOptionsManager $userOptionsManager,
		QueueManager $queueManager
	) {
		$this->config = $config;
		$this->revisionLookup = $revisionLookup;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->permissionManager = $permissionManager;
		$this->revisionStore = $revisionStore;
		$this->titleFactory = $titleFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->queueManager = $queueManager;
	}

	/** @inheritDoc */
	public function onPageMoveComplete(
		$oldTitle,
		$newTitle,
		$user,
		$oldid,
		$newid,
		$reason,
		$revisionRecord
	) {
		// Mark a page as unreviewed after moving the page from non-main(article) namespace to
		// main(article) namespace
		// Delete cache for record if it's in pagetriage queue
		$articleMetadata = new ArticleMetadata( [ $oldid ] );
		$articleMetadata->flushMetadataFromCache();

		$oldTitle = $this->titleFactory->newFromLinkTarget( $oldTitle );
		$newTitle = $this->titleFactory->newFromLinkTarget( $newTitle );

		// Delete user status cache
		self::flushUserStatusCache( $oldTitle->toPageIdentity() );
		self::flushUserStatusCache( $newTitle->toPageIdentity() );

		$oldNamespace = $oldTitle->getNamespace();
		$newNamespace = $newTitle->getNamespace();
		// Do nothing further on if
		// 1. the page move is within the same namespace or
		// 2. the new page is not in either the main or draft namespaces
		$draftNsId = $this->config->get( 'PageTriageDraftNamespaceId' );
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

	/** @inheritDoc */
	public function onRevisionFromEditComplete( $wikiPage, $rev, $baseID, $user, &$tags ) {
		// Check if a page is created from a redirect page, then insert into it PageTriage Queue
		// Note: Page will be automatically marked as triaged for users with autopatrol right
		if ( !in_array( $wikiPage->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		if ( $rev && $rev->getParentId() ) {
			// Make sure $prev->getContent() is done post-send if possible
			DeferredUpdates::addCallableUpdate( function () use ( $rev, $wikiPage, $user ) {
				$prevRevRecord = $this->revisionLookup->getRevisionById( $rev->getParentId() );
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

	/** @inheritDoc */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		// When a new article is created, insert it into PageTriage Queue and compile metadata.
		// Page saved, flush cache
		self::flushUserStatusCache( $wikiPage );

		if ( !( $flags & EDIT_NEW ) ) {
			// Don't add to queue if it is not a new page
			return;
		}

		// Don't add to queue if not in a namespace of interest.
		if ( !in_array( $wikiPage->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Add item to queue. Metadata compilation will get triggered in the LinksUpdate hook.
		self::addToPageTriageQueue(
			$wikiPage->getId(),
			$wikiPage->getTitle(),
			$user
		);
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		if ( !in_array( $linksUpdate->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			return;
		}

		// Update metadata when link information is updated.
		// This is also run after every page save.
		// Note that this hook can be triggered by a GET request (rollback action, until T88044 is
		// sorted out), in which case master DB connections and writes on GET request can occur.
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
	 * Add page to page triage queue, check for autopatrol right if reviewed is not set
	 *
	 * This method should only be called from this class and its closures
	 *
	 * @param int $pageId
	 * @param Title $title
	 * @param UserIdentity|null $userIdentity
	 * @return bool
	 * @throws MWPageTriageMissingRevisionException
	 */
	public static function addToPageTriageQueue( $pageId, $title, $userIdentity = null ): bool {
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
			return $pageTriage->addToPageTriageQueue();
		// action taken by a user
		} else {
			// set reviewed if it's not set yet
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
			$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
				->getPermissionErrors( 'autopatrol', $user, $title );
			$isAutopatrolled = ( $wgUseRCPatrol || $wgUseNPPatrol ) &&
				!count( $permissionErrors );
			if ( $isAutopatrolled && !$isDraft ) {
				// Set as reviewed if the user has the autopatrol right,
				// and they're not creating a Draft.
				return $pageTriage->addToPageTriageQueue(
					QueueRecord::REVIEW_STATUS_AUTOPATROLLED,
					$userIdentity
				);
			}
			// If they have no autopatrol right and are not making an explicit review,
			// set to unreviewed (as the system would, in this situation).
			return $pageTriage->addToPageTriageQueue();
		}
	}

	/**
	 * Flush user page/user talk page existence status, this function should
	 * be called when a page gets created/deleted/moved/restored
	 *
	 * @param PageIdentity $pageIdentity
	 */
	private static function flushUserStatusCache( PageIdentity $pageIdentity ): void {
		if ( in_array( $pageIdentity->getNamespace(), [ NS_USER, NS_USER_TALK ] ) ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cache->delete( PageTriageUtil::userStatusKey( $pageIdentity->getDBkey() ) );
		}
	}

	/**
	 * Determines whether to set noindex for the article specified
	 *
	 * The NOINDEX logic is explained at:
	 * https://www.mediawiki.org/wiki/Extension:PageTriage#NOINDEX
	 *
	 * @param Article $article
	 * @return bool
	 */
	private static function shouldShowNoIndex( Article $article ) {
		$page = $article->getPage();

		if ( self::shouldNoIndexForNewArticleReasons( $page ) ) {
			return true;
		}

		$wikitextHasNoIndexMagicWord = $article->mParserOutput instanceof ParserOutput
			&& $article->mParserOutput->getPageProperty( 'noindex' ) !== null;

		return $wikitextHasNoIndexMagicWord && self::shouldNoIndexForMagicWordReasons( $page );
	}

	/**
	 * Calculate whether we should show NOINDEX, based on criteria related to whether
	 * the page is reviewed.
	 *
	 * The NOINDEX logic is explained at:
	 * https://www.mediawiki.org/wiki/Extension:PageTriage#NOINDEX
	 *
	 * Note that we always check the age of the page last since that is potentially the
	 * most expensive check (if the data isn't cached). Performance is important because
	 * this code is run on every page.
	 *
	 * @param WikiPage $page
	 * @return bool
	 */
	private static function shouldNoIndexForNewArticleReasons( WikiPage $page ) {
		global $wgPageTriageNoIndexUnreviewedNewArticles, $wgPageTriageMaxAge;

		if ( !$wgPageTriageNoIndexUnreviewedNewArticles ) {
			return false;
		} elseif ( !PageTriageUtil::isPageUnreviewed( $page ) ) {
			return false;
		} elseif ( !self::isNewEnoughToNoIndex( $page, $wgPageTriageMaxAge ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Calculate whether we should show NOINDEX, based on criteria related to whether the
	 * page contains a __NOINDEX__ magic word.
	 *
	 * The NOINDEX logic is explained at:
	 * https://www.mediawiki.org/wiki/Extension:PageTriage#NOINDEX
	 *
	 * @param WikiPage $page
	 * @return bool
	 */
	private static function shouldNoIndexForMagicWordReasons( WikiPage $page ) {
		global $wgPageTriageMaxNoIndexAge;

		return self::isNewEnoughToNoIndex( $page, $wgPageTriageMaxNoIndexAge );
	}

	/**
	 * Checks to see if an article is new, i.e. less than the supplied $maxAgeInDays
	 *
	 * Look in cache for the creation date. If not found, query the replica for the value
	 * of ptrp_created.
	 *
	 * @param WikiPage $wikiPage WikiPage to check
	 * @param int|null|false $maxAgeInDays How many days old an article has to be to be
	 * considered "not new".
	 * @return bool
	 */
	private static function isNewEnoughToNoIndex( WikiPage $wikiPage, $maxAgeInDays ) {
		$pageId = $wikiPage->getId();
		if ( !$pageId ) {
			return false;
		}

		// Allow disabling the age threshold for noindex by setting maxAge to null, 0, or false
		if ( !$maxAgeInDays ) {
			return true;
		}

		// Check cache for creation date
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$pageCreationDateTime = $cache->getWithSetCallback(
			$cache->makeKey( 'pagetriage-page-created', $pageId ),
			$cache::TTL_DAY,
			static function ( $oldValue, &$ttl, array &$setOpts ) use ( $pageId ) {
				// The ptrp_created field is equivalent to creation_date
				// property set during article metadata compilation.
				$dbr = PageTriageUtil::getReplicaConnection();
				$setOpts += Database::getCacheSetOptions( $dbr );
				$queueLookup = PageTriageServices::wrap( MediaWikiServices::getInstance() )
					->getQueueLookup();
				$queueRecord = $queueLookup->getByPageId( $pageId );
				return $queueRecord instanceof QueueRecord ? $queueRecord->getCreatedTimestamp() : false;
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

		// If it's younger than the maximum age, return true.
		return $articleDaysOld < $maxAgeInDays;
	}

	/** @inheritDoc */
	public function onArticleViewFooter( $article, $patrolFooterShown ) {
		// Handler for hook ArticleViewFooter, this will determine whether to load
		// curation toolbar or 'mark as reviewed'/'reviewed' text
		$wikiPage = $article->getPage();
		$title = $wikiPage->getTitle();
		$context = $article->getContext();
		$user = $context->getUser();
		$outputPage = $context->getOutput();
		$request = $context->getRequest();

		// Overwrite the noindex rule defined in Article::view(), this also affects main namespace
		if ( self::shouldShowNoIndex( $article ) ) {
			$outputPage->setRobotPolicy( 'noindex,nofollow' );
			$this->statsdDataFactory->increment(
				'extension.PageTriage.by_wiki.' . WikiMap::getCurrentWikiId() . '.noindex'
			);
		}

		// Only logged in users can review
		if ( !$user->isRegistered() ) {
			return;
		}

		// Don't show anything for user with no patrol right
		if ( !$this->permissionManager->quickUserCan( 'patrol', $user, $title ) ) {
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
		$needsReview = PageTriageUtil::isPageUnreviewed( $wikiPage );
		if ( $needsReview !== null
			&& (
				!$user->equals( $this->revisionStore->getFirstRevision( $title )->getUser( RevisionRecord::RAW ) )
				|| $this->permissionManager->userHasRight( $user, 'autopatrol' )
			)
		) {
			if ( $this->config->get( 'PageTriageEnableCurationToolbar' ) ||
				$request->getVal( 'curationtoolbar' ) === 'true' ) {
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

	/** @inheritDoc */
	public function onMarkPatrolledComplete( $rcid, $user, $wcOnlySysopsCanPatrol, $auto ) {
		// Sync records from patrol queue to triage queue
		$rc = RecentChange::newFromId( $rcid );

		if ( $rc ) {
			if ( !in_array( $rc->getTitle()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
				return;
			}

			$pt = new PageTriage( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $pt->addToPageTriageQueue( QueueRecord::REVIEW_STATUS_PATROLLED, $user, true ) ) {
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
			$title = $this->titleFactory->newFromID( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $title ) {
				PageTriageUtil::createNotificationEvent(
					$title,
					$user,
					'pagetriage-mark-as-reviewed'
				);
			}
		}
	}

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $performer, $priorBlock ) {
		// Update Article metadata when a user gets blocked.
		PageTriageUtil::updateMetadataOnBlockChange( $block, (int)$block->isSitewide() );
	}

	/** @inheritDoc */
	public function onUnblockUserComplete( $block, $performer ) {
		// Update Article metadata when a user gets unblocked.
		PageTriageUtil::updateMetadataOnBlockChange( $block, 0 );
	}

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$pageTriageDraftNamespaceId = $config->get( 'PageTriageDraftNamespaceId' );
		$vars['pageTriageNamespaces'] = PageTriageUtil::getNamespaces( $config );
		$vars['wgPageTriageDraftNamespaceId'] = $pageTriageDraftNamespaceId;
	}

	/**
	 * Generates messages for toolbar
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function toolbarContentLanguageMessages( RL\Context $context, \Config $config ) {
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

	/**
	 * Generates messages for toolbar
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function toolbarConfig( RL\Context $context, Config $config ) {
		$pageTriageCurationModules = $config->get( 'PageTriageCurationModules' );
		$pageTriageCurationDependencies = [];
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiLove' ) ) {
			$pageTriageCurationModules['wikiLove'] = [
				// depends on WikiLove extension
				'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#WikiLove',
				'namespace' => [ NS_MAIN, NS_USER ],
			];
			$pageTriageCurationDependencies[] = 'ext.wikiLove.init';
		}
		return [
			'PageTriageCurationDependencies' => $pageTriageCurationDependencies,
			'PageTriageCurationModules' => $pageTriageCurationModules,
			'PageTriageEnableCopyvio' => $config->get( 'PageTriageEnableCopyvio' ),
			'PageTriageEnableOresFilters' => $config->get( 'PageTriageEnableOresFilters' ),
			'PageTriageEnableEnglishWikipediaFeatures' =>
				$config->get( 'PageTriageEnableEnglishWikipediaFeatures' ),
			'TalkPageNoteTemplate' => $config->get( 'TalkPageNoteTemplate' ),
		];
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

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for page reviews.
		if ( !$autocreated ) {
			$this->userOptionsManager->setOption( $user, 'echo-subscriptions-email-page-review', true );
		}
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

	/** @inheritDoc */
	public function onListDefinedTags( &$tags ) {
		$tags[] = self::TAG_NAME;
	}

	/** @inheritDoc */
	public function onChangeTagsAllowedAdd( &$allowedTags, $addTags, $user ) {
		$allowedTags[] = self::TAG_NAME;
	}

	/** @inheritDoc */
	public function onChangeTagsListActive( &$tags ) {
		$tags[] = self::TAG_NAME;
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/** @inheritDoc */
	public function onApiMain__moduleManager( $moduleManager ) {
		// phpcs:enable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
		if ( !$this->config->get( 'PageTriageEnableEnglishWikipediaFeatures' ) ) {
			$moduleManager->addModule(
				'pagetriagetagging',
				'action',
				ApiDisabled::class
			);
		}
	}

	/** @inheritDoc */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		if ( $this->queueManager->isPageTriageNamespace( $page->getNamespace() ) ) {
			// TODO: Factor the user status cache into another service.
			self::flushUserStatusCache( $page );
			$this->queueManager->deleteByPageId( $pageID );
		}
	}
}
