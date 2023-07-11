<?php

namespace MediaWiki\Extension\PageTriage\Test;

/**
 * Tests the Hooks class.
 *
 * @covers \MediaWiki\Extension\PageTriage\PageTriage
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 */
class HooksTest extends PageTriageTestCase {

	public function testDraftRedirectsAreNotAdded() {
		// Get the initial page count of the PageTriage queue.
		$originalCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		// Insert a redirect.
		$this->insertPage( 'Draft:Redirect test', '#REDIRECT [[Redirect test target]]' );
		// Check that it wasn't added to the queue.
		$actualCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		$this->assertEquals( $originalCount, $actualCount );
	}

}
