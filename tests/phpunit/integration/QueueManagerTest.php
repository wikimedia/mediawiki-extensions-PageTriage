<?php

namespace MediaWiki\Extension\PageTriage\Test\Integration;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\QueueManager;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageTriage\QueueManager
 * @group Database
 */
class QueueManagerTest extends MediaWikiIntegrationTestCase {

	/** @inheritDoc */
	protected $tablesUsed = [ 'pagetriage_page', 'pagetriage_page_tags', 'pagetriage_tags', 'page' ];

	public function setUp(): void {
		parent::setUp();
		// Clear queue entries that were automatically added on page creation in parent::setUp().
		$this->db->truncate( 'pagetriage_page' );
		$this->db->truncate( 'pagetriage_page_tags' );
		$this->db->truncate( 'pagetriage_tags' );
		$this->db->insert(
			'pagetriage_tags', [
				// Add a single tag, so metadata is compiled.
				// TODO: Add a convenience method for populating this in the test DB?
				[ 'ptrt_tag_name' => 'linkcount', 'ptrt_tag_desc' => 'Number of inbound links' ]
			]
		);
	}

	public function testDeleteNonexistentPage() {
		$status = $this->getQueueManager()->deleteByPageId( -1 );
		$this->assertFalse( $status->isOK() );
	}

	public function testDeleteExistentPage() {
		$page = $this->insertPage( 'PageTriageTest', '' )['title'];
		$this->assertSame( 1,
			$this->db->newSelectQueryBuilder()
				->select( '*' )
				->from( 'pagetriage_page' )
				->fetchRowCount()
		);
		$acp = ArticleCompileProcessor::newFromPageId( [ $page->getId() ] );
		$acp->compileMetadata();
		$this->assertSame( 1,
			$this->db->newSelectQueryBuilder()
				->select( '*' )
				->from( 'pagetriage_page_tags' )
				->fetchRowCount()
		);
		$status = $this->getQueueManager()->deleteByPageId( $page->getId() );
		$this->assertTrue( $status->isOK() );
		$this->assertSame( 0,
			$this->db->newSelectQueryBuilder()
				->select( '*' )
				->from( 'pagetriage_page' )
				->fetchRowCount()
		);
		$this->assertSame( 0,
			$this->db->newSelectQueryBuilder()
				->select( '*' )
				->from( 'pagetriage_page_tags' )
				->fetchRowCount()
		);
	}

	public function testIsPageTriageNamespace() {
		$this->overrideConfigValue( 'PageTriageNamespaces', [ 0, 2 ] );
		$this->assertFalse( $this->getQueueManager()->isPageTriageNamespace( -1 ) );
		$this->assertTrue( $this->getQueueManager()->isPageTriageNamespace( 0 ) );
		$this->overrideConfigValue( 'PageTriageDraftNamespaceId', 42 );
		$this->assertTrue( $this->getQueueManager()->isPageTriageNamespace( 42 ) );
	}

	private function getQueueManager(): QueueManager {
		return new QueueManager(
			$this->getServiceContainer()->getDBLoadBalancerFactory()
		);
	}

}
