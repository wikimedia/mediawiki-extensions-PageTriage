<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiUsageException;
use PageTriageTestCase;
use Title;

/**
 * Tests for ApiPageTriageTagCopyvio class.
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageTagCopyvio
 */
class ApiPageTriageTagCopyvioTest extends PageTriageTestCase {

	public function setUp() {
		global $wgHooks;
		parent::setUp();
		$this->setUpForOresCopyvioTests( $wgHooks );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage You don't have permission to tag pages as likely copyright violations.
	 */
	public function testCopyvioInvalidPermissions() {
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => 0,
			]
		);
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage There is no revision with ID 5.
	 */
	public function testInvalidPageId() {
		$this->setGroupPermissions( '*', 'pagetriage-copyvio', true );
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => 5
			]
		);
	}

	public function testCopyvioInsertLog() {
		$dbw = wfGetDB( DB_MASTER );
		foreach ( [ 'pagetriage_page', 'page' ] as $table ) {
			$dbw->delete( $table, '*' );
		}

		$this->setMwGlobals( 'wgOresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		self::ensureOresModel( 'draftquality' );
		self::ensureOresModel( 'articlequality' );
		self::ensureCopyvioTag();

		$page1 = $this->makePage( 'Page001' );
		$this->makePage( 'Page002' );

		$list = $this->getPageTriageList();
		$this->assertPages( [
			'Page001', 'Page002'
		], $list );
		// No copyvio yet.
		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => 1 ] );
		$this->assertEquals( [], $list );

		// Post copyvio data for page1.
		$title = Title::newFromID( $page1 );
		$revId = $title->getLatestRevID();
		$this->setGroupPermissions( '*', 'pagetriage-copyvio', true );
		$request = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => $revId,
			]
		);
		$this->assertEquals( 'success', $request[0]['pagetriagetagcopyvio']['result'] );
		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => 1 ] );
		$this->assertPages( [ 'Page001' ], $list );

		// Check that log entry was created.
		$result = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'logevents',
				'letitle' => (string)$title,
			]
		);
		$logevent = $result[0]['query']['logevents'][0];
		$this->assertEquals( 'insert', $logevent['action'] );
		$this->assertEquals( $revId, $logevent['params']['revId'] );
		$this->assertEquals( 'pagetriage-copyvio', $logevent['type'] );
	}
}
