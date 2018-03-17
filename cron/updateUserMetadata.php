<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * A maintenance script that updates expired user metadata
 */
class UpdateUserMetadata extends Maintenance {

	/**
	 * Max number of article to process at a time
	 * @var int
	 */
	protected $batchSize = 300;

	/**
	 * @var \Wikimedia\Rdbms\IDatabase
	 */
	protected $dbr, $dbw;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Update the user metadata in pagetriage_page_tags table";
		$this->requireExtension( 'PageTriage' );
	}

	protected function init() {
		$this->dbr = wfGetDB( DB_REPLICA );
	}

	public function execute() {
		global $wgPageTriageNamespaces;

		$this->init();
		$this->output( "Started processing... \n" );

		// Scan for data updated more than a day ago
		$startTime = wfTimestamp( TS_UNIX ) - 60 * 60 * 24;
		$count = $this->batchSize;

		$row = $this->dbr->selectRow(
			[ 'pagetriage_page' ],
			[ 'MAX(ptrp_page_id) AS max_id' ],
			[],
			__METHOD__
		);

		// No data to process, exit
		if ( $row === false ) {
			return;
		}

		$startId = $row->max_id + 1;

		if ( count( $wgPageTriageNamespaces ) > 0 ) {
			$namespace = $wgPageTriageNamespaces;
		} else {
			$namespace = NS_MAIN;
		}

		while ( $count === $this->batchSize ) {
			$count = 0;
			$startTime = $this->dbr->addQuotes( $this->dbr->timestamp( $startTime ) );
			$startId = (int)$startId;

			$res = $this->dbr->select(
				[ 'pagetriage_page', 'page' ],
				[ 'ptrp_page_id', 'ptrp_tags_updated' ],
				[
					'(ptrp_tags_updated < ' . $startTime . ') OR
					(ptrp_tags_updated = ' . $startTime . ' AND ptrp_page_id < ' . $startId . ')',
					'page_id = ptrp_page_id',
					'page_namespace' => $namespace
				],
				__METHOD__,
				[ 'LIMIT' => $this->batchSize, 'ORDER BY' => 'ptrp_tags_updated DESC, ptrp_page_id DESC' ]
			);

			$pageId = [];
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
					// safe to use slave db for data compilation
					$acp->configComponentDb( [ 'UserData' => DB_REPLICA ] );
					$acp->compileMetadata();
				}

				$this->output( "processed $count \n" );
				wfWaitForSlaves();
			}
		}

		$this->output( "Completed \n" );
	}
}

$maintClass = "UpdateUserMetadata";
require_once RUN_MAINTENANCE_IF_MAIN;
