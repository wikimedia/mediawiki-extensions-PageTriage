<?php
namespace MediaWiki\Extension\PageTriage\Maintenance;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * A maintenance script that updates expired user metadata
 */
class UpdateUserMetadata extends Maintenance {

	/**
	 * @var IDatabase|null
	 */
	protected $dbr;
	/**
	 * @var IDatabase|null
	 */
	protected $dbw;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update the user metadata in pagetriage_page_tags table" );
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 300 );
	}

	protected function init() {
		$this->dbr = PageTriageUtil::getReplicaConnection();
	}

	/**
	 * @suppress PhanPossiblyUndeclaredVariable False positive with $row
	 */
	public function execute() {
		$this->init();
		$this->output( "Started processing... \n" );

		// Scan for data updated more than a day ago
		$startTime = (int)wfTimestamp( TS_UNIX ) - 60 * 60 * 24;
		$count = $this->getBatchSize();

		$idRow = $this->dbr->newSelectQueryBuilder()
			->select( [ 'max_id' => 'MAX(ptrp_page_id)' ] )
			->from( 'pagetriage_page' )
			->caller( __METHOD__ )
			->fetchRow();

		// No data to process, exit
		if ( $idRow === false ) {
			return;
		}

		$startId = $idRow->max_id + 1;

		$pageTriageNamespaces = PageTriageUtil::getNamespaces();
		if ( count( $pageTriageNamespaces ) > 0 ) {
			$namespace = $pageTriageNamespaces;
		} else {
			$namespace = NS_MAIN;
		}

		while ( $count === $this->getBatchSize() ) {
			$count = 0;
			$res = $this->dbr->newSelectQueryBuilder()
				->select( [ 'ptrp_page_id', 'ptrp_tags_updated' ] )
				->from( 'pagetriage_page' )
				->join( 'page', null, 'page_id = ptrp_page_id' )
				->where( [
					$this->dbr->buildComparison( '<', [
						'ptrp_tags_updated' => $this->dbr->timestamp( $startTime ),
						'ptrp_page_id' => $startId,
					] ),
					'page_namespace' => $namespace
				] )
				->limit( $this->getBatchSize() )
				->orderBy( [ 'ptrp_tags_updated', 'ptrp_page_id' ], SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchResultSet();

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
				$startId = (int)$row->ptrp_page_id;

				$acp = ArticleCompileProcessor::newFromPageId( $pageId );
				if ( $acp ) {
					$acp->registerComponent( 'UserData' );
					// safe to use replica db for data compilation
					$acp->configComponentDb( [ 'UserData' => DB_REPLICA ] );
					$acp->compileMetadata();
				}

				$this->output( "processed $count \n" );
				$this->waitForReplication();
			}
		}

		$this->output( "Completed \n" );
	}
}
