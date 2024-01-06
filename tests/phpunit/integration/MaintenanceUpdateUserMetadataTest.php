<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\UpdateUserMetadata;

/**
 * Tests for the updateUserMetadata.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\UpdateUserMetadata
 *
 * @group medium
 * @group Database
 */
class MaintenanceUpdateUserMetadataTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		// Delete any dangling page triage pages before inserting our test data
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulUpdateUserMetadata() {
		$mainNsPage = $this->insertPage( 'MainMetadata', 'Test 1', NS_MAIN );
		$this->db->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_tags_updated' => $this->db->timestamp( '20200323210427' ) ] )
			->where( [ 'ptrp_page_id' => $mainNsPage['id'] ] )
			->execute();

		$maint = new UpdateUserMetadata();
		$maint->execute();
		$this->expectOutputString( "Started processing... \nprocessed 1 \nCompleted \n" );
	}
}
