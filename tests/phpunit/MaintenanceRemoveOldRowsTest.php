<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\RemoveOldRows;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

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
		PageTriageUtil::getPrimaryConnection()->delete( 'pagetriage_page', '*' );
	}

	public function testSuccessfulRemoveOldRows() {
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2 ] );
		$dbr = PageTriageUtil::getReplicaConnection();
		$dbw = PageTriageUtil::getPrimaryConnection();

		// Create some pages in the USER and MAIN namespace
		$mainNsPage = $this->insertPage( 'Main1', 'Test 1', NS_MAIN );
		$userNsPage = $this->insertPage( 'User1', 'Test 1', NS_USER );

		$initialPageTriageCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 2, $initialPageTriageCount );

		// Change the create date so that they will be deleted by the cron
		$dbw->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [
				'ptrp_reviewed' => 1,
				'ptrp_created' => $dbw->timestamp( '20200323210427' )
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

		$newPageTriageCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertSame( 0, $newPageTriageCount );
	}
}
