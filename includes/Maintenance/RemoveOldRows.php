<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageServices;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * A maintenance script that updates expired page metadata
 */
class RemoveOldRows extends Maintenance {

	/**
	 * @var IDatabase|null
	 */
	protected $dbr;
	/**
	 * @var IDatabase|null
	 */
	protected $dbw;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Remove reviewed pages from pagetriage queue if they"
			. " are older then 30 days" );
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 100 );
	}

	protected function init() {
		$this->dbr = PageTriageUtil::getReplicaConnection();
		$this->dbw = PageTriageUtil::getPrimaryConnection();
	}

	public function execute() {
		$this->init();
		$this->output( "Started processing... \n" );

		$this->output( "cleanReviewedPagesAndUnusedNamespaces()... \n" );
		$this->cleanReviewedPagesAndUnusedNamespaces();

		$this->output( "cleanRedirects()... \n" );
		$this->cleanRedirects();

		$this->output( "Completed \n" );
	}

	/**
	 * Removes pages from the SQL tables pagetriage_page and pagetriage_page_tags
	 * if they meet certain criteria.
	 *
	 * Remove pages older than 30 days, if
	 * 1. the page is in the article namespace and has been reviewed, or
	 * 2. the page is not in a namespace that PageTriage patrols (not in main,
	 * user, or draft)
	 *
	 * This is to help keep the number of rows in the tables pagetriage_page and
	 * pagetriage_page_tags tables reasonable. Pages not in these tables will be
	 * treated as reviewed, and the Page Curation toolbar will not show.
	 */
	private function cleanReviewedPagesAndUnusedNamespaces() {
		global $wgPageTriageNamespaces;

		$maxAgeInDays = 30;

		// This list doesn't include Article or Draft
		// because they have special handling.
		$secondaryNamespaces = array_filter(
			$wgPageTriageNamespaces,
			static function ( $ns ) {
				return $ns !== 0;
			}
		);
		$startTime = (int)wfTimestamp( TS_UNIX ) - $maxAgeInDays * 60 * 60 * 24;

		// the page is in the article namespace and has been reviewed.
		$reviewedMainspaceWhere = $this->dbr->makeList( [
			'page_namespace' => NS_MAIN,
			'ptrp_reviewed > 0'
		], LIST_AND );
		$sqlWhere = $reviewedMainspaceWhere;
		if ( count( $secondaryNamespaces ) ) {
			$sqlWhere = $this->dbr->makeList( [
				$reviewedMainspaceWhere,
				// the page is not in main or draft namespaces
				'page_namespace' => $secondaryNamespaces,
			], LIST_OR );
		}

		$this->cleanPageTriagePageTable( $startTime, $sqlWhere );
	}

	/**
	 * Removes pages from the SQL tables pagetriage_page and pagetriage_page_tags
	 * if they meet certain criteria.
	 *
	 * Remove pages older than 180 days, if the page is a redirect. This is regardless
	 * of its patrol status.
	 *
	 * This is to help keep the number of rows in the tables pagetriage_page and
	 * pagetriage_page_tags tables reasonable. Pages not in these tables will be
	 * treated as reviewed, and the Page Curation toolbar will not show.
	 */
	private function cleanRedirects() {
		global $wgPageTriageRedirectAutoreviewAge;

		$startTime = (int)wfTimestamp( TS_UNIX ) - $wgPageTriageRedirectAutoreviewAge * 60 * 60 * 24;
		$sqlWhere = $this->dbr->makeList( [
				'page_is_redirect' => 1,
			], LIST_OR );

		$this->cleanPageTriagePageTable( $startTime, $sqlWhere );
	}

	/**
	 * Deletes data from the pagetriage_page and pagetriage_page_tags tables that
	 * is older than $startTime and that meets the criteria in $sqlWhere.
	 *
	 * @param int $startTime a UNIX timestamp of the cutoff date
	 * @param string $sqlWhere SQL to be injected into the WHERE clause of an SQL query
	 * @suppress PhanPossiblyUndeclaredVariable False positive with $row
	 */
	private function cleanPageTriagePageTable( $startTime, $sqlWhere ) {
		// Scan for data with ptrp_created set more than $startTime days ago
		$count = $this->getBatchSize();

		$idRow = $this->dbr->newSelectQueryBuilder()
			->select( [ 'max_id' => 'MAX(ptrp_page_id)' ] )
			->from( 'pagetriage_page' )
			->caller( __METHOD__ )
			->fetchRow();

		// No data to process, exit
		if ( $idRow === false ) {
			$this->output( "No data to process \n" );
			return;
		}

		$startId = $idRow->max_id + 1;
		$queueManager = PageTriageServices::wrap( MediaWikiServices::getInstance() )
			->getQueueManager();

		while ( $count === $this->getBatchSize() ) {
			$count = 0;
			$res = $this->dbr->newSelectQueryBuilder()
				->select( [ 'ptrp_page_id', 'ptrp_created', 'page_namespace', 'ptrp_reviewed' ] )
				->from( 'pagetriage_page' )
				->join( 'page', 'page', 'ptrp_page_id = page_id' )
				->where( [
					$this->dbr->buildComparison( '<', [
						'ptrp_created' => $this->dbr->timestamp( $startTime ),
						'ptrp_page_id' => $startId,
					] ),
					$sqlWhere,
				] )
				->limit( $this->getBatchSize() )
				->orderBy( [ 'ptrp_created', 'ptrp_page_id' ], SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchResultSet();

			$pageIds = [];
			foreach ( $res as $row ) {
				$pageIds[] = $row->ptrp_page_id;
				$count++;
			}

			if ( $pageIds ) {
				// update data from last row
				if ( $row->ptrp_created ) {
					$startTime = wfTimestamp( TS_UNIX, $row->ptrp_created );
				}
				$startId = (int)$row->ptrp_page_id;
				$queueManager->deleteByPageIds( $pageIds );
			}

			$this->output( "processed $count \n" );
			$this->waitForReplication();
		}
	}
}
