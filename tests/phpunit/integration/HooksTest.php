<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\Hooks;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Extension\PageTriage\QueueRecord;
use MediaWiki\Page\Article;
use MediaWiki\Page\WikiPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MWTimestamp;
use ReflectionMethod;

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
		$title = Title::makeTitle( NS_MAIN, 'Delete me' );
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
		$this->assertStatusGood( $delStatus );

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
		$title = Title::makeTitle( NS_MAIN, 'Undelete me' );
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
		$this->assertStatusGood( $delStatus );

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
		$this->assertStatusGood( $undelStatus );

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
		$title = Title::makeTitle( NS_MAIN, 'Move me' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, [ 'edit', 'createpage', 'move' ] );
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
			->newMovePage( $title, Title::makeTitle( NS_MAIN, 'Move me to here' ) );
		$moveStatus = $movePage->moveIfAllowed( $user, 'move to a new title' );
		$this->assertStatusGood( $moveStatus );

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

		$pageId = $this->makeMainspacePage( __METHOD__, false, false, $user->getUser() );
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

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 */
	public function testShouldShowNoIndexForNewUnreviewedArticle() {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => true,
			'PageTriageMaxAge' => 90,
		] );
		$pageId = $this->makeMainspacePage( __METHOD__ );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$article = $this->createMock( Article::class );
		$article->method( 'getPage' )->willReturn( $page );
		$article->expects( $this->never() )->method( 'getParserOutput' );

		$this->assertTrue( $this->callShouldShowNoIndex( $article ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 */
	public function testShouldNotShowNoIndexForReviewedArticle() {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => true,
			'PageTriageMaxAge' => 90,
		] );
		$pageId = $this->makeMainspacePage( __METHOD__ );
		$pageTriage = new PageTriage( $pageId );
		$this->assertTrue( $pageTriage->setTriageStatus( QueueRecord::REVIEW_STATUS_REVIEWED ) );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$article = $this->newArticleMock( $page, false );

		$this->assertFalse( $this->callShouldShowNoIndex( $article ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 * @dataProvider provideUnreviewedArticleAge
	 */
	public function testShouldShowNoIndexForUnreviewedArticleAge( string $created, bool $expected ) {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => true,
			'PageTriageMaxAge' => 1,
		] );
		$pageId = $this->makeMainspacePage( __METHOD__ );
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_created' => $this->getDb()->timestamp( $created ) ] )
			->where( [ 'ptrp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$article = $this->newArticleMock( $page, false );
		MWTimestamp::setFakeTime( '20240102000000' );

		$this->assertSame( $expected, $this->callShouldShowNoIndex( $article ) );
	}

	public static function provideUnreviewedArticleAge() {
		return [
			'just below the age limit' => [ '20240101000001', true ],
			'at the age limit' => [ '20240101000000', false ],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 */
	public function testShouldNotShowNoIndexWhenCreationDateIsUnavailable() {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => false,
			'PageTriageMaxNoIndexAge' => 1,
		] );
		$pageId = $this->makeMainspacePage( __METHOD__ );
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$parserOutput = new ParserOutput();
		$parserOutput->setPageProperty( 'noindex', '' );
		$article = $this->newArticleMock( $page, $parserOutput );

		$this->assertFalse( $this->callShouldShowNoIndex( $article ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 * @dataProvider provideNoIndexWithoutExistingPage
	 */
	public function testShouldNotShowNoIndexWithoutExistingPage( ?bool $hasNoIndex ) {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => false,
		] );
		$parserOutput = $hasNoIndex === null ? false : new ParserOutput();
		if ( $hasNoIndex ) {
			$parserOutput->setPageProperty( 'noindex', '' );
		}
		$article = $this->newArticleMock( $this->newPageMock( 0 ), $parserOutput );

		$this->assertFalse( $this->callShouldShowNoIndex( $article ) );
	}

	public static function provideNoIndexWithoutExistingPage() {
		return [
			'without parser output' => [ null ],
			'without magic word' => [ false ],
			'magic word on page without ID' => [ true ],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldShowNoIndex()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForNewArticleReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::shouldNoIndexForMagicWordReasons()
	 * @covers \MediaWiki\Extension\PageTriage\Hooks::isNewEnoughToNoIndex()
	 */
	public function testShouldShowNoIndexForRecentArticleWithMagicWord() {
		$this->overrideConfigValues( [
			'PageTriageNoIndexUnreviewedNewArticles' => false,
			'PageTriageMaxNoIndexAge' => null,
		] );
		$pageId = $this->makeMainspacePage( __METHOD__ );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$parserOutput = new ParserOutput();
		$parserOutput->setPageProperty( 'noindex', '' );
		$article = $this->newArticleMock( $page, $parserOutput );

		$this->assertTrue( $this->callShouldShowNoIndex( $article ) );
	}

	private function newPageMock( int $pageId ): WikiPage {
		$page = $this->createMock( WikiPage::class );
		$page->method( 'getId' )->willReturn( $pageId );
		return $page;
	}

	private function newArticleMock( WikiPage $page, ParserOutput|false $parserOutput ): Article {
		$article = $this->createMock( Article::class );
		$article->method( 'getPage' )->willReturn( $page );
		$article->method( 'getParserOutput' )->willReturn( $parserOutput );
		return $article;
	}

	private function callShouldShowNoIndex( Article $article ): bool {
		$services = $this->getServiceContainer();
		$hooks = new Hooks(
			$services->getMainConfig(),
			$services->getRevisionLookup(),
			$services->getStatsFactory(),
			$services->getPermissionManager(),
			$services->getRevisionStore(),
			$services->getTitleFactory(),
			$services->getUserOptionsManager(),
			$services->getService( 'PageTriageQueueManager' ),
			$services->getWikiPageFactory(),
			$services->getService( 'PageTriageQueueLookup' ),
			$services->getUserFactory(),
			$services->getWANObjectCache()
		);
		$method = new ReflectionMethod( Hooks::class, 'shouldShowNoIndex' );
		return $method->invoke( $hooks, $article );
	}
}
