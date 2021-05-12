<?php

namespace MediaWiki\Extension\PageTriage\Test;

use PageTriageTestCase;

/**
 * Tests the Hooks class.
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 */
class HooksTest extends PageTriageTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriage
	 */
	public function testDraftRedirectsAreNotAdded() {
		$db = wfGetDB( DB_PRIMARY );
		// Get the initial page count of the PageTriage queue.
		$originalCount = $db->selectRowCount( 'pagetriage_page' );
		// Insert a redirect.
		$this->insertPage( 'Draft:Redirect test', '#REDIRECT [[Redirect test target]]' );
		// Check that it wasn't added to the queue.
		static::assertEquals( $originalCount, $db->selectRowCount( 'pagetriage_page' ) );
	}

}
