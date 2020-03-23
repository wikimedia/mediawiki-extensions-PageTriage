<?php

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileAfcTag;

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 */
class ApiPageTriageListTest extends PageTriageTestCase {

	/** @var int */
	protected $draftNsId = 150;

	/**
	 * Setup draft namespace, set up tables.
	 */
	public function setUp() : void {
		global $wgHooks;
		parent::setUp();
		$this->setUpForOresCopyvioTests( $wgHooks );
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
		static::assertCount( $originalPagesCount, $list1[0]['pagetriagelist']['pages'] );

		// Request the Draft namespace.
		$list2 = $this->getPageTriageList();
		static::assertArraySubset(
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
		static::assertCount( 0, $list1 );

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
		static::assertEquals( 2, $pageTags->numRows() );
		static::assertEquals( ArticleCompileAfcTag::DECLINED, $pageTags->current()->ptrpt_value );

		// Request the declined drafts.
		$list2 = $this->getPageTriageList( $apiParams );
		static::assertArraySubset(
			[ 'title' => 'Draft:AfC test page' ],
			$list2[0]
		);

		// Move the page out of the declined category, and it disappears from the list.
		$this->insertPage( 'AfC test page', '[[category:nop]]', $this->draftNsId );
		$list3 = $this->getPageTriageList( $apiParams );
		static::assertCount( 0, $list3 );
	}

	/**
	 * When there are multiple AfC categories on the page.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 */
	public function testMultiAfcCategories() {
		// Insert pending and under review categories
		$this->insertPage(
			'AfC test page',
			'[[Category:Pending AfC submissions]][[Category:Pending AfC submissions being reviewed now]]',
			$this->draftNsId
		);
		// Should be in the Pending feed.
		$list = $this->getPageTriageList( [ 'afc_state' => ArticleCompileAfcTag::UNDER_REVIEW ] );
		static::assertArraySubset(
			[ 'title' => 'Draft:AfC test page' ],
			$list[0]
		);

		// Should still be in Pending feed if categories are in the opposite order.
		// Also Declined category should be ignored, since Pending has higher priority.
		$this->insertPage(
			'AfC test page',
			'[[Category:Declined AfC submissions]][[Category:Pending AfC submissions being reviewed now]]'
				. '[[Category:Pending AfC submissions]]',
			$this->draftNsId
		);
		$list = $this->getPageTriageList( [ 'afc_state' => ArticleCompileAfcTag::UNDER_REVIEW ] );
		static::assertArraySubset(
			[ 'title' => 'Draft:AfC test page' ],
			$list[0]
		);
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
		static::assertCount(
			$originalUnsubmittedCount + 1,
			$this->getPageTriageList( $apiParams )
		);

		// Add valid category.
		$this->insertPage( 'Test page 2', '[[Category:Declined AfC submissions]]',
			$this->draftNsId
		);

		// Should now be back to the original count.
		static::assertCount(
			$originalUnsubmittedCount,
			$this->getPageTriageList( $apiParams )
		);

		// Remove the category and the page should once again be 'unsubmitted'.
		$this->insertPage( 'Test page 2', '[[Category:Nop]]', $this->draftNsId );
		static::assertCount(
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
		$movePage->move( static::getTestUser()->getUser(), '', false );

		// Check that the moved page is in the queue of unreviewed pages.
		$list = $this->getPageTriageList();
		static::assertCount( $originalPagesCount + 1, $list );
		static::assertArraySubset(
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
		$movePage->move( static::getTestUser()->getUser(), '', false );

		// Check that the queue has decremented by one.
		static::assertEquals(
			$originalPagesCount - 1,
			count( $this->getPageTriageList() )
		);
	}

	/**
	 * Make sure mainspace pages by autopatrolled users are marked as reviewed and vice versa.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::addToPageTriageQueue()
	 */
	public function testAutopatrolledCreation() {
		$apiParams = [ 'namespace' => 0 ];

		$this->insertPage( 'Mainspace test page 1', '' );

		// Should not be in unreviewed list (test user is a sysop and hence autopatrolled).
		$list = $this->getPageTriageList( $apiParams );

		// First check count($list) in case this test is ran standalone.
		static::assertTrue( count( $list ) === 0 || $list[0]['title'] !== 'Mainspace test page 1' );

		// Create another page using a non-autopatrolled user.
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Mainspace test page 2', '', 0, $user );

		// Test page 2 *should* be in the queue (and at the top since it's the most recent).
		$list = $this->getPageTriageList( $apiParams );
		static::assertEquals( 'Mainspace test page 2', $list[0]['title'] );
	}

	/**
	 * Make sure articles created from redirects are added to the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onNewRevisionFromEditComplete()
	 */
	public function testArticlesFromRedirects() {
		$apiParams = [ 'namespace' => 0 ];
		$pageTitle = 'Redirect test';

		$this->insertPage( $pageTitle, '#REDIRECT [[Foo]]' );

		// Should not be in unreviewed list (test user is a sysop and hence autopatrolled).
		$list = $this->getPageTriageList( $apiParams );
		static::assertTrue( count( $list ) === 0 || $list[0]['title'] !== $pageTitle );

		// Turn the redirect into an article using a non-autopatrolled user.
		$user = static::getTestUser()->getUser();
		$this->editPage( $pageTitle, 'My new article', '', 0, $user );

		// [[Redirect test]] should now be in the queue (and at the top since it's the most recent).
		$list = $this->getPageTriageList( $apiParams );
		static::assertEquals( $pageTitle, $list[0]['title'] );
	}

	/**
	 * Verify that endpoint-specific API params are defined properly.
	 *
	 * @throws ApiUsageException
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::getCommonApiParams()
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getAllowedParams()
	 */
	public function testApiParamsByEndpoint() {
		// Test invalid params to PageTriageList.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'topreviewers' => '1',
		] );
		static::assertEquals( 'Unrecognized parameter: topreviewers.',
			$response[0]['warnings']['main']['warnings'] );
		// Test valid param to PageTriageList.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'offset' => '56789',
		] );
		static::assertArrayNotHasKey( 'warnings', $response[0] );
	}

	/**
	 * Sorting drafts by submission date or date of decline.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testSubmissionSorting() {
		$apiParams = [
			'dir' => 'oldestreview',
		];

		$originalTopPageList = $this->getPageTriageList( $apiParams );
		$originalTopPage = $originalTopPageList !== [] ? $originalTopPageList[0] : null;

		// New draft in a relevant category.
		$page = $this->insertPage( 'Test page 5', '[[Category:Declined AfC submissions]]',
			$this->draftNsId
		);

		// Original top page should still be the top (or the new one, if none existed beforehand).
		$list = $this->getPageTriageList( $apiParams );
		if ( $originalTopPage ) {
			static::assertEquals( $originalTopPage, $list[0] );
		} else {
			static::assertArraySubset(
				[ 'title' => 'Draft:Test page 5' ],
				$list[0]
			);
		}

		// Manually set the reviewed at attribute to something really old.
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'pagetriage_page',
			[ 'ptrp_reviewed_updated' => '20010115000000' ],
			[ 'ptrp_page_id' => $page['id'] ],
			__METHOD__
		);

		// Insert another draft in a relevant category.
		$this->insertPage( 'Draft:Test page 6', '[[Category:Pending AfC submissions]]',
			$this->draftNsId
		);

		// 'Test page 5' should be the oldest.
		static::assertArraySubset(
			[ 'title' => 'Draft:Test page 5' ],
			$this->getPageTriageList( $apiParams )[0]
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testQueryOres() {
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Test page ores 1', 'some content', $this->draftNsId, $user );
		$page = WikiPage::factory( Title::newFromText( 'Test page ores 1', $this->draftNsId ) );
		$rev1 = $page->getLatest();

		$this->insertPage( 'Test page ores 2', 'some content', $this->draftNsId, $user );
		$page = WikiPage::factory( Title::newFromText( 'Test page ores 2', $this->draftNsId ) );
		$rev2 = $page->getLatest();

		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert( 'ores_classification', [
			'oresc_model' => self::ensureOresModel( 'articlequality' ),
			'oresc_probability' => 0.4,
			'oresc_rev' => $rev1,
			'oresc_class' => 1,
			'oresc_is_predicted' => 1,
		] );

		self::setDraftQuality( $rev2, 2 );

		$list = $this->getPageTriageList();
		$this->assertGreaterThan( 1, count( $list ) );

		$list = $this->getPageTriageList( [ 'show_predicted_class_c' => true ] );
		$this->assertCount( 1, $list );
		$this->assertEquals( 'Draft:Test page ores 1', $list[0]['title'] );

		$list = $this->getPageTriageList( [ 'show_predicted_issues_spam' => true ] );
		$this->assertCount( 1, $list );
		$this->assertEquals( 'Draft:Test page ores 2', $list[0]['title'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testQueryOresBoundaries() {
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Test page ores 3', 'some content', $this->draftNsId, $user );
		$page = WikiPage::factory( Title::newFromText( 'Test page ores 3', $this->draftNsId ) );
		$rev1 = $page->getLatest();

		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert( 'ores_classification', [
			'oresc_model' => self::ensureOresModel( 'articlequality' ),
			'oresc_probability' => 0.5,
			'oresc_rev' => $rev1,
			'oresc_class' => 1,
			'oresc_is_predicted' => 1,
		] );
		self::ensureOresModel( 'draftquality' );

		$list = $this->getPageTriageList();
		$this->assertEquals( 1, count( $list ), 'no filters' );

		$list = $this->getPageTriageList( [ 'show_predicted_class_b' => true ] );
		$this->assertCount( 1, $list, 'show class B' );
		$this->assertEquals( 'Draft:Test page ores 3', $list[0]['title'] );
		$this->assertEquals( 'B-class', $list[0]['ores_articlequality'] );

		$list = $this->getPageTriageList( [ 'show_predicted_class_c' => true ] );
		$this->assertCount( 0, $list, 'show class C ' );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testQueryOresCopyvio() {
		$dbw = wfGetDB( DB_MASTER );
		foreach ( [ 'pagetriage_page', 'page' ] as $table ) {
			$dbw->delete( $table, '*' );
		}
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		self::ensureOresModel( 'draftquality' );
		self::ensureOresModel( 'articlequality' );
		self::ensureCopyvioTag();

		$this->makePage( 'Page001' ); // DraftQuality: N/A
		$this->makePage( 'Page002', 1 ); // DraftQuality: OK
		$this->makePage( 'Page003', 2 ); // DraftQuality: SPAM
		$this->makePage( 'Page004', false, true ); // DraftQuality: N/A, Copyvio
		$this->makePage( 'Page005', 1, true ); // DraftQuality: OK, Copyvio
		$this->makePage( 'Page006', 2, true ); // DraftQuality: SPAM, Copyvio

		$list = $this->getPageTriageList();
		$this->assertPages( [
			'Page001', 'Page002', 'Page003', 'Page004', 'Page005', 'Page006'
		], $list );

		$list = $this->getPageTriageList( [ 'show_predicted_issues_spam' => true ] );
		$this->assertPages( [ 'Page003', 'Page006' ], $list );

		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => true ] );
		$this->assertPages( [ 'Page004', 'Page005', 'Page006' ], $list );

		$list = $this->getPageTriageList( [ 'show_predicted_issues_none' => true ] );
		$this->assertPages( [ 'Page001', 'Page002' ], $list );

		$list = $this->getPageTriageList(
			[ 'show_predicted_issues_none' => true, 'show_predicted_issues_copyvio' => true ] );
		$this->assertPages( [ 'Page001', 'Page002', 'Page004', 'Page005', 'Page006' ], $list );

		$list = $this->getPageTriageList(
			[ 'show_predicted_issues_spam' => true, 'show_predicted_issues_copyvio' => true ] );
		$this->assertPages( [ 'Page003', 'Page004', 'Page005', 'Page006' ], $list );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testFilterDateRange() {
		$user = self::getTestUser()->getUser();
		$page1 = $this->insertPage( 'DateRange20190215', 'Testing Date Range I', 0, $user );
		$page2 = $this->insertPage( 'DateRange20190715', 'Testing Date Range II', 0, $user );

		// Manually set the created at attribute to older dates.
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'pagetriage_page',
			[ 'ptrp_created' => '20190215000000' ],
			[ 'ptrp_page_id' => $page1['id'] ],
			__METHOD__
		);
		$dbw->update( 'pagetriage_page',
			[ 'ptrp_created' => '20190715233000' ],
			[ 'ptrp_page_id' => $page2['id'] ],
			__METHOD__
		);

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'date_range_from' => '20190216000000'
		] );
		$this->assertPages( [ 'DateRange20190715' ], $list,
			'Pages to date' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'date_range_to' => '20190215235959'
		] );
		$this->assertPages( [ 'DateRange20190215' ], $list,
			'Pages to date' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'date_range_from' => '20190105000000',
			'date_range_to' => '20190714235959'
		] );
		$this->assertPages( [ 'DateRange20190215' ], $list,
			'Pages to date' );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testFilterType() {
		$user = self::getTestUser()->getUser();
		$otherPage = $this->insertPage( 'PageOther', 'some content', 0, $user );
		$this->insertPage( 'PageDel', '[[Category:Articles_for_deletion]]', 0, $user );
		$this->insertPage( 'PageRedir', '#REDIRECT [[Foo]]', 0, $user );
		$this->insertPage( 'PageRfD', '[[Category:All_redirects_for_discussion]]', 0, $user );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
		] );
		$this->assertPages( [ 'PageOther', 'PageDel', 'PageRedir', 'PageRfD' ], $list,
			'All pages (no type filter)' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showothers' => 1,
		] );
		$this->assertPages( [ 'PageOther' ], $list,
			'Others only' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
		] );
		$this->assertPages( [ 'PageDel', 'PageRfD' ], $list,
			'Nominated for deletion only' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
			'showothers' => 1,
		] );
		$this->assertPages( [ 'PageOther', 'PageDel', 'PageRfD' ], $list,
			'Nominated for deletion and all others' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showredirs' => 1,
		] );
		$this->assertPages( [ 'PageRedir' ], $list,
			'Redirects only' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showredirs' => 1,
			'showothers' => 1,
		] );
		$this->assertPages( [ 'PageOther', 'PageRedir' ], $list,
			'Redirects and all others' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
			'showredirs' => 1,
		] );
		$this->assertPages( [ 'PageDel', 'PageRedir', 'PageRfD' ], $list,
			'Nominated for deletion and Redirects' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
			'showredirs' => 1,
			'showothers' => 1,
		] );
		$this->assertPages( [ 'PageOther', 'PageDel', 'PageRedir', 'PageRfD' ], $list,
			'Nominated for deletion, Redirects and all others => no filtering' );

		// Delete PageOther, then recreate it.
		$page = WikiPage::factory( $otherPage['title'] );

		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$page->doDeleteArticle( 'Test' );
		} else {
			// Only in 1.35+ to use the new signature
			$page->doDeleteArticleReal( 'Test', $user );
		}

		$this->insertPage( 'PageOther', 'some content', 0, $user );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'recreated' => 1,
		] );
		$this->assertPages( [ 'PageOther' ], $list,
			'Mainspace pages that were previously deleted' );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getPageIds()
	 */
	public function testUndelete() {
		$user = self::getTestUser()->getUser();
		$this->insertPage( 'PageNormal', 'some content', 0, $user );
		$this->insertPage( 'PageDelUndel', '[[Category:Articles_for_deletion]]', 0, $user );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
		] );
		$this->assertPages( [ 'PageNormal', 'PageDelUndel' ], $list,
			'All pages' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
		] );
		$this->assertPages( [ 'PageDelUndel' ], $list,
			'Nominated for deletion only' );

		// This edit is removing the 'nominated for deletion' category
		$this->editPage( 'PageDelUndel', 'Ok, it can stay' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
		] );
		$this->assertPages( [], $list,
			'Nothing is Nominated for deletion' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showothers' => 1,
		] );
		$this->assertPages( [ 'PageNormal', 'PageDelUndel' ], $list,
			'All pages are normal now' );

		// This edit nominates it for deletion again
		$this->editPage( 'PageDelUndel', '[[Category:Articles_for_deletion]]' );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'showdeleted' => 1,
		] );
		$this->assertPages( [ 'PageDelUndel' ], $list,
			'Nominated for deletion only' );
	}

	/**
	 * The pagetriagelist API method should return a count of the pagetriage-tagged edits on an
	 * article's talk page.
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList::getTalkpageFeedbackCount
	 */
	public function testTalkpageFeedbackCount() {
		// Create a test page.
		$testPageTitle = 'Article talkpage message test';
		$testPage = $this->insertPage( $testPageTitle, '' );

		// Check that it's in the queue does not have any talk page message.
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testPage['id'] ] );
		$pageInfo = $list[0]['pagetriagelist']['pages'][0];
		static::assertArrayHasKey( 'talkpage_feedback_count', $pageInfo );
		static::assertSame( 0, $pageInfo['talkpage_feedback_count'] );

		// Add two messages to the talkpage. This is done via MessagePoster in the front end usually,
		// so we don't have a PageTriage PHP method to use here.
		$talkPageTitle = Title::newFromText( $testPageTitle, NS_TALK );
		$page = WikiPage::factory( $talkPageTitle );
		$page->doEditContent(
			ContentHandler::makeContent( 'Test message.', $talkPageTitle ), '', 0, false,
			static::getTestSysop()->getUser(), null, [ 'pagetriage' ]
		);
		$page->doEditContent(
			ContentHandler::makeContent( 'Test message 2.', $talkPageTitle ), '', 0, false,
			static::getTestSysop()->getUser(), null, [ 'pagetriage' ]
		);

		// Retrieve the page's metadata again, and check that the talkpage feedback is flagged.
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testPage['id'] ] );
		$pageInfo = $list[0]['pagetriagelist']['pages'][0];
		static::assertSame( 2, $pageInfo['talkpage_feedback_count'] );
	}
}
