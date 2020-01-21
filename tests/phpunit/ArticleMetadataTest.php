<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleMetadata;
use PageTriageTestCase;
use Wikimedia\Rdbms\DBConnRef;

/**
 * Tests for ArticleMetadata class
 *
 * @group EditorEngagement
 * @group Database
 * @group medium
 * @author Ian Baker
 * @covers \MediaWiki\Extension\PageTriage\ArticleMetadata
 */
class ArticleMetadataTest extends PageTriageTestCase {

	protected $pageTriage;

	/** @var DBConnRef */
	protected $dbr;

	/** @var int[] */
	protected $pageIds;

	/** @var ArticleMetadata */
	protected $articleMetadata;

	protected function setUp() : void {
		parent::setUp();
		$this->pageIds = [];
		$this->dbr = wfGetDB( DB_REPLICA );

		// Set up 6 pages to test with.
		for ( $i = 0; $i < 6; $i++ ) {
			$this->pageIds[] = $this->makePage( __CLASS__ . $i );
		}

		$this->articleMetadata = new ArticleMetadata( $this->pageIds );
	}

	protected function tearDown() : void {
		parent::tearDown();
	}

	/**
	 * @group Broken
	 */
	public function testGetValidTags() {
		$tags = ArticleMetadata::getValidTags();

		$validTags = [
					'linkcount',
					'category_count',
					'csd_status',
					'prod_status',
					'blp_prod_status',
					'afd_status',
					'rev_count',
					'page_len',
					'snippet',
					'user_name',
					'user_editcount',
					'user_creation_date',
					'user_autoconfirmed',
					'user_experience',
					'user_bot',
					'user_block_status',
					'user_id',
					'reference',
					'afc_state',
					'copyvio'
				];

		$this->assertEmpty( array_diff( array_keys( $tags ), $validTags ) );
	}

	/**
	 * Valid page IDs must be in the pagetriage_page table.
	 */
	public function testValidatePageIds() {
		$rawPageIds = array_merge( $this->pageIds, [ 'cs', '99999999', 'abcde', '5ab', '200' ] );
		$validatedPageIds = ArticleMetadata::validatePageIds( $rawPageIds );

		// Check that all invalid page IDs were removed.
		$this->assertArrayEquals(
			$this->pageIds,
			$validatedPageIds,
			'Page IDs don\'t match after ArticleMetadata::validatePageId()'
		);

		// Check that they all exist in the pagetriage_page table.
		$res = $this->dbr->select(
			[ 'pagetriage_page' ],
			[ 'ptrp_page_id' ],
			[ 'ptrp_page_id' => $validatedPageIds ]
		);
		$this->assertEquals( count( $validatedPageIds ), $this->dbr->numRows( $res ) );
	}

	/**
	 */
	public function testGetMetadata() {
		$data = $this->articleMetadata->getMetadata();
		$this->assertGreaterThan( 0, count( $data ) );
		$tags = ArticleMetadata::getValidTags() +
			[
				'creation_date' => 'creation_date',
				'patrol_status' => 'patrol_status',
				'is_redirect' => 'is_redirect',
				'ptrp_last_reviewed_by' => 'ptrp_last_reviewed_by',
				'ptrp_reviewed_updated' => 'ptrp_reviewed_updated',
				'reviewer' => 'reviewer',
				'deleted' => 'deleted',
				'title' => 'title'
			];

		foreach ( $data as $pageId => $val ) {
			foreach ( $val as $tagName => $tagValue ) {
				$this->assertArrayHasKey( $tagName, $tags );
			}
		}
	}

	/**
	 * @depends testGetMetadata
	 */
	public function testDeleteMetadata() {
		$this->articleMetadata->deleteMetadata();
		$res = $this->dbr->select(
			[ 'pagetriage_page_tags' ],
			[ 'ptrpt_page_id' ],
			[ 'ptrpt_page_id' => $this->pageIds ]
		);
		$this->assertSame( 0, $this->dbr->numRows( $res ) );
	}

}
