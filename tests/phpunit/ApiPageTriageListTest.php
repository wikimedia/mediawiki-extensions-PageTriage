<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiUsageException;
use ContentHandler;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileAfcTag;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MockHttpTrait;

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageList
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 */
class ApiPageTriageListTest extends PageTriageTestCase {

	use MockHttpTrait;

	/** @var int */
	protected $draftNsId = 150;

	/**
	 * Setup draft namespace, set up tables.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->setUpForOresCopyvioTests();
	}

	/**
	 * Creating a page in Draft namespace adds it to the queue.
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
		$this->assertArrayHasKey( 'title', $list2[0] );
		$this->assertSame( 'Draft:Test page 1', $list2[0][ 'title' ] );
	}

	/**
	 * Adding/changing AfC categories.
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
		$pageTags = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->where( [ 'ptrpt_page_id' => $page['id'] ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals( 2, $pageTags->numRows() );
		$this->assertEquals( ArticleCompileAfcTag::DECLINED, $pageTags->current()->ptrpt_value );

		// Request the declined drafts.
		$list2 = $this->getPageTriageList( $apiParams );

		$this->assertArrayHasKey( 'title', $list2[0] );
		$this->assertSame( 'Draft:AfC test page', $list2[0][ 'title' ] );

		// Move the page out of the declined category, and it disappears from the list.
		$this->insertPage( 'AfC test page', '[[category:nop]]', $this->draftNsId );
		$list3 = $this->getPageTriageList( $apiParams );
		$this->assertCount( 0, $list3 );
	}

	/**
	 * Test cases where the user requests a afc page with a specific submission type
	 */
	public function testAfcTagsAndThatFilter() {
		$user = self::getTestUser()->getUser();
		$apiParams = [
			'afc_state' => ArticleCompileAfcTag::DECLINED,
			'recreated' => 1
		];

		$this->insertPage( 'Normal Unsubmitted Page', '', $this->draftNsId );
		$this->insertPage( 'Declined Afc Page', '[[Category:Declined AfC submissions]]', $this->draftNsId );

		$recreatedPage = $this->insertPage( 'Recreated declined afc submission', 'some stuff', $this->draftNsId );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $recreatedPage['title'] );

		$this->deletePage( $page, 'Test', $user );

		$recreatedPage = $this->insertPage(
			'Recreated declined afc submission',
			'[[Category:Declined AfC submissions]]',
			$this->draftNsId
		);

		$list = $this->getPageTriageList( $apiParams );

		$this->assertCount( 1, $list );
		$this->assertSame( 'Draft:Recreated declined afc submission', $list[0]['title'] );
	}

	/**
	 * When there are multiple AfC categories on the page.
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
		$this->assertArrayHasKey( 'title', $list[0] );
		$this->assertSame( 'Draft:AfC test page', $list[0][ 'title' ] );

		// Should still be in Pending feed if categories are in the opposite order.
		// Also Declined category should be ignored, since Pending has higher priority.
		$this->insertPage(
			'AfC test page',
			'[[Category:Declined AfC submissions]][[Category:Pending AfC submissions being reviewed now]]'
				. '[[Category:Pending AfC submissions]]',
			$this->draftNsId
		);
		$list = $this->getPageTriageList( [ 'afc_state' => ArticleCompileAfcTag::UNDER_REVIEW ] );
		$this->assertArrayHasKey( 'title', $list[0] );
		$this->assertSame( 'Draft:AfC test page', $list[0][ 'title' ] );
	}

	/**
	 * Getting unsubmitted drafts (not in related category).
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
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageMoveComplete()
	 */
	public function testMoveToDraftPage() {
		// Get the initial queue count.
		$originalPagesCount = count( $this->getPageTriageList() );

		// Move the page from mainspace to Draft.
		$from = Title::newFromText( 'Test page 3' );
		$to = Title::newFromText( 'Draft:Test page 3' );
		$this->insertPage( $from );

		MediaWikiServices::getInstance()
			->getMovePageFactory()
			->newMovePage( $from, $to )
			->move( static::getTestUser()->getUser(), '', false );

		// Check that the moved page is in the queue of unreviewed pages.
		$list = $this->getPageTriageList();
		$this->assertCount( $originalPagesCount + 1, $list );
		$this->assertArrayHasKey( 'title', $list[0] );
		$this->assertSame( 'Draft:Test page 3', $list[0][ 'title' ] );
	}

	/**
	 * Moving a page out of the Draft namespace removes it from the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageMoveComplete()
	 */
	public function testMoveFromDraftPage() {
		// Add a page to the Draft namespace.
		$from = Title::newFromText( 'Draft:Test page 4' );
		$to = Title::newFromText( 'Mainspace page 4' );
		$this->insertPage( $from );

		// Get the queue count.
		$originalPagesCount = count( $this->getPageTriageList() );

		// Move the page to mainspace.
		MediaWikiServices::getInstance()
			->getMovePageFactory()
			->newMovePage( $from, $to )
			->move( static::getTestUser()->getUser(), '', false );

		// Check that the queue has decremented by one.
		$this->assertCount(
			$originalPagesCount - 1,
			$this->getPageTriageList()
		);
	}

	/**
	 * Make sure mainspace pages by autopatrolled users are marked as reviewed and vice versa.
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::addToPageTriageQueue()
	 */
	public function testAutopatrolledCreation() {
		$apiParams = [ 'namespace' => 0 ];

		$this->insertPage( 'Mainspace test page 1', '' );

		// Should not be in unreviewed list (test user is a sysop and hence autopatrolled).
		$list = $this->getPageTriageList( $apiParams );

		// First check count($list) in case this test is ran standalone.
		$this->assertTrue( count( $list ) === 0 || $list[0]['title'] !== 'Mainspace test page 1' );

		// Create another page using a non-autopatrolled user.
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Mainspace test page 2', '', 0, $user );

		// Test page 2 *should* be in the queue (and at the top since it's the most recent).
		$list = $this->getPageTriageList( $apiParams );
		$this->assertEquals( 'Mainspace test page 2', $list[0]['title'] );
	}

	/**
	 * Make sure articles created from redirects are added to the queue.
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onRevisionFromEditComplete()
	 */
	public function testArticlesFromRedirects() {
		$apiParams = [ 'namespace' => 0 ];
		$pageTitle = 'Redirect test';

		$this->insertPage( $pageTitle, '#REDIRECT [[Foo]]' );

		// Should not be in unreviewed list (test user is a sysop and hence autopatrolled).
		$list = $this->getPageTriageList( $apiParams );
		$this->assertTrue( count( $list ) === 0 || $list[0]['title'] !== $pageTitle );

		// Turn the redirect into an article using a non-autopatrolled user.
		$user = static::getTestUser()->getUser();
		$this->editPage( $pageTitle, 'My new article', '', 0, $user );

		// [[Redirect test]] should now be in the queue (and at the top since it's the most recent).
		$list = $this->getPageTriageList( $apiParams );
		$this->assertEquals( $pageTitle, $list[0]['title'] );
	}

	/**
	 * Verify that endpoint-specific API params are defined properly.
	 *
	 * @throws ApiUsageException
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::getCommonApiParams()
	 */
	public function testApiParamsByEndpoint() {
		// Test invalid params to PageTriageList.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'topreviewers' => '1',
		] );
		$this->assertEquals( 'Unrecognized parameter: topreviewers.',
			$response[0]['warnings']['main']['warnings'] );
		// Test valid param to PageTriageList.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagelist',
			'offset' => '56789',
		] );
		$this->assertArrayNotHasKey( 'warnings', $response[0] );
	}

	/**
	 * Sorting drafts by submission date or date of decline.
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
			$this->assertEquals( $originalTopPage, $list[0] );
		} else {
			$this->assertArrayHasKey( 'title', $list[0] );
			$this->assertSame( 'Draft:Test page 5', $list[0][ 'title' ] );
		}

		// Manually set the reviewed at attribute to something really old.
		$this->db->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_reviewed_updated' => '20010115000000' ] )
			->where( [ 'ptrp_page_id' => $page['id'] ] )
			->caller( __METHOD__ )
			->execute();

		// Insert another draft in a relevant category.
		$this->insertPage( 'Draft:Test page 6', '[[Category:Pending AfC submissions]]',
			$this->draftNsId
		);

		// 'Test page 5' should be the oldest.
		$list = $this->getPageTriageList( $apiParams );
		$this->assertArrayHasKey( 'title', $list[0] );
		$this->assertSame( 'Draft:Test page 5', $list[0][ 'title' ] );
	}

	public function testQueryOres() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Test page ores 1', 'some content', $this->draftNsId, $user );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Test page ores 1', $this->draftNsId ) );
		$rev1 = $page->getLatest();

		$this->insertPage( 'Test page ores 2', 'some content', $this->draftNsId, $user );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Test page ores 2', $this->draftNsId ) );
		$rev2 = $page->getLatest();

		$this->db->insert( 'ores_classification', [
			'oresc_model' => $this->ensureOresModel( 'articlequality' ),
			'oresc_probability' => 0.4,
			'oresc_rev' => $rev1,
			'oresc_class' => 1,
			'oresc_is_predicted' => 1,
		] );

		$this->setDraftQuality( $rev2, 2 );

		$list = $this->getPageTriageList();
		$this->assertGreaterThan( 1, count( $list ) );

		$list = $this->getPageTriageList( [ 'show_predicted_class_c' => true ] );
		$this->assertCount( 1, $list );
		$this->assertEquals( 'Draft:Test page ores 1', $list[0]['title'] );

		$list = $this->getPageTriageList( [ 'show_predicted_issues_spam' => true ] );
		$this->assertCount( 1, $list );
		$this->assertEquals( 'Draft:Test page ores 2', $list[0]['title'] );
	}

	public function testQueryOresBoundaries() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$user = static::getTestUser()->getUser();
		$this->insertPage( 'Test page ores 3', 'some content', $this->draftNsId, $user );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Test page ores 3', $this->draftNsId ) );
		$rev1 = $page->getLatest();

		$this->db->insert( 'ores_classification', [
			'oresc_model' => $this->ensureOresModel( 'articlequality' ),
			'oresc_probability' => 0.5,
			'oresc_rev' => $rev1,
			'oresc_class' => 1,
			'oresc_is_predicted' => 1,
		] );
		$this->ensureOresModel( 'draftquality' );

		$list = $this->getPageTriageList();
		$this->assertCount( 1, $list, 'no filters' );

		$list = $this->getPageTriageList( [ 'show_predicted_class_b' => true ] );
		$this->assertCount( 1, $list, 'show class B' );
		$this->assertEquals( 'Draft:Test page ores 3', $list[0]['title'] );
		$this->assertEquals( 'B-class', $list[0]['ores_articlequality'] );

		$list = $this->getPageTriageList( [ 'show_predicted_class_c' => true ] );
		$this->assertCount( 0, $list, 'show class C ' );
	}

	public function testQueryOresCopyvio() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		foreach ( [ 'pagetriage_page', 'page' ] as $table ) {
			$this->db->newDeleteQueryBuilder()
				->deleteFrom( $table )
				->where( '1 = 1' )
				->caller( __METHOD__ )
				->execute();
		}
		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$this->ensureOresModel( 'draftquality' );
		$this->ensureOresModel( 'articlequality' );
		$this->ensureCopyvioTag();

		// DraftQuality: N/A
		$this->makePage( 'Page001' );
		// DraftQuality: OK
		$this->makePage( 'Page002', 1 );
		// DraftQuality: SPAM
		$this->makePage( 'Page003', 2 );
		// DraftQuality: N/A, Copyvio
		$this->makePage( 'Page004', false, true );
		// DraftQuality: OK, Copyvio
		$this->makePage( 'Page005', 1, true );
		// DraftQuality: SPAM, Copyvio
		$this->makePage( 'Page006', 2, true );

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

	public function testFilterDateRange() {
		$user = self::getTestUser()->getUser();
		$page1 = $this->insertPage( 'DateRange20190215', 'Testing Date Range I', 0, $user );
		$page2 = $this->insertPage( 'DateRange20190715', 'Testing Date Range II', 0, $user );

		// Manually set the created at attribute to older dates.
		$this->db->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_created' => '20190215000000' ] )
			->where( [ 'ptrp_page_id' => $page1['id'] ] )
			->caller( __METHOD__ )
			->execute();

		$this->db->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_created' => '20190715233000' ] )
			->where( [ 'ptrp_page_id' => $page2['id'] ] )
			->caller( __METHOD__ )
			->execute();

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
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $otherPage['title'] );

		$this->deletePage( $page, 'Test', $user );

		$this->insertPage( 'PageOther', 'some content', 0, $user );

		$list = $this->getPageTriageList( [
			'namespace' => 0,
			'recreated' => 1,
		] );
		$this->assertPages( [ 'PageOther' ], $list,
			'Mainspace pages that were previously deleted' );
	}

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
	 */
	public function testTalkpageFeedbackCount() {
		// Create a test page.
		$testPageTitle = 'Article talkpage message test';
		$testPage = $this->insertPage( $testPageTitle, '' );

		// Check that it's in the queue and does not have any talk page message.
		$this->installMockHttp( $this->makeFakeHttpRequest( $this->getFakeOresScores() ) );
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testPage['id'] ] );
		$pageInfo = $list[0]['pagetriagelist']['pages'][0];
		$this->assertArrayHasKey( 'talkpage_feedback_count', $pageInfo );
		$this->assertSame( 0, $pageInfo['talkpage_feedback_count'] );

		// Add two messages to the talkpage. This is done via MessagePoster in the front end usually,
		// so we don't have a PageTriage PHP method to use here.
		$talkPageTitle = Title::newFromText( $testPageTitle, NS_TALK );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $talkPageTitle );
		$page->doUserEditContent(
			ContentHandler::makeContent( 'Test message.', $talkPageTitle ),
			static::getTestSysop()->getUser(),
			'edit summary',
			0,
			false,
			[ 'pagetriage' ]
		);
		$page->doUserEditContent(
			ContentHandler::makeContent( 'Test message 2.', $talkPageTitle ),
			static::getTestSysop()->getUser(),
			'edit summary',
			0,
			false,
			[ 'pagetriage' ]
		);

		// Retrieve the page's metadata again, and check that the talkpage feedback is flagged.
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testPage['id'] ] );
		$pageInfo = $list[0]['pagetriagelist']['pages'][0];
		$this->assertSame( 2, $pageInfo['talkpage_feedback_count'] );

		// Now the same, for a draft page
		$testDraftTitle = 'Draft talkpage message test';
		$testDraft = $this->insertPage( $testDraftTitle, '', $this->draftNsId );

		// Check that it's in the queue and does not have any talk page message.
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testDraft['id'] ] );
		$draftInfo = $list[0]['pagetriagelist']['pages'][0];
		$this->assertArrayHasKey( 'talkpage_feedback_count', $draftInfo );
		$this->assertSame( 0, $draftInfo['talkpage_feedback_count'] );

		// Add one message to the talkpage. This is done via MessagePoster in the front end usually,
		// so we don't have a PageTriage PHP method to use here.
		$draftTalkTitle = $testDraft['title']->getTalkPageIfDefined();
		$draft = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $draftTalkTitle );
		$draft->doUserEditContent(
			ContentHandler::makeContent( 'Test message.', $draftTalkTitle ),
			static::getTestSysop()->getUser(),
			'edit summary',
			0,
			false,
			[ 'pagetriage' ]
		);

		// Retrieve the page's metadata again, and check that the talkpage feedback is flagged.
		$list = $this->doApiRequest( [ 'action' => 'pagetriagelist', 'page_id' => $testDraft['id'] ] );
		$draftInfo = $list[0]['pagetriagelist']['pages'][0];
		$this->assertSame( 1, $draftInfo['talkpage_feedback_count'] );
	}
}
