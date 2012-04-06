<?php

class PageTriage {

	// database property
	protected $mPageId;
	protected $mReviewed;
	protected $mTimestamp;

	// additional property
	protected $mMetadata;
	protected $mLoaded;

	/**
	 * @param $pageId int
	 */
	public function __construct( $pageId ) {
		$this->mPageId = intval( $pageId );
		$this->mLoaded = false;
	}

	/**
	 * Add page to page triage queue
	 * @return bool
	 */
	public function addToPageTriageQueue() {
		if ( $this->retrieve() ) {
			if ( $this->mReviewed ) {
				$this->setTriageStatus( '0' );
			}
			return true;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$dbw = wfGetDB( DB_MASTER );

		// Pull page creation date from database
		$res = $dbr->selectRow(
			'revision',
			'MIN(rev_timestamp) AS creation_date',
			array( 'rev_page' => $this->mPageId ),
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}

		$row = array(
			'ptrp_page_id' => $this->mPageId,
			'ptrp_reviewed' => '0',
			'ptrp_timestamp' => $res->creation_date
		);
		
		$dbw->insert( 'pagetriage_page', $row, __METHOD__, array( 'IGNORE' ) );

		return true;
	}
	
	/**
	 * set the triage status of an article in pagetriage queue
	 * @param $reviewed string - '1'/'0'
	 * @param $user User
	 * @param $fromRc bool
	 */
	public function setTriageStatus( $reviewed, User $user = null, $fromRc = false ) {

		if ( !in_array( $reviewed, array( '1', '0') ) ) {
			$reviewed = '0';
		}
		
		if ( !$this->retrieve() || $this->mReviewed == $reviewed ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );

		$row = array( 'ptrp_reviewed' => $reviewed );
		$this->mReviewed = $reviewed;

		$dbw->begin();
		//@Todo - case for marking a page as untriaged and make sure this logic is correct
		if ( !$fromRc && $this->mReviewed && !is_null( $user ) ) {
			$rc = RecentChange::newFromConds( array( 'rc_cur_id' => $this->mPageId, 'rc_new' => '1' ) );
			if ( $rc && !$rc->getAttribute('rc_patrolled') ) {
				$rc->reallyMarkPatrolled();
				PatrolLog::record( $rc, false, $user );
			}	
		}

		$dbw->update( 'pagetriage_page', $row, array( 'ptrp_page_id' => $this->mPageId ), __METHOD__ );
		// Log it if set by user
		if ( $dbw->affectedRows() > 0 && !is_null( $user ) && !$user->isAnon() ) {
			$this->logUserTriageAction( $user );
		}
		$dbw->commit();	
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
			array( 'pagetriage_page' ),
			array( 'ptrp_reviewed', 'ptrp_timestamp' ),
			array( 'ptrp_page_id' => $this->mPageId ),
			__METHOD__
		);

		if ( !$res ) {
			return false;
		}

		$this->mReviewed = $res->ptrp_reviewed;
		$this->mTimestamp = $res->ptrp_timestamp;
		$this->mLoaded = true;
		return true;
	}
	
	public function retrieveTriageLog() {
		// get the triage log	
	}
	
	public function loadMetadata() {
		$metaData = new ArticleMetadata( array( $this->mPageId ) );
		$this->mMetadata = $metaData->getMetadata();	
	}
	
	/**
	 * Get the metadata for this page
	 * @return array
	 */
	public function getMetadata() {
		if ( is_null( $this->mMetadata ) ) {
			$this->loadMetadata();
		}

		return $this->mMetadata;
	}
	
	/**
	 * Log the user triage action
	 * @param $user User
	 */
	protected function logUserTriageAction( $user ) {
		$dbw = wfGetDB( DB_MASTER );

		$row = array(
			'ptrl_page_id' => $this->mPageId,
			'ptrl_user_id' => $user->getID(),
			'ptrl_reviewed' => $this->mReviewed,
			'ptrl_timestamp' => $dbw->timestamp( wfTimestampNow() )
		);
		
		$row['ptrl_id'] = $dbw->nextSequenceValue( 'pagetriage_log_ptrl_id' );
		$dbw->insert( 'pagetriage_log', $row, __METHOD__ );
	}
	
}
