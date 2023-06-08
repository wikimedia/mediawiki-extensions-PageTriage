<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the cleanupPageTriage.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriage
 *
 * @group medium
 * @group Database
 */
class MaintenanceCleanupPageTriageTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'pagetriage_page', 'pagetriage_page_tags' ];
		// Delete any dangling page triage pages before inserting our test data
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page_tags' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulPageTriageCleanup() {
		// Allow PROJECT namespaces to be added to the queue
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2, 4 ] );
		$dbr = PageTriageUtil::getReplicaConnection();
		// Create some pages in the USER or MAIN namespace
		$this->insertPage( 'Main1', 'Test 1', NS_MAIN );
		$this->insertPage( 'User1', 'Test 1', NS_USER );
		// Create a PROJECT page
		$this->insertPage( 'ProjectPage', 'Test 1', NS_PROJECT );

		$initialPageTriageCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 3, $initialPageTriageCount );

		$maint = new CleanupPageTriage();
		$maint->execute();
		$this->expectOutputString( "processing 1\n" );

		// The script should delete the USER_TALK page from the queue
		$newPageTriageCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 2, $newPageTriageCount );
	}
}
