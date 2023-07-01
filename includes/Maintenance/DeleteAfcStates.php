<?php
namespace MediaWiki\Extension\PageTriage\Maintenance;

use BatchRowIterator;
use Maintenance;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

class DeleteAfcStates extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete the afc_state tag value for every draft in the queue' );
		$this->addOption( 'dry-run', 'Do not delete anything, print what would be deleted' );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'PageTriage' );
	}

	public function execute() {
		$dbr = PageTriageUtil::getReplicaConnection();
		$dbw = PageTriageUtil::getPrimaryConnection();
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
		$iterator->setCaller( __METHOD__ );

		foreach ( $iterator as $rows ) {
			$pageIds = array_map( static function ( $row ) {
				return $row->ptrpt_page_id;
			}, $rows );

			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output(
					"Would delete afc state for " . count( $pageIds ) .
					" pages. Starting with id:" . reset( $pageIds ) . "\n"
				);
				continue;
			}

			$dbw->newDeleteQueryBuilder()
				->delete( 'pagetriage_page_tags' )
				->where( [
					'ptrpt_page_id' => $pageIds,
					'ptrpt_tag_id' => $afcStateTagId,
				] )
				->caller( __METHOD__ )
				->execute();
			$this->waitForReplication();

			$count = count( $pageIds );
			$first = reset( $pageIds );
			$last = end( $pageIds );

			$this->output( "Deleted afc_state for $count pages. From $first to $last.\n" );
		}

		$this->output( "Done\n" );
	}
}
