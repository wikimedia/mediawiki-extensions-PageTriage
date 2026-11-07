<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\BackfillPtrpEnqueued;

/**
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\BackfillPtrpEnqueued
 * @group medium
 * @group Database
 */
class BackfillPtrpEnqueuedTest extends PageTriageTestCase {

	public function testBackfillMissingTimestamps(): void {
		$db = $this->getDb();
		$rows = [];
		for ( $id = 1; $id <= 5; $id++ ) {
			$rows[] = [
				'ptrp_page_id' => $id,
				'ptrp_reviewed' => 1,
				'ptrp_created' => $db->timestamp( '20200101000000' ),
				'ptrp_reviewed_updated' => $db->timestamp(
					sprintf( '202001%02d000000', $id )
				),
				'ptrp_enqueued' => null,
			];
		}
		$db->newInsertQueryBuilder()
			->insertInto( 'pagetriage_page' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		$script = new BackfillPtrpEnqueued();
		$script->setForce();
		$this->assertTrue( $script->execute() );
		$results = $db->newSelectQueryBuilder()
			->select( [ 'ptrp_enqueued', 'ptrp_reviewed_updated' ] )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => [ 1, 2, 3, 4, 5 ] ] )
			->orderBy( 'ptrp_page_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 5, $results->numRows() );
		foreach ( $results as $row ) {
			$this->assertSame( $row->ptrp_reviewed_updated, $row->ptrp_enqueued );
		}

		$this->expectOutputString( "Backfilled 5 PageTriage enqueue timestamps.\n" );
	}
}
