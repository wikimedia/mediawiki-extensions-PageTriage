<?php
/**
 * Remove page with namespace other than NS_MAIN/NS_USER from pagetriage queue
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
 * Maintenance script that removes page with namespace other than NS_MAIN/NS_USER
 * from pagetriage queue
 *
 * @ingroup Maintenance
 */
class CleanupPageTriage extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$dbw = PageTriageUtil::getConnection( DB_PRIMARY );
		$dbr = PageTriageUtil::getConnection( DB_REPLICA );
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$batchSize = $this->getBatchSize();
		$count = $batchSize;
		$start = 0;

		while ( $count == $batchSize ) {
			$res = $dbr->select(
				[ 'pagetriage_page', 'page' ],
				[ 'page_id' ],
				[
					'page_id = ptrp_page_id',
					'page_namespace != "' . NS_MAIN . '" AND page_namespace != "' . NS_USER . '"',
					'ptrp_page_id > ' . $start
				],
				__METHOD__,
				[ 'LIMIT' => $batchSize, 'ORDER BY' => 'ptrp_page_id' ]
			);

			$page = [];
			foreach ( $res as $row ) {
				$page[] = $row->page_id;
				$start  = $row->page_id;
			}
			$count = count( $page );

			if ( $count > 0 ) {
				$this->beginTransaction( $dbw, __METHOD__ );

				$dbw->delete(
					'pagetriage_page',
					[ 'ptrp_page_id' => $page ],
					__METHOD__
				);

				$dbw->delete(
					'pagetriage_page_tags',
					[ 'ptrpt_page_id' => $page ],
					__METHOD__
				);

				$this->commitTransaction( $dbw, __METHOD__ );

				$this->output( "processing " . $count . "\n" );
				$lbFactory->waitForReplication();
			}

		}
	}
}

$maintClass = CleanupPageTriage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
