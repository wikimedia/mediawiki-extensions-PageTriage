<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageServices;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Extension\PageTriage\QueueRecord;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests that ?action=query&prop=isreviewed works
 *
 * @covers \MediaWiki\Extension\PageTriage\Api\ApiIsReviewed
 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::getStatus
 *
 * @group PageTriage
 * @group extensions
 * @group Database
 */
class ApiIsReviewedTest extends ApiTestCase {

	public function testUnreviewedPage() {
		$pageId = $this->insertPage(
			'testUnreviewedPage',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$this->assertNotNull( $wikipage, 'Page should exist' );

		$status = PageTriageUtil::getStatus( $wikipage );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_UNREVIEWED,
			$status,
			'Page should be marked as unreviewed'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => $pageId
		] );
		$this->assertFalse(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=# response should contain isreviewed=false'
		);
	}

	public function testReviewedPage() {
		$pageId = $this->insertPage(
			'testReviewedPage',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$this->assertNotNull( $wikipage, 'Page should exist' );

		$pageTriage = new PageTriage( $pageId );
		$pageTriage->setTriageStatus( QueueRecord::REVIEW_STATUS_REVIEWED );
		$status = PageTriageUtil::getStatus( $wikipage );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_REVIEWED,
			$status,
			'Page should be marked as reviewed'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => $pageId
		] );
		$this->assertTrue(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=# response should contain isreviewed=true'
		);
	}

	public function testPatrolledPage() {
		$pageId = $this->insertPage(
			'testPatrolledPage',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$this->assertNotNull( $wikipage, 'Page should exist' );

		$pageTriage = new PageTriage( $pageId );
		$pageTriage->setTriageStatus( QueueRecord::REVIEW_STATUS_PATROLLED );
		$status = PageTriageUtil::getStatus( $wikipage );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_PATROLLED,
			$status,
			'Page should be marked as patrolled'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => $pageId
		] );
		$this->assertTrue(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=# response should contain isreviewed=true'
		);
	}

	public function testAutopatrolledPage() {
		$pageId = $this->insertPage(
			'testAutopatrolledPage',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$this->assertNotNull( $wikipage, 'Page should exist' );

		$pageTriage = new PageTriage( $pageId );
		$pageTriage->setTriageStatus( QueueRecord::REVIEW_STATUS_AUTOPATROLLED );
		$status = PageTriageUtil::getStatus( $wikipage );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_AUTOPATROLLED,
			$status,
			'Page should be marked as autopatrolled'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => $pageId
		] );
		$this->assertTrue(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=# response should contain isreviewed=true'
		);
	}

	public function testPageNotInNewPagesFeed() {
		$pageId = $this->insertPage(
			'testPageNotInNewPagesFeed',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$this->assertNotNull( $wikipage, 'Page should exist' );

		$queueManager = PageTriageServices::wrap( $this->getServiceContainer() )
			->getQueueManager();
		$queueManager->deleteByPageIds( [ $pageId ] );
		$status = PageTriageUtil::getStatus( $wikipage );
		$this->assertNull(
			$status,
			'Page should not be in the new pages feed'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => $pageId
		] );
		$this->assertTrue(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=# response should contain isreviewed=true'
		);
	}

	public function testUnreviewedPages() {
		$pageId1 = $this->insertPage(
			'testUnreviewedPages1',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage1 = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId1 );
		$this->assertNotNull( $wikipage1, 'Page 1 should exist' );

		$pageId2 = $this->insertPage(
			'testUnreviewedPages2',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage2 = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId2 );
		$this->assertNotNull( $wikipage2, 'Page 2 should exist' );

		$status = PageTriageUtil::getStatus( $wikipage1 );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_UNREVIEWED,
			$status,
			'Page 1 should be marked as unreviewed'
		);

		$status = PageTriageUtil::getStatus( $wikipage2 );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_UNREVIEWED,
			$status,
			'Page 2 should be marked as unreviewed'
		);

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => "$pageId1|$pageId2"
		] );

		$this->assertFalse(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId1 ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=1|2 response should contain isreviewed=false for page 1'
		);

		$this->assertFalse(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId2 ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=1|2 response should contain isreviewed=false for page 2'
		);
	}

	public function testValidAndInvalidPages() {
		$pageId1 = $this->insertPage(
			'testValidAndInvalidPages_ValidPage',
			'Text',
			NS_MAIN,
			$this->getTestUser()->getUser()
		)[ 'id' ];
		$wikipage1 = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId1 );
		$this->assertNotNull( $wikipage1, 'Page 1 should exist' );

		$pageId2 = 46454555;
		$wikipage2 = $this->getServiceContainer()->getWikiPageFactory()->newFromId( $pageId2 );
		$this->assertNull( $wikipage2, 'Page 2 should not exist' );

		$status = PageTriageUtil::getStatus( $wikipage1 );
		$this->assertSame(
			QueueRecord::REVIEW_STATUS_UNREVIEWED,
			$status,
			'Page 1 should be marked as unreviewed'
		);

		// Can't run PageTriageUtil::getStatus() on page 2, since it is not a WikiPage object

		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed',
			'pageids' => "$pageId1|$pageId2"
		] );

		$this->assertFalse(
			$response[ 0 ][ 'query' ][ 'pages' ][ $pageId1 ][ 'isreviewed' ],
			'?action=query&prop=isreviewed&pages=1|2 response should contain isreviewed=false for page 1'
		);

		$this->assertFalse(
			isset( $response[ 0 ][ 'query' ][ 'pages' ][ $pageId2 ][ 'isreviewed' ] ),
			'?action=query&prop=isreviewed&pages=1|2 response should not contain isreviewed for page 2'
		);
	}

	public function testNoPageSelected() {
		$response = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'isreviewed'
		] );
		$this->assertFalse(
			isset( $response[ 0 ][ 'query' ][ 'pages' ] ),
			'?action=query&prop=isreviewed response should not contain any pages'
		);
	}
}
