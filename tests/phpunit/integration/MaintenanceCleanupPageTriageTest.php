<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriage;

/**
 * Tests for the cleanupPageTriage.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriage
 *
 * @group medium
 * @group Database
 */
class MaintenanceCleanupPageTriageTest extends PageTriageTestCase {

	public function testSuccessfulPageTriageCleanup() {
		// Allow PROJECT namespaces to be added to the queue
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2, 4 ] );
		// Create some pages in the USER or MAIN namespace
		$this->insertPage( 'Main1', 'Test 1', NS_MAIN );
		$this->insertPage( 'User1', 'Test 1', NS_USER );
		// Create a PROJECT page
		$this->insertPage( 'ProjectPage', 'Test 1', NS_PROJECT );

		$initialPageTriageCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 3, $initialPageTriageCount );

		$maint = new CleanupPageTriage();
		$maint->execute();
		$this->expectOutputString( "processing 1\n" );

		// The script should delete the USER_TALK page from the queue
		$newPageTriageCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 2, $newPageTriageCount );
	}
}
