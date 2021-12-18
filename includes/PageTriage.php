<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\User\UserIdentity;
use PatrolLog;
use RecentChange;

class PageTriage {

	/** @var int */
	protected $mPageId;
	/** @var string Review status as a string between "0" and "3" */
	protected $mReviewed;
	/** @var string */
	protected $mCreated;
	/** @var string Deletion flag as a string "0" or "1" */
	protected $mDeleted;
	/** @var string */
	protected $mTagsUpdated;
	/** @var string */
	protected $mReviewedUpdated;
	/** @var int */
	protected $mLastReviewedBy;

	/** @var bool */
	protected $mLoaded;

	/**
	 * @var ArticleMetadata
	 */
	protected $mArticleMetadata;

	/**
	 * @param int $pageId
	 */
	public function __construct( $pageId ) {
		$this->mPageId = (int)$pageId;
		$this->mLoaded = false;
	}

	/**
	 * Add page to page triage queue
	 * @param string $reviewed The reviewed status of the page...
	 *    '0': unreviewed
	 *    '1': reviewed manually
	 *    '2': patrolled from Special:NewPages
	 *    '3': auto-patrolled
	 * @param UserIdentity|null $user
	 * @param bool $fromRc
	 * @throws MWPageTriageMissingRevisionException
	 * @return bool true: add new record, false: update existing record
	 */
	public function addToPageTriageQueue( $reviewed = '0', UserIdentity $user = null, $fromRc = false ) {
		if ( $this->retrieve() ) {
			if ( $this->mReviewed != $reviewed ) {
				$this->setTriageStatus( $reviewed, $user, $fromRc );
			}
			return false;
		}

		$dbw = wfGetDB( DB_PRIMARY );

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
			'ptrp_reviewed' => $reviewed,
			'ptrp_created' => $res->creation_date,
			'ptrp_reviewed_updated' => $res->last_edit_date
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
	 * @param string $reviewed see PageTriage::getValidReviewedStatus()
	 * @param UserIdentity|null $user
	 * @param bool $fromRc
	 * @return bool If a page status was updated
	 */
	public function setTriageStatus( $reviewed, UserIdentity $user = null, $fromRc = false ) {
		if ( !array_key_exists( $reviewed, self::getValidReviewedStatus() ) ) {
			$reviewed = '0';
		}

		if ( !$this->retrieve() ) {
			return false; // Page doesn't exist in pagetriage_page
		}
		if ( $this->mReviewed == $reviewed ) {
			return false; // Status doesn't change
		}

		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$set = [
			'ptrp_reviewed' => $reviewed,
			'ptrp_reviewed_updated' => $dbw->timestamp( wfTimestampNow() ),
			'ptrp_last_reviewed_by' => $user ? $user->getId() : 0
		];
		$dbw->update(
			'pagetriage_page',
			$set,
			[
				'ptrp_page_id' => $this->mPageId,
				'ptrp_reviewed != ' . $dbw->addQuotes( $reviewed )
			],
			__METHOD__
		);
		if ( $dbw->affectedRows() > 0 ) {
			$this->mReviewed = $reviewed;
			$this->mReviewedUpdated = $set['ptrp_reviewed_updated'];
			$this->mLastReviewedBy = $set['ptrp_last_reviewed_by'];
			// @Todo - case for marking a page as untriaged and make sure this logic is correct
			if ( !$fromRc && $reviewed && $user ) {
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

		$dbw = wfGetDB( DB_PRIMARY );
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

		$dbr = wfGetDB( DB_REPLICA );

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

		$dbw = wfGetDB( DB_PRIMARY );

		$row = [
			'ptrl_page_id' => $this->mPageId,
			'ptrl_user_id' => $this->mLastReviewedBy,
			'ptrl_reviewed' => $this->mReviewed,
			'ptrl_timestamp' => $this->mReviewedUpdated
		];

		$dbw->insert( 'pagetriage_log', $row, __METHOD__ );
	}

	protected function loadArticleMetadata() {
		if ( !$this->mArticleMetadata ) {
			$this->mArticleMetadata = new ArticleMetadata( [ $this->mPageId ] );
		}
	}

	/**
	 * Delete the page from page triage queue
	 */
	public function deleteFromPageTriage() {
		$dbw = wfGetDB( DB_PRIMARY );

		$this->loadArticleMetadata();

		$dbw->startAtomic( __METHOD__ );

		$dbw->delete(
				'pagetriage_page',
				[ 'ptrp_page_id' => $this->mPageId ],
				__METHOD__
		);
		$this->mArticleMetadata->deleteMetadata();

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Set the tags updated timestamp
	 * @param array $pageIds
	 * @return string
	 */
	public static function bulkSetTagsUpdated( $pageIds ) {
		$dbw = wfGetDB( DB_PRIMARY );

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
