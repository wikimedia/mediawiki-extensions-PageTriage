<?php

use MediaWiki\MediaWikiServices;

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 */
class ApiPageTriageListTest extends ApiTestCase {

	/** @var int */
	protected $draftNsId = 150;

	public function setUp() {
		global $wgContLang;
		parent::setUp();

		// Define a Draft NS unless there already is one.
		$draftNsId = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'PageTriageDraftNamespaceId' );
		if ( !$draftNsId ) {
			$this->setMwGlobals( [
				'wgExtraNamespaces' => [ $this->draftNsId => 'Draft' ],
				'wgPageTriageDraftNamespaceId' => $this->draftNsId,
			] );
			// Clear NS caches.
			MWNamespace::clearCaches();
			$wgContLang->resetNamespaces();
		} else {
			$this->draftNsId = $draftNsId;
		}
	}

	/**
	 * Creating a page in Draft namespace adds it to the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testCreateDraftPage() {
		// Get initial queue length, for comparison.
		$originalList = $this->doApiRequest(
			[ 'action' => 'pagetriagelist', 'showunreviewed' => '1' ]
		);
		$originalPagesCount = count( $originalList[0]['pagetriagelist']['pages'] );

		// If we don't ask for it, a draft page shouldn't be returned.
		$this->insertPage( Title::newFromText( 'Draft:Test page 1' ) );
		$list1 = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'showunreviewed' => '1' ] );
		$this->assertCount( $originalPagesCount, $list1[0]['pagetriagelist']['pages'] );

		// Request the Draft namespace.
		$list2 = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		] );
		$this->assertArraySubset(
			[ 'title' => 'Draft:Test page 1' ],
			$list2[0]['pagetriagelist']['pages'][0]
		);
	}

	/**
	 * Moving an existing page to the Draft namespace adds it to the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testMoveToDraftPage() {
		// Get the initial queue count.
		$list1 = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		] );
		$originalPagesCount = count( $list1[0]['pagetriagelist']['pages'] );

		// Move the page from mainspace to Draft.
		$from = Title::newFromText( 'Test page 2' );
		$to = Title::newFromText( 'Draft:Test page 2' );
		$this->insertPage( $from );
		$movePage = new MovePage( $from, $to );
		$movePage->move( $this->getTestUser()->getUser(), '', false );

		// Check that the moved page is in the queue of unreviewed pages.
		$list2 = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		] );
		$this->assertCount(
			$originalPagesCount + 1,
			$list2[0]['pagetriagelist']['pages']
		);
		$this->assertArraySubset(
			[ 'title' => 'Draft:Test page 2' ],
			$list2[0]['pagetriagelist']['pages'][0]
		);
	}

	/**
	 * Moving a page out of the Draft namespace removes it from the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testMoveFromDraftPage() {
		// Add a page to the Draft namespace.
		$from = Title::newFromText( 'Draft:Test page 3' );
		$to = Title::newFromText( 'Mainspace page 3' );
		$this->insertPage( $from );

		// Get the queue count.
		$list1 = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		] );
		$originalPagesCount = count( $list1[0]['pagetriagelist']['pages'] );

		// Move the page to mainspace.
		$movePage = new MovePage( $from, $to );
		$movePage->move( $this->getTestUser()->getUser(), '', false );

		// Check that the queue has decremented by one.
		$list2 = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		] );
		$this->assertEquals(
			$originalPagesCount - 1,
			count( $list2[0]['pagetriagelist']['pages'] )
		);
	}

}
