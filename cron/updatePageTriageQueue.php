<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * A maintenance script that updates expired user metadata
 */
class updatePageTriageQueue extends Maintenance {

	/**
	 * Max number of article to process at a time
	 * @var int
	 */
	protected $batchSize = 300;

	/**
	 * @var DatabaseBase
	 */
	protected $dbr, $dbw;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Remove page from pagetriage queue after 60 days of inactivity";
	}

	protected function init() {
		$this->dbr = wfGetDB( DB_SLAVE );
		$this->dbw = wfGetDB( DB_MASTER );
	}

	public function execute() {
		$this->init();
		$this->output( "Started processing... \n" );

		// Scan for data with reviewed_updated set more than 60 days ago
		$startTime = wfTimestamp( TS_UNIX ) - 60* 60 * 60 * 24;
		$count = $this->batchSize;

		$row = $this->dbr->selectRow(
			array( 'pagetriage_page' ),
			array( 'MAX(ptrp_page_id) AS max_id' ),
			array(),
			__METHOD__
		);

		// No data to process, exit
		if ( $row === false ) {
			$this->output( "No data to process \n" );
			return;
		}

		$startId = $row->max_id + 1;

		while ( $count === $this->batchSize ) {
			$count = 0;
			$startTime = $this->dbr->addQuotes( $this->dbr->timestamp( $startTime ) );
			$startId = intval( $startId );

			// Remove articles from pagetriage queue after 60 days of inactivity, if
			// 1. the article has been reviewed
			// 2. the article is not in main namespace
			$res = $this->dbr->select(
				array( 'pagetriage_page', 'page' ),
				array( 'ptrp_page_id', 'ptrp_reviewed_updated', 'page_namespace', 'ptrp_reviewed' ),
				array(
					'(ptrp_reviewed_updated < ' . $startTime . ') OR
					(ptrp_reviewed_updated = ' . $startTime . ' AND ptrp_page_id < ' . $startId . ')',
					'ptrp_page_id = page_id',
					'page_namespace != 0 OR ptrp_reviewed > 0'
				),
				__METHOD__,
				array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_reviewed_updated DESC, ptrp_page_id DESC' )
			);

			$pageId = array();
			foreach ( $res as $row ) {
				$pageId[] = $row->ptrp_page_id;
				$count++;
			}

			if ( $pageId ) {
				// update data from last row
				if ( $row->ptrp_reviewed_updated ) {
					$startTime = wfTimestamp( TS_UNIX, $row->ptrp_reviewed_updated );
				}
				$startId = $row->ptrp_page_id;

				$this->dbw->begin();

				$this->dbw->delete(
						'pagetriage_page',
						array( 'ptrp_page_id' => $pageId ),
						__METHOD__,
						array()
				);
				$this->dbw->delete(
						'pagetriage_log',
						array( 'ptrl_page_id' => $pageId ),
						__METHOD__,
						array()
				);
				$articleMetadata = new ArticleMetadata( $pageId );
				$articleMetadata->deleteMetadata();

				$this->dbw->commit();
			}

			$this->output( "processed $count \n" );
			wfWaitForSlaves();
		}

		$this->output( "Completed \n" );
	}
}

$maintClass = "updatePageTriageQueue";
require_once( DO_MAINTENANCE );
