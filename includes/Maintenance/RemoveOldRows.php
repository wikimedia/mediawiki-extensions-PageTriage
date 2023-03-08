<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageServices;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

/**
 * A maintenance script that updates expired page metadata
 */
class RemoveOldRows extends Maintenance {

	/**
	 * @var \Wikimedia\Rdbms\IDatabase|null
	 */
	protected $dbr;
	/**
	 * @var \Wikimedia\Rdbms\IDatabase|null
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
		$this->dbr = PageTriageUtil::getConnection( DB_REPLICA );
		$this->dbw = PageTriageUtil::getConnection( DB_PRIMARY );
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

		$idRow = $this->dbr->selectRow(
			[ 'pagetriage_page' ],
			[ 'MAX(ptrp_page_id) AS max_id' ],
			[],
			__METHOD__
		);

		// No data to process, exit
		if ( $idRow === false ) {
			$this->output( "No data to process \n" );
			return;
		}

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$startId = $idRow->max_id + 1;
		$queueManager = PageTriageServices::wrap( MediaWikiServices::getInstance() )
			->getQueueManager();

		while ( $count === $this->getBatchSize() ) {
			$count = 0;
			$res = $this->dbr->select(
				[ 'pagetriage_page', 'page' ],
				[ 'ptrp_page_id', 'ptrp_created', 'page_namespace', 'ptrp_reviewed' ],
				[
					$this->dbr->buildComparison( '<', [
						'ptrp_created' => $this->dbr->timestamp( $startTime ),
						'ptrp_page_id' => $startId,
					] ),
					$sqlWhere,
				],
				__METHOD__,
				[ 'LIMIT' => $this->getBatchSize(), 'ORDER BY' => 'ptrp_created DESC, ptrp_page_id DESC' ],
				[ 'page' => [ 'INNER JOIN', 'ptrp_page_id = page_id' ] ]
			);

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
			$lbFactory->waitForReplication();
		}
	}
}
