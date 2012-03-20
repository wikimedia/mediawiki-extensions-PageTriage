<?php
/**
 * Tests for SpecialPageTriage class (PageTriage list view)
 *
 * @group EditorEngagement
 * @author Ryan Kaldari
 */
class SpecialPageTriageTest extends PHPUnit_Framework_TestCase {

	protected $pageTriage;

	protected function setUp() {
		parent::setUp();
		$this->pageTriage = new SpecialPageTriage;
		
		// Insert some made up articles into the database
	}

	protected function tearDown() {
		parent::tearDown();
		
		// Remove the made up articles
	}

	// This is a sample test (not actually very useful)
	public function testGetTriageHeader() {
		$this->assertEquals(
			'<p>Page Triage Header goes here</p>',
			$this->pageTriage->getTriageHeader()
		);
	}

}
