<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\UpdateUserMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

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
		$this->tablesUsed = [ 'pagetriage_page', 'page' ];
		// Delete any dangling page triage pages before inserting our test data
		PageTriageUtil::getPrimaryConnection()->newDeleteQueryBuilder()
			->delete( 'pagetriage_page' )
			->where( '1 = 1' )
			->caller( __METHOD__ )
			->execute();
	}

	public function testSuccessfulUpdateUserMetadata() {
		$dbr = PageTriageUtil::getReplicaConnection();
		$dbw = PageTriageUtil::getPrimaryConnection();

		$mainNsPage = $this->insertPage( 'MainMetadata', 'Test 1', NS_MAIN );
		$dbw->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_tags_updated' => $dbr->timestamp( '20200323210427' ) ] )
			->where( [ 'ptrp_page_id' => $mainNsPage['id'] ] )
			->execute();

		$maint = new UpdateUserMetadata();
		$maint->execute();
		$this->expectOutputString( "Started processing... \nprocessed 1 \nCompleted \n" );
	}
}
