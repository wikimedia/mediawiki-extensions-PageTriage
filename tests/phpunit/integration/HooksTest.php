<?php

namespace MediaWiki\Extension\PageTriage\Test;

use CommentStoreComment;
use ContentHandler;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use RecentChange;

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

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageSaveComplete()
	 */
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

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageDeleteComplete()
	 */
	public function testOnPageDelete() {
		$title = Title::newFromText( 'Delete me' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$content = ContentHandler::makeContent( 'Delete this article', $title );
		$comment = CommentStoreComment::newUnsavedComment( 'Comment' );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->setRcPatrolStatus( RecentChange::PRC_PATROLLED );
		$updater->saveRevision( $comment );

		$beforeDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $beforeDeleteCount );

		$deletePage = $this->getServiceContainer()
					 ->getDeletePageFactory()
					 ->newDeletePage( $page, $this->getTestSysop()->getUser() );
		$delStatus = $deletePage->deleteIfAllowed( 'reason' );
		$this->assertTrue( $delStatus->isGood() );

		$afterDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 0, $afterDeleteCount );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageUndeleteComplete()
	 */
	public function testOnPageUndelete() {
		$title = Title::newFromText( 'Undelete me' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$sysOp = $this->getTestSysop()->getUser();
		$content = ContentHandler::makeContent( 'Undelete this article', $title );
		$comment = CommentStoreComment::newUnsavedComment( 'Comment' );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->setRcPatrolStatus( RecentChange::PRC_PATROLLED );
		$updater->saveRevision( $comment );

		$beforeDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $beforeDeleteCount );

		// Delete this article
		$deletePage = $this->getServiceContainer()
			->getDeletePageFactory()
			->newDeletePage( $page, $sysOp );
		$delStatus = $deletePage->deleteIfAllowed( 'reason' );
		$this->assertTrue( $delStatus->isGood() );

		$afterDeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 0, $afterDeleteCount );

		// Undelete the article
		$undeletePage = $this->getServiceContainer()
			->getUndeletePageFactory()
			->newUndeletePage( $page, $sysOp );
		$undelStatus = $undeletePage->undeleteIfAllowed( 'reason' );
		$this->assertTrue( $undelStatus->isGood() );

		$afterUndeleteCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $afterUndeleteCount );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::updateMetadataOnBlockChange
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onBlockIpComplete
	 */
	public function testBlockUserAndUpdateMetadata() {
		$user = $this->getMutableTestUser();
		$this->assertTrue( (bool)$user->getUser()->getId(), 'User successfully created' );

		$this->assertFalse( (bool)$user->getUser()->getBlock(), 'User is not blocked' );

		$pageId = $this->makeDraft( __METHOD__, false, false, $user->getUser() );
		$this->assertTrue( (bool)$pageId, 'Page successfully created' );

		$metadata = ArticleMetadata::getMetadataForArticles( [ $pageId ] );
		$this->assertNotEmpty( $metadata, 'PageTriage page metadata exists (1)' );

		$this->assertSame(
			'0',
			$metadata[ $pageId ]['user_block_status'],
			'Page author is not marked as blocked in PageTriage system'
		);

		$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$user->getUser(),
			$this->getTestSysop()->getAuthority(),
			'infinity',
			'test block'
		)->placeBlock();
		$block = $user->getUser()->getBlock();
		$this->assertTrue( (bool)$block, 'User successfully blocked' );

		$this->assertTrue( (bool)$user->getUser()->getBlock(), 'User is blocked' );

		$metadata = ArticleMetadata::getMetadataForArticles( [ $pageId ] );
		$this->assertNotEmpty( $metadata, 'PageTriage page metadata exists (2)' );

		$this->assertSame(
			'1',
			$metadata[ $pageId ]['user_block_status'],
			'Page author is marked as blocked in PageTriage system'
		);
	}
}
