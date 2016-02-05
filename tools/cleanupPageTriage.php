<?php
/**
 * Remove page with namespace other than NS_MAIN/NS_USER from pagetriage queue
 *
 * @ingroup Maintenance
 */

require_once dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that removes page with namespace other than NS_MAIN/NS_USER
 * from pagetriage queue
 *
 * @ingroup Maintenance
 */
class CleanupPageTriage extends Maintenance {

	protected $batchSize = 100;

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_SLAVE );

		$count = $this->batchSize;
		$start = 0;

		while ( $count == $this->batchSize ) {
			$res = $dbr->select(
				array( 'pagetriage_page', 'page' ),
				array( 'page_id' ),
				array(
					'page_id = ptrp_page_id',
					'page_namespace != "' . NS_MAIN . '" AND page_namespace != "' . NS_USER . '"',
					'ptrp_page_id > ' . $start
				),
				__METHOD__,
				array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_page_id' )
			);

			$page = array();
			foreach ( $res as $row ) {
				$page[] = $row->page_id;
				$start  = $row->page_id;
			};
			$count = count( $page );

			if ( $count > 0 ) {
				$this->beginTransaction( $dbw, __METHOD__ );

				$dbw->delete(
					'pagetriage_page',
					array( 'ptrp_page_id' => $page ),
					__METHOD__
				);

				$dbw->delete(
					'pagetriage_log',
					array( 'ptrl_page_id' => $page ),
					__METHOD__
				);

				$dbw->delete(
					'pagetriage_page_tags',
					array( 'ptrpt_page_id' => $page ),
					__METHOD__
				);

				$this->commitTransaction( $dbw, __METHOD__ );

				$this->output( "processing " . $count . "\n" );
				wfWaitForSlaves();
			}

		}
	}
}

$maintClass = 'CleanupPageTriage'; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
