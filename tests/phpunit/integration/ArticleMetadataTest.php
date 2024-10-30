<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleMetadata;

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

	/** @var PageTriage */
	protected $pageTriage;

	/** @var int[] */
	protected $pageIds;

	/** @var ArticleMetadata */
	protected $articleMetadata;

	protected function setUp(): void {
		parent::setUp();
		$this->pageIds = [];

		// Set up 6 pages to test with.
		for ( $i = 0; $i < 6; $i++ ) {
			$this->pageIds[] = $this->makeDraft( __CLASS__ . $i );
		}

		$this->articleMetadata = new ArticleMetadata( $this->pageIds );

		ArticleMetadata::clearStaticCache();
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\ArticleMetadata::getValidTags()
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
			'copyvio',
			'recreated',
			'content_similarity'
		];

		$this->assertArrayEquals( [], array_diff( array_keys( $tags ), $validTags ) );
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
		$res = $this->getDb()->newSelectQueryBuilder()
			->select( 'ptrp_page_id' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $validatedPageIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertEquals( count( $validatedPageIds ), $res->numRows() );
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
		$res = $this->getDb()->newSelectQueryBuilder()
			->select( 'ptrpt_page_id' )
			->from( 'pagetriage_page_tags' )
			->where( [ 'ptrpt_page_id' => $this->pageIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 0, $res->numRows() );
	}

}
