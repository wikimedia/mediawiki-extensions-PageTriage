<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\Maintenance\DeleteAfcStates;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the deleteAfcStates.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\DeleteAfcStates
 *
 * @group medium
 * @group Database
 */
class MaintenanceDeleteAfcStatesTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'pagetriage_page_tags' ];
		// Delete any dangling page triage pages before inserting our test data
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbw->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulDeleteAfcStates() {
		$dbr = PageTriageUtil::getReplicaConnection();
		// Create a page in the MAIN namespace that is nominated for deletion
		$page = $this->insertPage( 'MainTest1', '[[Category:Articles_for_deletion]]', NS_MAIN );
		$acp = ArticleCompileProcessor::newFromPageId( [ $page[ 'id' ] ] );
		$acp->compileMetadata();
		$afcStateTagId = ArticleMetadata::getValidTags()['afc_state'];

		$initialCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->where( [ 'ptrpt_tag_id' => $afcStateTagId ] )
			->fetchRowCount();
		$this->assertSame( 1, $initialCount );

		$maint = new DeleteAfcStates();
		$maint->execute();
		$this->expectOutputString(
			"Deleted afc_state for 1 pages. From {$page[ 'id' ]} to {$page[ 'id' ]}.\nDone\n"
		);

		// The script should change the value of the ptrp_deleted
		$newCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'pagetriage_page_tags' )
			->where( [ 'ptrpt_tag_id' => $afcStateTagId ] )
			->fetchRowCount();
		$this->assertSame( 0, $newCount );
	}
}
