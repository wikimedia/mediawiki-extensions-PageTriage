<?php
/**
 * Remove page with namespace other than NS_MAIN/NS_USER from pagetriage queue
 *
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

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

		$batchSize = $this->getBatchSize();
		$count = $batchSize;
		$start = 0;

		while ( $count == $batchSize ) {
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'page_id' ] )
				->from( 'pagetriage_page' )
				->join( 'page', null, 'page_id = ptrp_page_id' )
				->where( [
					$dbr->expr( 'page_namespace', '!=', [ NS_MAIN, NS_USER ] ),
					$dbr->expr( 'ptrp_page_id', '>', $start ),
				] )
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

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'pagetriage_page' )
					->where( [ 'ptrp_page_id' => $page ] )
					->caller( __METHOD__ )
					->execute();

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'pagetriage_page_tags' )
					->where( [ 'ptrpt_page_id' => $page ] )
					->caller( __METHOD__ )
					->execute();

				$this->commitTransaction( $dbw, __METHOD__ );

				$this->output( "processing " . $count . "\n" );
				$this->waitForReplication();
			}

		}
	}
}
