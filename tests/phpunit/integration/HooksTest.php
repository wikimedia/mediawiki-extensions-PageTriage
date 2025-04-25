<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Extension\PageTriage\QueueRecord;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

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
		$originalCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();
		// Insert a redirect.
		$this->insertPage( 'Draft:Redirect test', '#REDIRECT [[Redirect test target]]' );
		// Check that it wasn't added to the queue.
		$actualCount = $this->getDb()->newSelectQueryBuilder()
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

		$beforeDeleteCount = $this->getDb()->newSelectQueryBuilder()
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

		$afterDeleteCount = $this->getDb()->newSelectQueryBuilder()
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

		$beforeDeleteCount = $this->getDb()->newSelectQueryBuilder()
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

		$afterDeleteCount = $this->getDb()->newSelectQueryBuilder()
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

		$afterUndeleteCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page->getId() ] )
			->fetchRowCount();
		$this->assertSame( 1, $afterUndeleteCount );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::onPageMoveComplete()
	 */
	public function testMoveShouldNotUnreviewArticle() {
		$title = Title::newFromText( 'Move me' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$content = ContentHandler::makeContent( 'Move this article', $title );
		$comment = CommentStoreComment::newUnsavedComment( 'Comment' );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->setRcPatrolStatus( RecentChange::PRC_PATROLLED );
		$updater->saveRevision( $comment );

		$pageTriage = new PageTriage( $page->getId() );
		$pageTriage->setTriageStatus( QueueRecord::REVIEW_STATUS_REVIEWED );

		// Move this article
		$movePage = $this->getServiceContainer()
			->getMovePageFactory()
			->newMovePage( $title, Title::newFromText( 'Move me to here' ) );
		$moveStatus = $movePage->moveIfAllowed( $user, 'move to a new title' );
		$this->assertTrue( $moveStatus->isGood() );

		$status = PageTriageUtil::getStatus( $page );

		$this->assertSame(
			QueueRecord::REVIEW_STATUS_REVIEWED,
			$status,
			'Page should be marked as reviewed'
		);
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
