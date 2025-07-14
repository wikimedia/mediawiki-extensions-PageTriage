<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * A maintenance script for populating the Draft namespace queue.
 */
class PopulateDraftQueue extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Add missing Draft namespace pages to the AfC triage queue." );
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 100 );
	}

	/**
	 * @return bool|null True for success, false for failure.
	 */
	public function execute() {
		// Find the Draft namespace ID.
		$pageTriageDraftNamespaceId = $this->getConfig()->get( 'PageTriageDraftNamespaceId' );
		if ( !$pageTriageDraftNamespaceId ) {
			$this->output(
				'Unable to determine Draft namespace. Please set $wgPageTriageDraftNamespaceId'
			);
			return false;
		}

		$afcStateTagId = ArticleMetadata::getValidTags()['afc_state'];

		// Set up.
		$this->output( "Processing drafts in NS $pageTriageDraftNamespaceId...\n" );
		$db = $this->getDB( DB_PRIMARY );

		// Loop through all batches.
		$batchNum = 1;
		$totalProcessed = 0;
		$lastBatch = false;
		while ( !$lastBatch ) {
			// Progress indicator.
			$this->output( "- batch $batchNum\n" );
			$batchNum++;

			// Find all Draft NS pages that don't have records in the PageTriage table.
			$drafts = $db->newSelectQueryBuilder()
				->select( 'page_id' )
				->from( 'page' )
				->leftJoin( 'pagetriage_page', 'pagetriage_page', 'page_id = ptrp_page_id' )
				->leftJoin( 'pagetriage_page_tags', 'pagetriage_page_tags',
					[
						'page_id = ptrpt_page_id',
						'ptrpt_tag_id' => $afcStateTagId,
					]
				)
				->where( [
					$db->expr( 'ptrp_page_id', '=', null )->or( 'ptrpt_page_id', '=', null ),
					'page_namespace' => $pageTriageDraftNamespaceId,
					'page_is_redirect' => '0',
				] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();

			// The loop will exit if this is the last batch.
			if ( $drafts->numRows() < $this->getBatchSize() ) {
				$lastBatch = true;
			}

			// Go through this batch, and add each page to the PageTriage queue.
			foreach ( $drafts as $draft ) {
				$this->beginTransaction( $db, __METHOD__ );
				$pageTriage = new PageTriage( $draft->page_id );
				$pageTriage->addToPageTriageQueue();
				$acp = ArticleCompileProcessor::newFromPageId( [ $draft->page_id ] );
				if ( $acp ) {
					$acp->compileMetadata();
				}
				$this->commitTransaction( $db, __METHOD__ );
				$totalProcessed++;
			}
		}

		// Finish.
		$this->output( "Complete; $totalProcessed drafts processed.\n" );
		return true;
	}

}

// @codeCoverageIgnoreStart
$maintClass = PopulateDraftQueue::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
