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
		// Reset tables so we have a blank slate to begin with when adding the item to the queue.
		$this->db->truncate( 'pagetriage_page' );
		$this->db->truncate( 'pagetriage_page_tags' );
		$this->db->truncate( 'pagetriage_tags' );
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
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->db->truncate( 'pagetriage_page' );
		$this->db->truncate( 'pagetriage_page_tags' );
		$this->db->truncate( 'pagetriage_tags' );
		$pageIds[] = $this->insertPage( 'PageTriageTest', 'Testing 123' )['id'];

		$pageTriagePage = $this->db->newSelectQueryBuilder()
			->select( 'ptrp_tags_updated' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertNull( $pageTriagePage );

		PageTriage::bulkSetTagsUpdated( $pageIds );

		$newPageTriagePage = $this->db->newSelectQueryBuilder()
			->select( 'ptrp_tags_updated' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertNotNull( $newPageTriagePage );
	}
}
