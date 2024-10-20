<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Title\Title;

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

	public function setUp(): void {
		parent::setUp();
		$this->setUpForOresCopyvioTests();
	}

	public function testCopyvioInvalidPermissions() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			"You don't have permission to tag pages as likely copyright violations." );
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => 0,
			]
		);
	}

	public function testInvalidPageId() {
		$this->setGroupPermissions( '*', 'pagetriage-copyvio', true );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'There is no revision with ID 5.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => 5
			]
		);
	}

	public function testCopyvioInsertLog() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->overrideConfigValue( 'OresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$this->ensureOresModel( 'draftquality' );
		$this->ensureOresModel( 'articlequality' );
		$this->ensureCopyvioTag();

		$page1 = $this->makeDraft( 'Page001' );
		$this->makeDraft( 'Page002' );

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
		$result = $this->getLogEventForTitle( (string)$title );
		$logevent = $result[0]['query']['logevents'][0];
		$this->assertEquals( 'insert', $logevent['action'] );
		$this->assertEquals( $revId, $logevent['params']['revId'] );
		$this->assertEquals( 'pagetriage-copyvio', $logevent['type'] );

		// Should get 'done' if posting a duplicate rev ID.
		$request = $this->doApiRequestWithToken( [
			'action' => 'pagetriagetagcopyvio',
			'revid' => $revId,
		] );
		$this->assertEquals( 'done', $request[0]['pagetriagetagcopyvio']['result'] );

		// But there should only be a single log entry.
		$result = $this->getLogEventForTitle( (string)$title );
		$this->assertCount( 1, $result[0]['query']['logevents'] );

		// Verify that logging a new revision for this page works.
		$this->editPage( (string)$title, 'should work' );
		$title = Title::newFromID( $title->getArticleID() );
		$request = $this->doApiRequestWithToken( [
			'action' => 'pagetriagetagcopyvio',
			'revid' => $title->getLatestRevID(),
		] );
		$this->assertEquals( 'success', $request[0]['pagetriagetagcopyvio']['result'] );
		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => 1 ] );
		$this->assertPages( [ 'Page001' ], $list );
		// Check that there are two log entries now.
		$result = $this->getLogEventForTitle( (string)$title );
		$this->assertCount( 2, $result[0]['query']['logevents'] );
	}

	public function testCopyvioDeleteLog() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->overrideConfigValue( 'OresModels', [
			'draftquality' => [ 'enabled' => true ],
			'articlequality' => [ 'enabled' => true ],
		] );
		$this->ensureOresModel( 'draftquality' );
		$this->ensureOresModel( 'articlequality' );
		$this->ensureCopyvioTag();

		$page = $this->makeDraft( 'Page001' );

		// No copyvio yet.
		$list = $this->getPageTriageList();
		$this->assertPages( [ 'Page001'	], $list );
		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => 1 ] );
		$this->assertEquals( [], $list );

		// Tag page for copyvio.
		$title = Title::newFromID( $page );
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
		$result = $this->getLogEventForTitle( (string)$title );
		$logevent = $result[0]['query']['logevents'][0];
		$this->assertEquals( 'insert', $logevent['action'] );
		$this->assertEquals( $revId, $logevent['params']['revId'] );
		$this->assertEquals( 'pagetriage-copyvio', $logevent['type'] );

		// Untag page for copyvio.
		$request = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriagetagcopyvio',
				'revid' => $revId,
				'untag' => true,
			]
		);
		$this->assertEquals( 'success', $request[0]['pagetriagetagcopyvio']['result'] );
		$list = $this->getPageTriageList( [ 'show_predicted_issues_copyvio' => 1 ] );
		$this->assertPages( [], $list );

		// Check that log entry was created.
		$result = $this->getLogEventForTitle( (string)$title );
		$this->assertCount( 2, $result[0]['query']['logevents'] );
		$logevent = $result[0]['query']['logevents'][0];
		$this->assertEquals( 'delete', $logevent['action'] );
		$this->assertEquals( $revId, $logevent['params']['revId'] );
		$this->assertEquals( 'pagetriage-copyvio', $logevent['type'] );

		// Should get 'done' if posting a duplicate rev ID.
		$request = $this->doApiRequestWithToken( [
			'action' => 'pagetriagetagcopyvio',
			'revid' => $revId,
			'untag' => true,
		] );
		$this->assertEquals( 'done', $request[0]['pagetriagetagcopyvio']['result'] );

		// But there should still only be two log entries (1 tag and 1 untag).
		$result = $this->getLogEventForTitle( (string)$title );
		$this->assertCount( 2, $result[0]['query']['logevents'] );
	}

	protected function getLogEventForTitle( $title ) {
		return $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'logevents',
				'letype' => 'pagetriage-copyvio',
				'letitle' => $title,
			]
		);
	}
}
