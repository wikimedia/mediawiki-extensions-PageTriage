<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\FixNominatedForDeletion;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the fixNominatedForDeletion.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\FixNominatedForDeletion
 *
 * @group medium
 * @group Database
 */
class MaintenanceFixNominatedForDeletionTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'pagetriage_page', 'categorylinks' ];
	}

	public function testSuccessfulFixNominatedForDeletion() {
		$dbr = PageTriageUtil::getReplicaConnection();
		$dbw = PageTriageUtil::getPrimaryConnection();
		// Create a page in the MAIN namespace that is nominated for deletion
		$page = $this->insertPage( 'NominatedArticle', '[[Category:Articles_for_deletion]]', NS_MAIN );

		// Purposefully assigning ptrp_deleted to 0
		$dbw->newUpdateQueryBuilder()
			->update( 'pagetriage_page' )
			->set( [ 'ptrp_deleted' => 0 ] )
			->where( [ 'ptrp_page_id'  => $page[ 'id' ] ] )
			->caller( __METHOD__ )
			->execute();
		$initialPtrpDeleted = $dbr->newSelectQueryBuilder()
			->select( 'ptrp_deleted' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page[ 'id' ] ] )
			->fetchField();
		$this->assertSame( 0, (int)$initialPtrpDeleted );

		$maint = new FixNominatedForDeletion();
		$maint->execute();
		$this->expectOutputString(
			"Updated 1 pages. From {$page['id']} to {$page['id']}.\nDone\n"
		);

		// The script should change the value of ptrp_deleted
		$newPtrpDeleted = $dbr->newSelectQueryBuilder()
			->select( 'ptrp_deleted' )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $page[ 'id' ] ] )
			->fetchField();
		$this->assertSame( 1,  (int)$newPtrpDeleted );
	}
}
