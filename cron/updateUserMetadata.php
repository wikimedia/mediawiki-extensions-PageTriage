<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * A maintenance script that updates expired user metadata
 */
class updateUserMetadata extends Maintenance {

	/**
	 * Max number of article to process at a time
	 * @var int
	 */
	protected $batchSize = 500;	

	/**
	 * @var DatabaseBase
	 */
	protected $dbr, $dbw;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Update the user metadata in pagetriage_page_tags table";
	}

	protected function init() {
		$this->dbr = wfGetDB( DB_SLAVE );
	}

	public function execute() {
		$this->init();
		$this->output( "Started processing... \n" );

		// Scan for data updated more than a day ago
		$startTime = wfTimestamp( TS_UNIX ) - 60 * 60 * 24;
		$count = $this->batchSize;

		$row = $this->dbr->selectRow(
			array( 'pagetriage_page' ),
			array( 'MAX(ptrp_page_id) AS max_id' ),
			array(),
			__METHOD__
		);

		// No data to process, exit
		if ( $row === false ) {
			return;
		}

		$startId = $row->max_id + 1;

		while ( $count === $this->batchSize ) {
			$count = 0;
			$startTime = $this->dbr->addQuotes( $this->dbr->timestamp( $startTime ) );
			$startId = intval( $startId );

			$res = $this->dbr->select(
				array( 'pagetriage_page' ),
				array( 'ptrp_page_id', 'ptrp_tags_updated' ),
				array(
					'(ptrp_tags_updated < ' . $startTime . ') OR 
					(ptrp_tags_updated = ' . $startTime . ' AND ptrp_page_id < ' . $startId . ')'
				),
				__METHOD__,
				array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_tags_updated DESC, ptrp_page_id DESC' )
			);

			$pageId = array();
			foreach ( $res as $row ) {
				$pageId[] = $row->ptrp_page_id;
				$count++;
			}

			if ( $pageId ) {
				// update the startTime with the last row if it's set, check in case it's not set
				if ( $row->ptrp_tags_updated ) {
					$startTime = wfTimestamp( TS_UNIX, $row->ptrp_tags_updated );
				}
				$startId = $row->ptrp_page_id;

				$acp = ArticleCompileProcessor::newFromPageId( $pageId );
				if ( $acp ) {
					$acp->registerComponent( 'UserData' );
					$acp->compileMetadata();
				}

				$this->output( "processed $count \n" );
				wfWaitForSlaves();
			}
		}

		$this->output( "Completed \n" );
	}
}

$maintClass = "updateUserMetadata";
require_once( DO_MAINTENANCE );
