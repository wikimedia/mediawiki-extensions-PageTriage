<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileAfcTag;

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 */
class ApiPageTriageListTest extends PageTriageTestCase {

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
		$list2 = $this->getPageTriageList();
		$this->assertArraySubset(
			[ 'title' => 'Draft:Test page 1' ],
			$list2[0]
		);
	}

	/**
	 * Adding/changing AfC categories.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testAfcTags() {
		$page = $this->insertPage( 'AfC test page', '', $this->draftNsId );
		$apiParams = [ 'afc_state' => ArticleCompileAfcTag::DECLINED ];

		// Initially there should be no declined drafts.
		$list1 = $this->getPageTriageList( $apiParams );
		$this->assertCount( 0, $list1 );

		// Add category.
		$this->insertPage( 'AfC test page', '[[Category:Declined AfC submissions]]',
			$this->draftNsId
		);

		// Check that the database was updated correctly (not really necessary?).
		$db = wfGetDB( DB_MASTER );
		$pageTags = $db->select( 'pagetriage_page_tags', '*',
			[ 'ptrpt_page_id' => $page['id'] ],
			__METHOD__
		);
		$this->assertEquals( 2, $pageTags->numRows() );
		$this->assertEquals( ArticleCompileAfcTag::DECLINED, $pageTags->current()->ptrpt_value );

		// Request the declined drafts.
		$list2 = $this->getPageTriageList( $apiParams );
		$this->assertArraySubset(
			[ 'title' => 'Draft:AfC test page' ],
			$list2[0]
		);

		// Move the page out of the declined category, and it disappears from the list.
		$this->insertPage( 'AfC test page', '[[category:nop]]', $this->draftNsId );
		$list3 = $this->getPageTriageList( $apiParams );
		$this->assertCount( 0, $list3 );
	}

	/**
	 * Getting unsubmitted drafts (not in related category).
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testUnsubmittedDrafts() {
		$apiParams = [ 'afc_state' => ArticleCompileAfcTag::UNSUBMITTED ];

		$originalUnsubmittedCount = count( $this->getPageTriageList( $apiParams ) );

		// Create a draft page.
		$this->insertPage( 'Test page 2', '', $this->draftNsId );

		// There should be one more unsubmitted draft.
		$this->assertCount(
			$originalUnsubmittedCount + 1,
			$this->getPageTriageList( $apiParams )
		);

		// Add valid category.
		$this->insertPage( 'Test page 2', '[[Category:Declined AfC submissions]]',
			$this->draftNsId
		);

		// Should now be back to the original count.
		$this->assertCount(
			$originalUnsubmittedCount,
			$this->getPageTriageList( $apiParams )
		);

		// Remove the category and the page should once again be 'unsubmitted'.
		$this->insertPage( 'Test page 2', '[[Category:Nop]]', $this->draftNsId );
		$this->assertCount(
			$originalUnsubmittedCount + 1,
			$this->getPageTriageList( $apiParams )
		);
	}

	/**
	 * Moving an existing page to the Draft namespace adds it to the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testMoveToDraftPage() {
		// Get the initial queue count.
		$originalPagesCount = count( $this->getPageTriageList() );

		// Move the page from mainspace to Draft.
		$from = Title::newFromText( 'Test page 3' );
		$to = Title::newFromText( 'Draft:Test page 3' );
		$this->insertPage( $from );
		$movePage = new MovePage( $from, $to );
		$movePage->move( $this->getTestUser()->getUser(), '', false );

		// Check that the moved page is in the queue of unreviewed pages.
		$list = $this->getPageTriageList();
		$this->assertCount( $originalPagesCount + 1, $list );
		$this->assertArraySubset(
			[ 'title' => 'Draft:Test page 3' ],
			$list[0]
		);
	}

	/**
	 * Moving a page out of the Draft namespace removes it from the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testMoveFromDraftPage() {
		// Add a page to the Draft namespace.
		$from = Title::newFromText( 'Draft:Test page 4' );
		$to = Title::newFromText( 'Mainspace page 4' );
		$this->insertPage( $from );

		// Get the queue count.
		$originalPagesCount = count( $this->getPageTriageList() );

		// Move the page to mainspace.
		$movePage = new MovePage( $from, $to );
		$movePage->move( $this->getTestUser()->getUser(), '', false );

		// Check that the queue has decremented by one.
		$this->assertEquals(
			$originalPagesCount - 1,
			count( $this->getPageTriageList() )
		);
	}

}
