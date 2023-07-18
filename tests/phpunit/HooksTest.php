<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use PageArchive;

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

	public function testOnPageDelete() {
		$title = Title::newFromText( 'Delete me' );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$page->doUserEditContent( ContentHandler::makeContent( 'Delete this article', $title ), $user, 'Comment' );

		$beforeDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $beforeDeleteCount );

		$page->doDeleteArticleReal( 'Reason', $this->getTestSysop()->getUser() );

		$afterDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 0, $afterDeleteCount );
	}

	public function testOnArticleUndelete() {
		$title = Title::newFromText( 'Undelete me' );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$page->doUserEditContent( ContentHandler::makeContent( 'Undelete this article', $title ), $user, 'Comment' );

		$beforeDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $beforeDeleteCount );

		// Delete this article
		$page->doDeleteArticleReal( 'Reason', $this->getTestSysop()->getUser() );

		$afterDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 0, $afterDeleteCount );

		// Undelete the article
		$archive = new PageArchive( $title );
		$archive->undeleteAsUser( [], $this->getTestSysop()->getUser() );

		$afterUndeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			// After undeleting, page ID changes; we need to get the next page created
			->where( [ 'ptrp_page_id' => $page->getId() + 1 ] )
			->fetchRowCount();
		$this->assertSame( 1, $afterUndeleteCount );
	}

}
