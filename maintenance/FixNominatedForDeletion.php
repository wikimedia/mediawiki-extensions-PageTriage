<?php

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileDeletionTag;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class FixNominatedForDeletion extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Set ptrp_deleted on pages nominated for deletion';
		$this->addOption( 'dry-run', 'Do not fetch scores, only print revisions.' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$dbr = $this->getDB( DB_REPLICA );
		$dbw = $this->getDB( DB_MASTER );
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$iterator = new BatchRowIterator(
			$dbr,
			[ 'pagetriage_page', 'categorylinks' ],
			'ptrp_page_id',
			$this->mBatchSize
		);
		$iterator->setFetchColumns( [ 'ptrp_page_id' ] );
		$iterator->addJoinConditions( [
			'categorylinks' => [ 'INNER JOIN', 'ptrp_page_id = cl_from' ],
		] );
		$iterator->addConditions( [
			'ptrp_deleted' => 0,
			'cl_to' => array_keys( ArticleCompileDeletionTag::getDeletionTags() ),
		] );
		// deduplicate pages in multiples deletion categories
		$iterator->addOptions( [ 'GROUP BY' => 'ptrp_page_id' ] );

		foreach ( $iterator as $rows ) {
			$pageIds = array_map( function ( $row ) {
				return $row->ptrp_page_id;
			}, $rows );

			if ( $this->hasOption( 'dry-run' ) ) {
				$this->output( "Pages: " . implode( ', ', $pageIds ) . "\n" );
				continue;
			}

			$dbw->update(
				'pagetriage_page',
				[ 'ptrp_deleted' => 1 ],
				[ 'ptrp_page_id' => $pageIds ]
			);
			$lbFactory->waitForReplication();

			$count = count( $pageIds );
			$first = reset( $pageIds );
			$last = end( $pageIds );
			$this->output( "Updated $count pages. From $first to $last.\n" );
		}

		$this->output( "Done\n" );
	}
}

$maintClass = FixNominatedForDeletion::class;

require_once RUN_MAINTENANCE_IF_MAIN;
