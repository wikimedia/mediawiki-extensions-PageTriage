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
	 * Database Object
	 */
	protected $dbr;
	protected $dbw;

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

		// Make the start time really old
		$startTime = wfTimestamp( TS_UNIX ) - 60 * 60 * 24 * 365 * 10;
		$count = $this->batchSize;
		$startId = 0;

		while ( $count === $this->batchSize ) {
			$count = 0;
			$startTime = $this->dbr->addQuotes( $this->dbr->timestamp( $startTime ) );

			// Data should expire in a day, keep this inside loop so 
			// it's update to second
			$expiration = wfTimestamp( TS_UNIX ) - 60 * 60 * 24;
			$res = $this->dbr->select(
				array( 'pagetriage_page' ),
				array( 'ptrp_page_id', 'ptrp_created' ),
				array(
					'(ptrp_created > ' . $startTime . ') OR 
					(ptrp_created = ' . $startTime . ' AND ptrp_page_id > ' . $startId . ')', 
					'ptrp_tags_updated < ' . $this->dbr->addQuotes( $this->dbr->timestamp( $expiration ) )
				),
				__METHOD__,
				array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_created, ptrp_page_id' )
			);

			$pageId = array();
			foreach ( $res as $row ) {
				$pageId[] = $row->ptrp_page_id;
				$count++;
			}

			if ( $pageId ) {
				// update the startTime with the last row
				$startTime = wfTimestamp( TS_UNIX, $row->ptrp_created );
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
