<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\RemoveOldRows;

/**
 * Tests for the removeOldRows.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\RemoveOldRows
 *
 * @group medium
 * @group Database
 */
class MaintenanceRemoveOldRowsTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'pagetriage_page' ];
		// Delete any dangling page triage pages before inserting our test data
		$this->db->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulRemoveOldRows() {
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2 ] );
		// Create some pages in the USER and MAIN namespace
		$mainNsPage = $this->insertPage( 'MainRows', 'Test 1', NS_MAIN );
		$userNsPage = $this->insertPage( 'UserRows', 'Test 1', NS_USER );

		$initialPageTriageCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 2, $initialPageTriageCount );

		// Change the create date so that they will be deleted by the cron
		$this->db->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [
				'ptrp_reviewed' => 1,
				'ptrp_created' => $this->db->timestamp( '20200323210427' )
			] )
			->where( [ 'ptrp_page_id' => [ $mainNsPage[ 'id' ], $userNsPage[ 'id' ] ] ] )
			->caller( __METHOD__ )
			->execute();

		$maint = new RemoveOldRows();
		$maint->execute();
		$this->expectOutputString( "Started processing... \n" .
			"cleanReviewedPagesAndUnusedNamespaces()... \n" .
			"processed 2 \n" .
			"cleanRedirects()... \n" .
			"processed 0 \n" .
			"Completed \n"
		);

		$newPageTriageCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertSame( 0, $newPageTriageCount );
	}
}
