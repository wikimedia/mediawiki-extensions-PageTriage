<?php
/**
 * Tests for ApiPageTriageGetMetadata class
 *
 * @group EditorEngagement
 * @author Ian Baker
 */
class ApiPageTriageGetMetadataTest extends MediaWikiTestCase {

	protected $pageTriage;

	protected function setUp() {
		parent::setUp();
		
		// Insert some made up articles into the database
	}

	protected function tearDown() {
		parent::tearDown();
		
		// Remove the made up articles
	}

	public function testGetMetadata() {
		$pageId = 1; // TODO: make a test page, then fetch it here.
		
		list( $result, $request, $session ) =  $this->doApiRequest( array(
			'action' => 'pagetriagegetmetadata',
			'page_id' => $pageId) );
			
		
	}

}
