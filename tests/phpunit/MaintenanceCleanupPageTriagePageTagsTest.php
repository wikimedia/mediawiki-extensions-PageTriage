<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriagePageTags;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the removeOldRows.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriagePageTags
 *
 * @group medium
 * @group Database
 */
class MaintenanceCleanupPageTriagePageTagsTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'pagetriage_page_tags', 'pagetriage_page', 'pagetriage_tags' ];
		// Delete any dangling page triage pages before inserting our test data
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page_tags' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_tags' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulCleanupPageTriagePageTags() {
		$dbr = PageTriageUtil::getReplicaConnection();
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbw->insert(
			'pagetriage_tags',
			[
				[ 'ptrt_tag_name' => 'afc_state', 'ptrt_tag_desc' => 'Desc' ],
				[ 'ptrt_tag_name' => 'user_name', 'ptrt_tag_desc' => 'Desc' ],
				[ 'ptrt_tag_name' => 'recreated', 'ptrt_tag_desc' => 'Desc' ]
			],
			__METHOD__,
		);

		$mainNsPage = $this->insertPage( 'Main123', 'Test 1', NS_MAIN );
		$acp = ArticleCompileProcessor::newFromPageId( [ $mainNsPage[ 'id' ] ] );
		$acp->compileMetadata();

		$countPageTriagePages = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();

		$countPageTriagePageTags = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->fetchRowCount();

		$this->assertSame( 1, $countPageTriagePages );
		// Two tags are created
		$this->assertSame( 2, $countPageTriagePageTags );

		// Delete page in pagetriage_page table
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $mainNsPage[ 'id' ] ] )
			->caller( __METHOD__ )
			->execute();

		$newCountPageTriagePages = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();

		$newCountPageTriagePageTags = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->fetchRowCount();

		$this->assertSame( 0, $newCountPageTriagePages );
		$this->assertSame( 2, $newCountPageTriagePageTags );

		$maint = new CleanupPageTriagePageTags();
		$maint->execute();
		$this->expectOutputString( "processing " . $countPageTriagePages . "\n" );

		$finalCountPageTriagePages = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page' )
			->fetchRowCount();

		$finalCountPageTriagePageTags = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->fetchRowCount();

		$this->assertSame( 0, $finalCountPageTriagePages );
		$this->assertSame( 0, $finalCountPageTriagePageTags );
	}
}
