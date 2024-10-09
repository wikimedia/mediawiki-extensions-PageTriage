<?php

namespace MediaWiki\Extension\PageTriage\Test\Integration;

use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageTriage\PageTriage
 * @group Database
 *
 * The PageTriage class is getting spilt up into other classes, but while it still exists,
 * let's have some tests.
 */
class PageTriageTest extends MediaWikiIntegrationTestCase {

	public function testAddToPageTriageQueue() {
		$pageId = $this->insertPage( 'PageTriageTest', '' )['id'];

		// Remove from queue to test adding to it
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();

		$pageTriage = new PageTriage( $pageId );
		$record = $pageTriage->retrieve();
		$this->assertFalse( $record );
		$result = $pageTriage->addToPageTriageQueue();
		$this->assertTrue( $result );
		$record = $pageTriage->retrieve();
		$this->assertTrue( $record );
	}

	public function testBulkSetTagsUpdated() {
		// Skipping this test if ORES is not loaded. See: T335998
		// $this->markTestSkippedIfExtensionNotLoaded( 'ORES' );

		// Skip the test altogether. T376412, T335998
		$this->markTestSkipped();

		$pageIds[] = $this->insertPage( 'PageTriageTest', 'Testing 123' )['id'];

		$pageTriagePage = $this->getDb()->newSelectQueryBuilder()
			->select( 'ptrp_tags_updated' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertNull( $pageTriagePage );

		PageTriage::bulkSetTagsUpdated( $pageIds );

		$newPageTriagePage = $this->getDb()->newSelectQueryBuilder()
			->select( 'ptrp_tags_updated' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertNotNull( $newPageTriagePage );
	}
}
