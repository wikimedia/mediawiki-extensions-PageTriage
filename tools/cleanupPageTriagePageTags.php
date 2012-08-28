<?php
/**
 * Remove page from pagetriage_page_tags if they are not in pagetriage_page
 *
 * @ingroup Maintenance
 */

require_once( dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php' );

/**
 * Maintenance script that removes data from pagetriage_page_tags with page_id not in pagetriage_page
 *
 * @ingroup Maintenance
 */
class CleanupPageTriagePageTags extends Maintenance {

	protected $batchSize = 1000;

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_SLAVE );

		$count = $this->batchSize;
		$start = 0;

		while ( $count == $this->batchSize ) {
			$res = $dbr->select(
				array( 'pagetriage_page_tags', 'pagetriage_page' ),
				array( 'ptrpt_page_id AS page_id' ),
				array(
					'ptrpt_page_id > ' . $start,
					'ptrp_page_id IS NULL'
				),
				__METHOD__,
				array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrpt_page_id' ),
				array( 'pagetriage_page' => array( 'LEFT JOIN', 'ptrp_page_id = ptrpt_page_id' ) )
			);

			$page = array();
			$count = 0;
			foreach( $res as $row ) {
				if ( !in_array( $row->page_id, $page ) ) {
					$page[] = $row->page_id;
					$start  = $row->page_id;
				}
				$count++;
			};

			$pageCount = count( $page );
			if ( $pageCount > 0 ) {
				$dbw->delete(
					'pagetriage_page_tags',
					array( 'ptrpt_page_id' => $page ),
					__METHOD__
				);

				$this->output( "processing " . $pageCount . "\n" );
				wfWaitForSlaves();
			}

		}
	}
}

$maintClass = 'CleanupPageTriagePageTags'; // Tells it to run the class
require_once( RUN_MAINTENANCE_IF_MAIN );
