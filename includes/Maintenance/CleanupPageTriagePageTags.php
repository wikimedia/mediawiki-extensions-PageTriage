<?php
/**
 * Remove page from pagetriage_page_tags if they are not in pagetriage_page
 *
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

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
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'page_id' => 'ptrpt_page_id' ] )
				->from( 'pagetriage_page_tags' )
				->leftJoin( 'pagetriage_page', null, 'ptrp_page_id = ptrpt_page_id' )
				->where( [
					'ptrpt_page_id > ' . $start,
					'ptrp_page_id IS NULL'
				] )
				->limit( $batchSize )
				->orderBy( 'ptrpt_page_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

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
				$dbw->newDeleteQueryBuilder()
					->delete( 'pagetriage_page_tags' )
					->where( [ 'ptrpt_page_id' => $page ] )
					->caller( __METHOD__ )
					->execute();

				$this->output( "processing " . $pageCount . "\n" );
				$lbFactory->waitForReplication();
			}

		}
	}
}
