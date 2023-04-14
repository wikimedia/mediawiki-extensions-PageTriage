<?php
/**
 * Remove page from pagetriage_page_tags if they are not in pagetriage_page
 *
 * @ingroup Maintenance
 */

use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script that removes data from pagetriage_page_tags with page_id
 * not in pagetriage_page
 *
 * @ingroup Maintenance
 */
class CleanupPageTriagePageTags extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$batchSize = $this->getBatchSize();
		$count = $batchSize;
		$start = 0;

		while ( $count == $batchSize ) {
			$res = $dbr->select(
				[ 'pagetriage_page_tags', 'pagetriage_page' ],
				[ 'ptrpt_page_id AS page_id' ],
				[
					'ptrpt_page_id > ' . $start,
					'ptrp_page_id IS NULL'
				],
				__METHOD__,
				[ 'LIMIT' => $batchSize, 'ORDER BY' => 'ptrpt_page_id' ],
				[ 'pagetriage_page' => [ 'LEFT JOIN', 'ptrp_page_id = ptrpt_page_id' ] ]
			);

			$page = [];
			$count = 0;
			foreach ( $res as $row ) {
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
				if ( !in_array( $row->page_id, $page ) ) {
					$page[] = $row->page_id;
					$start  = $row->page_id;
				}
				$count++;
			}

			$pageCount = count( $page );
			if ( $pageCount > 0 ) {
				$dbw->delete(
					'pagetriage_page_tags',
					[ 'ptrpt_page_id' => $page ],
					__METHOD__
				);

				$this->output( "processing " . $pageCount . "\n" );
				$lbFactory->waitForReplication();
			}

		}
	}
}

$maintClass = CleanupPageTriagePageTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
