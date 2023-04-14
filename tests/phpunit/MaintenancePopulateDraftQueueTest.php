<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileAfcTag;
use MediaWiki\Extension\PageTriage\Maintenance\PopulateDraftQueue;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the populateDraftQueueTest.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\PopulateDraftQueue
 *
 * @group medium
 * @group Database
 */
class MaintenancePopulateDraftQueueTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'page', 'pagetriage_page', 'pagetriage_page_tags', 'pagetriage_tags' ];
		// Start with the Draft mode turned off
		// (also use a different ID and NS name, just in case we're assuming these).
		$this->draftNsId = 210;
		$this->setMwGlobals( [
			'wgExtraNamespaces' => [ $this->draftNsId => 'Submissions' ],
			'wgPageTriageDraftNamespaceId' => false,
		] );
	}

	public function testPreExistingPageAddedToDraftQueueAfterActivation() {
		// Get the initial page count.
		$initialCount = PageTriageUtil::getReplicaConnection()->selectRowCount( 'pagetriage_page' );
		// Create a page in the Draft namespace and confirm that it hasn't been added to the
		// PageTriage queue.
		$testPage = $this->insertPage( self::class . 'Test1', '', $this->draftNsId );
		$this->assertSelect( 'pagetriage_page', 'ptrp_page_id',
			[ 'ptrp_page_id' => $testPage['id'] ], []
		);

		// Enable Drafts mode in PageTriage, and run the maintenance script.
		$this->setMwGlobals( 'wgPageTriageDraftNamespaceId', $this->draftNsId );
		$maint = new PopulateDraftQueue();
		$maint->execute();
		$this->expectOutputString(
			"Processing drafts in NS {$this->draftNsId}...\n- batch 1\nComplete; 1 drafts processed.\n"
		);

		// Now the page should be in the queue.
		$newCount = PageTriageUtil::getReplicaConnection()->selectRowCount( 'pagetriage_page' );
		$this->assertEquals( $initialCount + 1, $newCount );
	}

	public function testPreExistingPagesWithCategoriesAreGivenCorrectTags() {
		$testPageCount = 10;
		// Get the initial page counts (because previous tests can leave things behind).
		$db = PageTriageUtil::getReplicaConnection();
		$initialCount = $db->selectRowCount( 'pagetriage_page' );
		$initialAfCPendingCount = $db->selectRowCount(
			[ 'pagetriage_page_tags', 'pagetriage_tags' ],
			'*',
			[
				'ptrpt_tag_id = ptrt_tag_id',
				'ptrt_tag_name' => 'afc_state',
				'ptrpt_value' => ArticleCompileAfcTag::PENDING,
			],
			__METHOD__
		);
		// Add 10 pages, five with categories.
		for ( $i = 1; $i <= $testPageCount; $i++ ) {
			$text = ( $i % 2 ) ? '[[Category:Pending AfC submissions]]' : '';
			$this->insertPage( self::class . 'TagTest' . $i, $text, $this->draftNsId );
		}
		// And one extra page, a redirect which shouldn't be included in the queue.
		$this->insertPage( self::class . 'RedirectTest',
			'#REDIRECT [[Non-draft page]]', $this->draftNsId
		);
		// No extra pages in the queue to start with.
		$this->assertSelect( [ 'pagetriage_page' ], [ 'count' => 'COUNT(*)' ], [],
			[ [ $initialCount ] ]
		);

		// Enable Drafts mode in PageTriage, and run the maintenance script.
		$this->setMwGlobals( 'wgPageTriageDraftNamespaceId', $this->draftNsId );
		$maint = new PopulateDraftQueue();
		$maint->execute();
		$this->expectOutputString(
			"Processing drafts in NS {$this->draftNsId}...\n"
			. "- batch 1\nComplete; $testPageCount drafts processed.\n"
		);

		// Now there are 10 more pages in the queue, 5 of them with the afc_state tag for 'pending'.
		$this->assertSelect( [ 'pagetriage_page' ], [ 'count' => 'COUNT(*)' ], [],
			[ [ 10 + $initialCount ] ]
		);
		$this->assertSelect(
			[ 'pagetriage_page_tags', 'pagetriage_tags' ],
			[ 'COUNT(*)' ],
			[
				'ptrpt_tag_id = ptrt_tag_id',
				'ptrt_tag_name' => 'afc_state',
				'ptrpt_value' => ArticleCompileAfcTag::PENDING,
			],
			[ [ 5 + $initialAfCPendingCount ] ]
		);
	}
}
