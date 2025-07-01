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
class RemoveOldRowsTest extends PageTriageTestCase {

	public function testSuccessfulRemoveOldRows() {
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2 ] );
		// Create some pages in the USER and MAIN namespace
		$mainNsPage = $this->insertPage( 'MainRows', 'Test 1', NS_MAIN );
		$userNsPage = $this->insertPage( 'UserRows', 'Test 1', NS_USER );

		$initialPageTriageCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( 2, $initialPageTriageCount );

		// Change the create date so that they will be deleted by the cron
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [
				'ptrp_reviewed' => 1,
				'ptrp_created' => $this->getDb()->timestamp( '20200323210427' )
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

		$newPageTriageCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertSame( 0, $newPageTriageCount );
	}
}
