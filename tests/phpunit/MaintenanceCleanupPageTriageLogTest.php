<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriageLog;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * Tests for the cleanupPageTriageLogTest.php maintenance script.
 *
 * @covers \MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriageLog
 *
 * @group medium
 * @group Database
 */
class MaintenanceCleanupPageTriageLogTest extends PageTriageTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed = [ 'logging' ];
		// Delete any dangling logs before inserting our test data
		PageTriageUtil::getPrimaryConnection()->delete( 'logging', '*' );
	}

	public function testSuccessfulPageTriageCleanup() {
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		$logs = [];

		$comment = \MediaWiki\MediaWikiServices::getInstance()->getCommentStore()
			->createComment( $dbw, '' );
		$logs[] = [
			'log_type' => 'pagetriage-curation',
			'log_action' => 'tag',
			'log_actor' => 7251,
			'log_params' => serialize( [ '4::tags' => true ] ),
			'log_timestamp' => $dbw->timestamp( '20991223210427' ),
			'log_namespace' => NS_MAIN,
			'log_title' => 'PageTriageLog',
			'log_comment_id' => $comment->id,
		];

		$dbw->insert( 'logging', $logs );

		$logsBefore = $dbr->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [
				'log_type' => 'pagetriage-curation',
				'log_action' => 'tag',
			] )
			->fetchField();
		$this->assertStringContainsString( '4::tags', $logsBefore );

		$maint = new CleanupPageTriageLog();
		$maint->execute();
		$outputPrint = "processed pagetriage-curation tag: 1\n" .
		"processed pagetriage-curation delete: 0\n" .
		"processed pagetriage-deletion delete: 0\n";
		$this->expectOutputString( $outputPrint );

		$logsAfter = $dbr->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [
				'log_type' => 'pagetriage-curation',
				'log_action' => 'tag',
			] )
			->fetchField();
		$this->assertStringContainsString( 'tags', $logsAfter );
		$this->assertStringNotContainsString( '4::', $logsAfter );
	}
}
