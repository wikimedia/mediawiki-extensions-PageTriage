<?php
/**
 * Tests for ArticleMetadata class
 *
 * @group EditorEngagement
 * @author Ian Baker
 */
class ArticleMetadataTest extends MediaWikiTestCase {

	protected $pageTriage;

	protected function setUp() {
		parent::setUp();
		
		$title = Title::newFromText( "Some test article" );
        $page = WikiPage::factory( $title );
	}

	protected function tearDown() {
		parent::tearDown();
		
	}

	public function testDeleteMetadata() {
		// TODO: delete an article's metadata

		return true;
	}

}
