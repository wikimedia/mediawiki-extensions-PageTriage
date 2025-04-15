<?php

namespace MediaWiki\Extension\PageTriage;

use Article;
use ManualLogEntry;
use MediaWiki\Api\ApiDisabled;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsAllowedAddHook;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Notifications\Model\Event;
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
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Hook\ArticleViewFooterHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use RecentChange;
use Wikimedia\Rdbms\Database;
use Wikimedia\Stats\StatsFactory;
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
	LocalUserCreatedHook,
	PageUndeleteCompleteHook
{

	private const TAG_NAME = 'pagetriage';

	/** @var Config */
	private Config $config;
	/** @var QueueManager */
	private QueueManager $queueManager;

	/** @var RevisionLookup */
	private RevisionLookup $revisionLookup;

	/** @var PermissionManager */
	private PermissionManager $permissionManager;

	/** @var RevisionStore */
	private RevisionStore $revisionStore;

	/** @var StatsFactory */
	private StatsFactory $statsFactory;

	/** @var TitleFactory */
	private TitleFactory $titleFactory;

	/** @var UserOptionsManager */
	private UserOptionsManager $userOptionsManager;

	/** @var WikiPageFactory */
	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param Config $config
	 * @param RevisionLookup $revisionLookup
	 * @param StatsFactory $statsFactory
	 * @param PermissionManager $permissionManager
	 * @param RevisionStore $revisionStore
	 * @param TitleFactory $titleFactory
	 * @param UserOptionsManager $userOptionsManager
	 * @param QueueManager $queueManager
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		Config $config,
		RevisionLookup $revisionLookup,
		StatsFactory $statsFactory,
		PermissionManager $permissionManager,
		RevisionStore $revisionStore,
		TitleFactory $titleFactory,
		UserOptionsManager $userOptionsManager,
		QueueManager $queueManager,
		WikiPageFactory $wikiPageFactory
	) {
		$this->config = $config;
		$this->revisionLookup = $revisionLookup;
		$this->statsFactory = $statsFactory->withComponent( 'PageTriage' );
		$this->permissionManager = $permissionManager;
		$this->revisionStore = $revisionStore;
		$this->titleFactory = $titleFactory;
		$this->userOptionsManager = $userOptionsManager;
		$this->queueManager = $queueManager;
		$this->wikiPageFactory = $wikiPageFactory;
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

		$draftNsId = $this->config->get( 'PageTriageDraftNamespaceId' );

		// If the page is in a namespace we don't care about, abort
		if ( !in_array( $newNamespace, [ NS_MAIN, $draftNsId ], true ) ) {
			return;
		}

		// Else if the page is moved around in the same namespace we only care about updating
		// the recreated attribute
		if ( $oldNamespace === $newNamespace ) {
			// Check if the page currently exists in the feed
			$pageTriage = new PageTriage( $oldid );
			if ( $pageTriage->retrieve() ) {
				DeferredUpdates::addCallableUpdate( static function () use ( $oldid ) {
					$acp = ArticleCompileProcessor::newFromPageId(
						[ $oldid ],
						false
					);

					if ( $acp ) {
						$acp->registerComponent( 'Recreated' );
						$acp->compileMetadata();
					}
				} );
			}
			return;
		}

		// else it was moved from one namespace to another, we might need a full recompile
		$newAdditionToFeed = self::addToPageTriageQueue( $oldid, $newTitle, $user );

		// The page was already in the feed so a recompile is not needed
		if ( !$newAdditionToFeed ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( static function () use ( $oldid, $newAdditionToFeed ) {
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $oldid ],
				false
			);
			if ( $acp ) {
				// Since this is a title move, the only component requiring DB_PRIMARY will be
				// BasicData.
				$acp->configComponentDb(
					ArticleCompileProcessor::getSafeComponentDbConfigForCompilation()
				);
				$acp->compileMetadata();
			}
		} );
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
				if ( !$prevRevRecord ) {
					return;
				}

				$wasRedirectBecameArticle = !$wikiPage->isRedirect() &&
					$prevRevRecord->getContent( SlotRecord::MAIN )->isRedirect();
				$wasArticleBecameRedirect = $wikiPage->isRedirect() &&
					!$prevRevRecord->getContent( SlotRecord::MAIN )->isRedirect();
				if ( $wasRedirectBecameArticle || $wasArticleBecameRedirect ) {
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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// Get draft information.
		$draftNsId = $config->get( 'PageTriageDraftNamespaceId' );
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
			$useRCPatrol = $config->get( 'UseRCPatrol' );
			$useNPPatrol = $config->get( 'UseNPPatrol' );
			$isAutopatrolled = ( $useRCPatrol || $useNPPatrol ) &&
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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( !$config->get( 'PageTriageNoIndexUnreviewedNewArticles' ) ) {
			return false;
		} elseif ( !PageTriageUtil::isPageUnreviewed( $page ) ) {
			return false;
		} elseif ( !self::isNewEnoughToNoIndex( $page, $config->get( 'PageTriageMaxAge' ) ) ) {
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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		return self::isNewEnoughToNoIndex( $page, $config->get( 'PageTriageMaxNoIndexAge' ) );
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
		// Handler for hook ArticleViewFooter. This will...
		//   1) determine whether to turn on noindex for new, unreviewed articles,
		//   2) determine whether to load a link for autopatrolled users to unpatrol their article,
		//   3) determine whether to load the Page Curation toolbar, and/or
		//   4) determine whether to load the "Add to New Pages Feed" link

		$wikiPage = $article->getPage();
		$title = $wikiPage->getTitle();
		$context = $article->getContext();
		$user = $context->getUser();
		$outputPage = $context->getOutput();
		$request = $context->getRequest();

		// 1) Determine whether to turn on noindex for new, unreviewed articles.
		// Overwrite the noindex rule defined in Article::view(), this also affects main namespace
		if ( self::shouldShowNoIndex( $article ) ) {
			$outputPage->setRobotPolicy( 'noindex,nofollow' );
			$this->statsFactory->getCounter( 'noindex_total' )
				->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
				->copyToStatsdAt( 'extension.PageTriage.by_wiki.' . WikiMap::getCurrentWikiId() . '.noindex' )
				->increment();
		}

		// onArticleViewFooter() is run every time any article is not viewed from cache, so exit
		// early if we can, to increase performance.
		// Only named users can review
		if ( !$user->isNamed() ) {
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

		// 2) determine whether to load a link for autopatrolled users to unpatrol their article
		$userCanPatrol = $this->permissionManager->quickUserCan( 'patrol', $user, $title );
		$userCanAutoPatrol = $this->permissionManager->userHasRight( $user, 'autopatrol' );
		$outputPage->addJsConfigVars( [
			'wgPageTriageUserCanPatrol' => $userCanPatrol,
			'wgPageTriageUserCanAutoPatrol' => $userCanAutoPatrol
		] );
		if ( !$userCanPatrol ) {
			$this->maybeShowUnpatrolLink( $wikiPage, $user, $outputPage );
			return;
		}

		// 3) determine whether to load the Page Curation toolbar.
		// 4) determine whether to load the "Add to New Pages Feed" link.
		// See if the page is in the PageTriage page queue
		// If it isn't, $needsReview will be null
		// Also, users without the autopatrol right can't review their own pages
		$needsReview = PageTriageUtil::isPageUnreviewed( $wikiPage );
		if ( $needsReview !== null
			&& (
				!$user->equals( $this->revisionStore->getFirstRevision( $title )->getUser( RevisionRecord::RAW ) )
				|| $userCanAutoPatrol
			)
		) {
			if ( $this->config->get( 'PageTriageEnableCurationToolbar' ) ||
				$request->getVal( 'curationtoolbar' ) === 'true' ) {
				// Load the JavaScript for the curation toolbar
				$outputPage->addModules( 'ext.pageTriage.toolbarStartup' );
				$outputPage->addModuleStyles( [ 'mediawiki.interface.helpers.styles' ] );
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
				$outputPage->addModules( [ 'ext.pageTriage.articleLink' ] );
				$html = Html::rawElement( 'div', [ 'class' => 'mw-pagetriage-markpatrolled' ], $msg );
				$outputPage->addHTML( $html );
			}
		} elseif ( $needsReview === null && !$title->isMainPage() ) {
			// Page is potentially usable, but not in the queue, allow users to add it manually
			// Option is not shown if the article is the main page
			$outputPage->addModules( 'ext.pageTriage.sidebarLink' );
		}
	}

	/**
	 * Show a link to autopatrolled users without the 'patrol'
	 * userright that allows them to unreview a specific page iff
	 * the page is autopatrolled && they are the page's creator
	 *
	 * @param WikiPage $wikiPage Wikipage being viewed
	 * @param User $user Current user
	 * @param OutputPage $out Output of current page
	 */
	private function maybeShowUnpatrolLink( WikiPage $wikiPage, User $user, OutputPage $out ): void {
		$reviewStatus = PageTriageUtil::getStatus( $wikiPage );
		$articleIsNotAutoPatrolled = $reviewStatus !== QueueRecord::REVIEW_STATUS_AUTOPATROLLED;
		if ( $articleIsNotAutoPatrolled ) {
			return;
		}

		$isAutopatrolled = $this->permissionManager->userHasRight( $user, 'autopatrol' );

		if ( !$isAutopatrolled ) {
			return;
		}

		$pageCreator = $this->revisionStore->getFirstRevision( $wikiPage )->getUser( RevisionRecord::RAW );
		$isPageCreator = $pageCreator->equals( $user );

		if ( $isPageCreator ) {
			$out->addModules( 'ext.pageTriage.sidebarLink' );
		}
	}

	/** @inheritDoc */
	public function onMarkPatrolledComplete( $rcid, $user, $wcOnlySysopsCanPatrol, $auto ) {
		// Sync records from patrol queue to triage queue
		$rc = RecentChange::newFromId( $rcid );
		if ( !$rc ) {
			return;
		}

		// Run for PageTriage namespaces and for draftspace
		if ( !in_array( $rc->getPage()->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
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

		// Only notify for PageTriage namespaces, not for draftspace
		$title = $this->titleFactory->newFromID( $rc->getAttribute( 'rc_cur_id' ) );
		$isInPageTriageNamespaces = in_array(
			$title->getNamespace(),
			$this->config->get( 'PageTriageNamespaces' )
		);
		if ( $title && $isInPageTriageNamespaces ) {
			PageTriageUtil::createNotificationEvent(
				$title,
				$user,
				'pagetriage-mark-as-reviewed'
			);
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
	 * @param Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function toolbarContentLanguageMessages( Context $context, Config $config ) {
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
	 * @param Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function toolbarConfig( Context $context, Config $config ) {
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
			'PageTriageEnableExtendedFeatures' =>
				$config->get( 'PageTriageEnableExtendedFeatures' ),
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
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$enabledEchoEvents = $config->get( 'PageTriageEnabledEchoEvents' );

		if ( $enabledEchoEvents ) {
			$notificationCategories['page-review'] = [
				'priority' => 8,
				'tooltip' => 'echo-pref-tooltip-page-review',
			];
		}

		if ( in_array( 'pagetriage-mark-as-reviewed', $enabledEchoEvents ) ) {
			$notifications['pagetriage-mark-as-reviewed'] = [
				'presentation-model' => PageTriageMarkAsReviewedPresentationModel::class,
				'category' => 'page-review',
				'group' => 'neutral',
				'section' => 'message',
				'user-locators' => [ [ self::class . '::locateUsersForNotification' ] ],
			];
		}
		if ( in_array( 'pagetriage-add-maintenance-tag', $enabledEchoEvents ) ) {
			$notifications['pagetriage-add-maintenance-tag'] = [
				'presentation-model' => PageTriageAddMaintenanceTagPresentationModel::class,
				'category' => 'page-review',
				'group' => 'neutral',
				'section' => 'alert',
				'user-locators' => [ [ self::class . '::locateUsersForNotification' ] ],
			];
		}
		if ( in_array( 'pagetriage-add-deletion-tag', $enabledEchoEvents ) ) {
			$notifications['pagetriage-add-deletion-tag'] = [
				'presentation-model' => PageTriageAddDeletionTagPresentationModel::class,
				'category' => 'page-review',
				'group' => 'negative',
				'section' => 'alert',
				'user-locators' => [ [ self::class . '::locateUsersForNotification' ] ],
			];
			$icons['trash'] = [
				'path' => 'PageTriage/echo-icons/trash.svg'
			];
		}

		return true;
	}

	/**
	 * For locating users to be notifies of an Echo Event.
	 * @param Event $event
	 * @return array
	 */
	public static function locateUsersForNotification( Event $event ) {
		if ( !$event->getTitle() ) {
			return [];
		}

		$pageId = $event->getTitle()->getArticleID();

		$articleMetadata = new ArticleMetadata( [ $pageId ], false, DB_REPLICA );
		$metaData = $articleMetadata->getMetadata();

		if ( !$metaData ) {
			return [];
		}

		$users = [];
		if ( $metaData[$pageId]['user_id'] ) {
			$users[$metaData[$pageId]['user_id']] = User::newFromId( $metaData[$pageId]['user_id'] );
		}
		return $users;
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
			[ (int)$rc->getPage()->getDBkey() ], DB_REPLICA
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
		if ( !$this->config->get( 'PageTriageEnableExtendedFeatures' ) ) {
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

	/** @inheritDoc */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		if ( !$created ) {
			// not interested in revdel actions
			return;
		}

		if ( !in_array( $page->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			// don't queue pages in namespaces where PageTriage is disabled
			return;
		}

		$wikiPage = $this->wikiPageFactory->newFromTitle( $page );
		self::addToPageTriageQueue( $wikiPage->getId(), $wikiPage->getTitle() );
	}
}
