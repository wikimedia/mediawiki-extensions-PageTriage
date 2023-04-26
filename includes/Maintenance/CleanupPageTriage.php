<?php
/**
 * Remove page with namespace other than NS_MAIN/NS_USER from pagetriage queue
 *
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

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
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$batchSize = $this->getBatchSize();
		$count = $batchSize;
		$start = 0;

		while ( $count == $batchSize ) {
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'page_id' ] )
				->from( 'pagetriage_page' )
				->join( 'page', null, 'page_id = ptrp_page_id' )
				->where(
					[
						'page_namespace != "' . NS_MAIN . '" AND page_namespace != "' . NS_USER . '"',
						'ptrp_page_id > ' . $start
					]
				)
				->limit( $batchSize )
				->orderBy( 'ptrp_page_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

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
