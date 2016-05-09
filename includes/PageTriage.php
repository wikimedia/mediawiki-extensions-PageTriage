<?php

class PageTriage {

	// database property
	protected $mPageId;
	protected $mReviewed;
	protected $mCreated;
	protected $mDeleted;
	protected $mTagsUpdated;
	protected $mReviewedUpdated;
	protected $mLastReviewedBy;

	// additional property
	protected $mLoaded;

	/**
	 * @var ArticleMetadata
	 */
	protected $mArticleMetadata;

	/**
	 * @param $pageId int
	 */
	public function __construct( $pageId ) {
		$this->mPageId = (int)$pageId;
		$this->mLoaded = false;
	}

	/**
	 * Add page to page triage queue
	 * @param $reviewed string The reviewed status of the page...
	 *    '0': unreviewed
	 *    '1': reviewed manually
	 *    '2': patrolled from Special:NewPages
	 *    '3': auto-patrolled
	 * @param $user User
	 * @param $fromRc bool
	 * @throws MWPageTriageMissingRevisionException
	 * @return bool - true: add new record, false: update existing record
	 */
	public function addToPageTriageQueue( $reviewed = '0', User $user = null, $fromRc = false ) {
		if ( $this->retrieve() ) {
			if ( $this->mReviewed != $reviewed ) {
				$this->setTriageStatus( $reviewed, $user, $fromRc );
			}
			return false;
		}

		$dbw = wfGetDB( DB_MASTER );

		// Pull page creation date from database
		// must select from master here since the page has just been created, and probably
		// hasn't propagated to the slaves yet.
		$res = $dbw->selectRow(
			'revision',
			'MIN(rev_timestamp) AS creation_date',
			[ 'rev_page' => $this->mPageId ],
			__METHOD__
		);

		if ( !$res ) {
			throw new MWPageTriageMissingRevisionException( 'Page missing revision!' );
		}

		$row = [
			'ptrp_page_id' => $this->mPageId,
			'ptrp_reviewed' => $reviewed,
			'ptrp_created' => $res->creation_date,
			'ptrp_reviewed_updated' => $dbw->timestamp( wfTimestampNow() )
		];

		$row['ptrp_last_reviewed_by'] = $user ? $user->getId() : 0;

		$this->mReviewedUpdated = $row['ptrp_reviewed_updated'];
		$this->mLastReviewedBy  = $row['ptrp_last_reviewed_by'];

		$dbw->insert( 'pagetriage_page', $row, __METHOD__, [ 'IGNORE' ] );

		$this->mReviewed = $reviewed;

		if ( $this->mLastReviewedBy ) {
			$this->logUserTriageAction();
		}

		return true;
	}

	/**
	 * set the triage status of an article in pagetriage queue
	 * @param $reviewed string - see PageTriage::getValidReviewedStatus()
	 * @param $user User
	 * @param $fromRc bool
	 */
	public function setTriageStatus( $reviewed, User $user = null, $fromRc = false ) {

		if ( !array_key_exists( $reviewed, self::getValidReviewedStatus() ) ) {
			$reviewed = '0';
		}

		if ( !$this->retrieve() || $this->mReviewed == $reviewed ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );

		$row = [
				'ptrp_reviewed' => $reviewed,
				'ptrp_reviewed_updated' => $dbw->timestamp( wfTimestampNow() )
		];
		$row['ptrp_last_reviewed_by'] = $user ? $user->getId() : 0;

		$this->mReviewed = $reviewed;
		$this->mReviewedUpdated = $row['ptrp_reviewed_updated'];
		$this->mLastReviewedBy  = $row['ptrp_last_reviewed_by'];

		$dbw->startAtomic( __METHOD__ );
		// @Todo - case for marking a page as untriaged and make sure this logic is correct
		if ( !$fromRc && $this->mReviewed && !is_null( $user ) ) {
			$rc = RecentChange::newFromConds( [ 'rc_cur_id' => $this->mPageId, 'rc_new' => '1' ] );
			if ( $rc && !$rc->getAttribute( 'rc_patrolled' ) ) {
				$rc->reallyMarkPatrolled();
				PatrolLog::record( $rc, false, $user );
			}
		}

		$dbw->update( 'pagetriage_page', $row, [ 'ptrp_page_id' => $this->mPageId ], __METHOD__ );
		// Log it if set by user
		if ( $dbw->affectedRows() > 0 && $this->mLastReviewedBy ) {
			$this->logUserTriageAction();
		}
		$dbw->endAtomic( __METHOD__ );

		$articleMetadata = new ArticleMetadata( [ $this->mPageId ] );
		$metadataArray = $articleMetadata->getMetadata();

		if ( array_key_exists( $this->mPageId, $metadataArray ) ) {
			$articleMetadata->flushMetadataFromCache( $this->mPageId );
		}
	}

	/**
	 * Update the database record
	 * @param $row array key => value pair to be updated
	 * Todo: ptrpt_reviewed should not updated from this function, add exception to catch this
	 *       or find a better solution
		 */
	public function update( $row ) {
		if ( !$row ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
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

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->selectRow(
			[ 'pagetriage_page' ],
			[
				'ptrp_reviewed',
				'ptrp_created',
				'ptrp_deleted',
				'ptrp_tags_updated',
				'ptrp_reviewed_updated',
				'ptrp_last_reviewed_by'
			],
			[ 'ptrp_page_id' => $this->mPageId ],
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}

		$this->mReviewed = $res->ptrp_reviewed;
		$this->mCreated = $res->ptrp_created;
		$this->mDeleted = $res->ptrp_deleted;
		$this->mTagsUpdated = wfTimestamp( TS_UNIX, $res->ptrp_tags_updated );
		$this->mReviewedUpdated = wfTimestamp( TS_UNIX, $res->ptrp_reviewed_updated );
		$this->mLastReviewedBy = $res->ptrp_last_reviewed_by;
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

		$dbw = wfGetDB( DB_MASTER );

		$row = [
			'ptrl_page_id' => $this->mPageId,
			'ptrl_user_id' => $this->mLastReviewedBy,
			'ptrl_reviewed' => $this->mReviewed,
			'ptrl_timestamp' => $this->mReviewedUpdated
		];

		$row['ptrl_id'] = $dbw->nextSequenceValue( 'pagetriage_log_ptrl_id' );
		$dbw->insert( 'pagetriage_log', $row, __METHOD__ );
	}

	protected function loadArticleMetadata() {
		if ( !$this->mArticleMetadata ) {
			$this->mArticleMetadata = new ArticleMetadata( [ $this->mPageId ] );
		}
	}

	/**
	 * Delete the page from page triage queue and log
	 */
	public function deleteFromPageTriage() {
		$dbw = wfGetDB( DB_MASTER );

		$this->loadArticleMetadata();

		$dbw->startAtomic( __METHOD__ );

		$dbw->delete(
				'pagetriage_page',
				[ 'ptrp_page_id' => $this->mPageId ],
				__METHOD__,
				[]
		);
		$dbw->delete(
				'pagetriage_log',
				[ 'ptrl_page_id' => $this->mPageId ],
				__METHOD__,
				[]
		);
		$this->mArticleMetadata->deleteMetadata();

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Set the tags updated timestamp
	 * @param $pageIds array
	 * @return string
	 */
	public static function bulkSetTagsUpdated( $pageIds ) {
		$dbw = wfGetDB( DB_MASTER );

		$now = wfTimestampNow();
		$dbw->update(
			'pagetriage_page',
			[ 'ptrp_tags_updated' => $dbw->timestamp( $now ) ],
			[ 'ptrp_page_id' => $pageIds ],
			__METHOD__
		);

		return $now;
	}

	/**
	 * Get a list of valid reviewed status
	 * @return array
	 */
	public static function getValidReviewedStatus() {
		return [
			'0' => 'unreviewed',
			'1' => 'reviewed',
			'2' => 'patrolled',
			'3' => 'auto-patrolled'
		];
	}
}

class MWPageTriageMissingRevisionException extends Exception {
}
