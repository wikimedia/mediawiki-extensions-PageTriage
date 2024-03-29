<?php
namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\SpecialNewPagesFeed;
use SpecialPageTestBase;

/**
 * Tests for SpecialNewPagesFeed class (PageTriage list view)
 *
 * @covers \MediaWiki\Extension\PageTriage\SpecialNewPagesFeed
 */
class SpecialNewPagesFeedTest extends SpecialPageTestBase {

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return new SpecialNewPagesFeed();
	}

	public function testPageLoads() {
		[ $html, ] = $this->executeSpecialPage(
			'',
			null,
			'qqx'
		);

		// Welcome message should display
		$this->assertStringContainsString( 'pagetriage-welcome', $html );
		// List View content should load
		$this->assertStringContainsString( 'pagetriage-please-wait', $html );
		$this->assertStringContainsString( 'pagetriage-js-required', $html );
	}

}
