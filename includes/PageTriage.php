<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use PatrolLog;
use RecentChange;

/**
 * TODO: This class does too much. Refactoring into services and classes with single responsibility
 * in progress, please don't add new methods here.
 */
class PageTriage {

	/** @var int The relevant page ID. */
	protected int $mPageId;
	/** @var int Review status, valid values are QueueRecord::VALID_REVIEW_STATUSES. */
	protected int $currentReviewStatus;
	/** @var string MediaWiki-style timestamp of when the last review happened. */
	protected string $mReviewedUpdated;
	/** @var int User ID of the user that last reviewed the article. */
	protected int $mLastReviewedBy;

	/** @var bool Used for in-process caching. */
	protected bool $mLoaded;

	public const CACHE_VERSION = 2;

	/**
	 * @param int $pageId
	 */
	public function __construct( int $pageId ) {
		$this->mPageId = $pageId;
		$this->mLoaded = false;
	}

	/**
	 * Add page to page triage queue
	 * @param int $reviewStatus The reviewed status of the page, see QueueRecord::VALID_REVIEW_STATUSES
	 * @param UserIdentity|null $user
	 * @param bool $fromRc
	 * @return bool true: add new record, false: update existing record
	 * @throws MWPageTriageMissingRevisionException
	 */
	public function addToPageTriageQueue(
		int $reviewStatus = 0,
		UserIdentity $user = null,
		bool $fromRc = false
	): bool {
		if ( $this->retrieve() ) {
			if ( $this->currentReviewStatus !== $reviewStatus ) {
				$this->setTriageStatus( $reviewStatus, $user, $fromRc );
			}
			return false;
		}

		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );

		// Pull page creation date from database
		// must select from master here since the page has just been created, and probably
		// hasn't propagated to the replicas yet.
		$res = $dbw->selectRow(
			'revision',
			[ 'MIN(rev_timestamp) AS creation_date', 'MAX(rev_timestamp) AS last_edit_date' ],
			[ 'rev_page' => $this->mPageId ],
			__METHOD__
		);

		if ( !$res ) {
			throw new MWPageTriageMissingRevisionException( 'Page missing revision!' );
		}

		$row = [
			'ptrp_page_id' => $this->mPageId,
			'ptrp_reviewed' => $reviewStatus,
			'ptrp_created' => $res->creation_date,
			'ptrp_reviewed_updated' => $res->last_edit_date
		];

		$row['ptrp_last_reviewed_by'] = $user ? $user->getId() : 0;

		$this->mReviewedUpdated = $row['ptrp_reviewed_updated'];
		$this->mLastReviewedBy  = $row['ptrp_last_reviewed_by'];

		$dbw->insert( 'pagetriage_page', $row, __METHOD__, [ 'IGNORE' ] );

		$this->currentReviewStatus = $reviewStatus;

		if ( $this->mLastReviewedBy ) {
			$this->logUserTriageAction();
		}

		return true;
	}

	/**
	 * Set the review status of an article in the PageTriage queue.
	 *
	 * TODO: Move this code into QueueManager::setStatusForPageId().
	 *
	 * @param int $newReviewStatus see QueueRecord::VALID_REVIEW_STATUSES
	 * @param UserIdentity|null $user
	 * @param bool $fromRc
	 * @return bool If a page status was updated
	 */
	public function setTriageStatus( int $newReviewStatus = 0, UserIdentity $user = null, bool $fromRc = false ): bool {
		if ( !in_array( $newReviewStatus, QueueRecord::VALID_REVIEW_STATUSES ) ) {
			// TODO: Should log an error here, or maybe just not accept invalid review status to begin with.
			$newReviewStatus = QueueRecord::REVIEW_STATUS_UNREVIEWED;
		}

		if ( !$this->retrieve() ) {
			// Page doesn't exist in pagetriage_page
			return false;
		}
		if ( $this->currentReviewStatus === $newReviewStatus ) {
			// Status doesn't change
			return false;
		}
		if ( $this->currentReviewStatus === QueueRecord::REVIEW_STATUS_AUTOPATROLLED &&
			$newReviewStatus !== QueueRecord::REVIEW_STATUS_UNREVIEWED ) {
			// Only unreviewing is allowed for autopatrolled articles
			return false;
		}

		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$set = [
			'ptrp_reviewed' => $newReviewStatus,
			'ptrp_reviewed_updated' => $dbw->timestamp( wfTimestampNow() ),
			'ptrp_last_reviewed_by' => $user ? $user->getId() : 0
		];
		$dbw->update(
			'pagetriage_page',
			$set,
			[
				'ptrp_page_id' => $this->mPageId,
				'ptrp_reviewed != ' . $dbw->addQuotes( $newReviewStatus )
			],
			__METHOD__
		);
		if ( $dbw->affectedRows() > 0 ) {
			$this->currentReviewStatus = $newReviewStatus;
			$this->mReviewedUpdated = $set['ptrp_reviewed_updated'];
			$this->mLastReviewedBy = $set['ptrp_last_reviewed_by'];
			// @Todo - case for marking a page as untriaged and make sure this logic is correct
			if ( !$fromRc && $newReviewStatus && $user ) {
				$rc = RecentChange::newFromConds( [
					'rc_cur_id' => $this->mPageId,
					'rc_new' => '1'
				], __METHOD__ );
				if ( $rc && !$rc->getAttribute( 'rc_patrolled' ) ) {
					$rc->reallyMarkPatrolled();
					PatrolLog::record( $rc, false, $user, 'pagetriage' );
				}
			}
			// Log it if set by user
			if ( $this->mLastReviewedBy ) {
				$this->logUserTriageAction();
			}
		}
		$dbw->endAtomic( __METHOD__ );

		$articleMetadata = new ArticleMetadata( [ $this->mPageId ] );
		$metadataArray = $articleMetadata->getMetadata();

		if ( array_key_exists( $this->mPageId, $metadataArray ) ) {
			$articleMetadata->flushMetadataFromCache( $this->mPageId );
		}
		return true;
	}

	/**
	 * Update the database record
	 * @param array $row key => value pair to be updated
	 * Todo: ptrpt_reviewed should not updated from this function, add exception to catch this
	 *       or find a better solution
	 */
	public function update( $row ) {
		if ( !$row ) {
			return;
		}

		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );
		$dbw->update(
			'pagetriage_page',
			$row,
			[ 'ptrp_page_id' => $this->mPageId ],
			__METHOD__
		);
	}

	/**
	 * Load a page triage record
	 * @return bool
	 */
	public function retrieve() {
		if ( $this->mLoaded ) {
			return true;
		}

		$pageTriageServices = PageTriageServices::wrap( MediaWikiServices::getInstance() );
		$queueLookup = $pageTriageServices->getQueueLookup();
		$queueRecord = $queueLookup->getByPageId( $this->mPageId );
		if ( !$queueRecord ) {
			return false;
		}

		$this->currentReviewStatus = $queueRecord->getReviewedStatus();
		$this->mReviewedUpdated = wfTimestamp( TS_UNIX, $queueRecord->getReviewedUpdatedTimestamp() );
		$this->mLastReviewedBy = $queueRecord->getLastReviewedByUserId();
		$this->mLoaded = true;
		return true;
	}

	/**
	 * Log the user triage action
	 */
	protected function logUserTriageAction() {
		if ( !$this->mLastReviewedBy ) {
			return;
		}

		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );

		$row = [
			'ptrl_page_id' => $this->mPageId,
			'ptrl_user_id' => $this->mLastReviewedBy,
			'ptrl_reviewed' => $this->currentReviewStatus,
			'ptrl_timestamp' => $this->mReviewedUpdated
		];

		$dbw->insert( 'pagetriage_log', $row, __METHOD__ );
	}

	/**
	 * Set the tags updated timestamp
	 * @param array $pageIds
	 * @return string
	 */
	public static function bulkSetTagsUpdated( $pageIds ) {
		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );

		$now = wfTimestampNow();
		$dbw->update(
			'pagetriage_page',
			[ 'ptrp_tags_updated' => $dbw->timestamp( $now ) ],
			[ 'ptrp_page_id' => $pageIds ],
			__METHOD__
		);

		return $now;
	}
}
