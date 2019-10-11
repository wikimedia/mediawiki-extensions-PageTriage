<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWikiTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * Tests for ArticleMetadata class
 *
 * @group EditorEngagement
 * @group Broken
 * @author Ian Baker
 * @covers MediaWiki\Extension\PageTriage\ArticleMetadata
 */
class ArticleMetadataTest extends MediaWikiTestCase {

	protected $pageTriage;
	/** @var IDatabase */
	protected $dbr;
	protected $pageId;
	/** @var ArticleMetadata */
	protected $articleMetadata;

	protected function setUp() : void {
		parent::setUp();
		$this->pageId = [];
		$this->dbr = wfGetDB( DB_REPLICA );

		// Set up some page_id to test
		$count = $start = 0;
		while ( $count < 6 ) {
			$res = $this->dbr->selectRow(
						[ 'page', 'pagetriage_page' ],
						[ 'page_id' ],
						[
							'page_is_redirect' => 0,
							'page_id > ' . $start,
							'page_id = ptrp_page_id'
						],
						__METHOD__,
						[
							'ORDER BY' => 'page_id',
							'LIMIT' => 1,
						]
				);
			if ( $res ) {
				$this->pageId[$res->page_id] = $res->page_id;
				$start = intval( $res->page_id );
			}
			$count++;
		}

		$this->articleMetadata = new ArticleMetadata( $this->pageId );
	}

	protected function tearDown() : void {
		parent::tearDown();
	}

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
	 * @depends testGetValidTags
	 *
	 */
	public function testValidatePageIds() {
		$origPageId = array_merge( $this->pageId, [ 'cs', '99999999', 'abcde', '5ab', '200' ] );

		$pageId = ArticleMetadata::validatePageIds( $origPageId );

		$this->assertEquals(
			count( $origPageId ),
			count( $pageId ),
			'Article count doesn\'t match after ArticleMetadata::validatePageId()'
		);

		foreach ( $pageId as $val ) {
			$this->assertEquals( (string)$val, (string)(int)$val );
		}

		$res = $this->dbr->select(
			[ 'pagetriage_page' ],
			[ 'ptrp_page_id' ],
			[ 'ptrp_page_id' => $pageId ]
		);
		$this->assertEquals( count( $pageId ), $this->dbr->numRows( $res ) );
	}

	/**
	 *  @depends testValidatePageId
	 *
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
			[ 'ptrpt_page_id' => $this->pageId ]
		);
		$this->assertEquals( 0, $this->dbr->numRows( $res ) );
	}

}
