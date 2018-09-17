<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;

/**
 * A maintenance script for populating the Draft namespace queue.
 */
class PopulateDraftQueue extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Add missing Draft namespace pages to the AfC triage queue.";
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
		$db = $this->getDB( DB_MASTER );

		// Loop through all batches.
		$batchNum = 1;
		$totalProcessed = 0;
		$lastBatch = false;
		while ( !$lastBatch ) {
			// Progress indicator.
			$this->output( "- batch $batchNum\n" );
			$batchNum++;

			// Find all Draft NS pages that don't have records in the PageTriage table.
			$drafts = $db->select(
				[ 'page', 'pagetriage_page', 'pagetriage_page_tags' ],
				[ 'page_id' ],
				[
					'ptrp_page_id IS NULL OR ptrpt_page_id IS NULL',
					'page_namespace' => $pageTriageDraftNamespaceId,
					'page_is_redirect' => '0',
				],
				__METHOD__,
				[ 'LIMIT' => $this->getBatchSize() ],
				[
					'pagetriage_page' => [ 'LEFT JOIN', [ 'page_id = ptrp_page_id' ] ],
					'pagetriage_page_tags' => [ 'LEFT JOIN', [
						'page_id = ptrpt_page_id',
						'ptrpt_tag_id' => $afcStateTagId,
					] ],
				]
			);

			// The loop will exit if this is the last batch.
			if ( $drafts->numRows() < $this->getBatchSize() ) {
				$lastBatch = true;
			}

			// Go through this batch, and add each page to the PageTriage queue.
			foreach ( $drafts as $draft ) {
				$this->beginTransaction( $db, __METHOD__ );
				$pageTriage = new PageTriage( $draft->page_id );
				$pageTriage->addToPageTriageQueue( '0' );
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
