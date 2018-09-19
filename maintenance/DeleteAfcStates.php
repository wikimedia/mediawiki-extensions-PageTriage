<?php

use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class DeleteAfcStates extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Delete the afc_state tag value for every draft in the queue';
		$this->addOption( 'dry-run', 'Do not delete anything, print what would be deleted' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );
		$dbw = $this->getDB( DB_MASTER );
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$afcStateTagId = ArticleMetadata::getValidTags()['afc_state'];

		$iterator = new BatchRowIterator(
			$dbr,
			'pagetriage_page_tags',
			[ 'ptrpt_page_id', 'ptrpt_tag_id' ],
			$this->mBatchSize
		);
		$iterator->setFetchColumns( [ 'ptrpt_page_id' ] );
		$iterator->addConditions( [
			'ptrpt_tag_id' => $afcStateTagId,
		] );

		foreach ( $iterator as $rows ) {
			$pageIds = array_map( function ( $row ) {
				return $row->ptrpt_page_id;
			}, $rows );

			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output(
					"Would delete afc state for " . count( $pageIds ) .
					" pages. Starting with id:" . reset( $pageIds ) . "\n"
				);
				continue;
			}

			$dbw->delete(
				'pagetriage_page_tags',
				[
					'ptrpt_page_id' => $pageIds,
					'ptrpt_tag_id' => $afcStateTagId,
				]
			);
			$lbFactory->waitForReplication();

			$count = count( $pageIds );
			$first = reset( $pageIds );
			$last = end( $pageIds );
			$this->output( "Deleted afc_state for $count pages. From $first to $last.\n" );
		}

		$this->output( "Done\n" );
	}
}

$maintClass = DeleteAfcStates::class;

require_once RUN_MAINTENANCE_IF_MAIN;
