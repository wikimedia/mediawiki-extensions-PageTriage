<?php
/**
 * Remove page with namespace other than NS_MAIN/NS_USER from pagetriage queue
 *
 * @ingroup Maintenance
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that removes page with namespace other than NS_MAIN/NS_USER
 * from pagetriage queue
 *
 * @ingroup Maintenance
 */
class CleanupPageTriage extends Maintenance {

	protected $batchSize = 100;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageTriage' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_REPLICA );

		$count = $this->batchSize;
		$start = 0;

		while ( $count == $this->batchSize ) {
			$res = $dbr->select(
				[ 'pagetriage_page', 'page' ],
				[ 'page_id' ],
				[
					'page_id = ptrp_page_id',
					'page_namespace != "' . NS_MAIN . '" AND page_namespace != "' . NS_USER . '"',
					'ptrp_page_id > ' . $start
				],
				__METHOD__,
				[ 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_page_id' ]
			);

			$page = [];
			foreach ( $res as $row ) {
				$page[] = $row->page_id;
				$start  = $row->page_id;
			};
			$count = count( $page );

			if ( $count > 0 ) {
				$this->beginTransaction( $dbw, __METHOD__ );

				$dbw->delete(
					'pagetriage_page',
					[ 'ptrp_page_id' => $page ],
					__METHOD__
				);

				$dbw->delete(
					'pagetriage_log',
					[ 'ptrl_page_id' => $page ],
					__METHOD__
				);

				$dbw->delete(
					'pagetriage_page_tags',
					[ 'ptrpt_page_id' => $page ],
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
