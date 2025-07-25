<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use TestUser;

/**
 * Tests for ApiPageTriageAction class
 *
 * @group medium
 * @group Database
 * @group EditorEngagement
 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageAction
 */
class ApiPageTriageActionTest extends PageTriageTestCase {

	/**
	 * @var TestUser[] of test users
	 */
	public static $users;

	public function setUp(): void {
		$this->setGroupPermissions( 'autopatrol', 'autopatrol', true );
		parent::setUp();
		$this->setUpForOresCopyvioTests();

		self::$users['one'] = new TestUser(
			'ApitestuserA',
			'Api Test UserA',
			'api_test_userA@example.com',
			[ 'sysop' ]
		);

		self::$users['two'] = new TestUser(
			'ApitestuserB',
			'Api Test UserB',
			'api_test_userB@example.com',
			[]
		);

		self::$users['blockeduser'] = new TestUser(
			'ApitestuserC',
			'Api Test UserC',
			'api_test_userB@example.com',
			[ 'sysop' ]
		);

		self::$users['autopatrolleduser'] = new TestUser(
			'ApitestuserD',
			'Api Test UserD',
			'api_test_userD@example.com',
			[ 'autopatrol' ]
		);

		$blockUserAction = MediaWikiServices::getInstance()
			->getBlockUserFactory()
			->newBlockUser(
				'ApitestuserC',
				self::$users['one']->getAuthority(),
				'infinite',
				'Test reason'
			);

		$blockUserAction->placeBlock();
	}

	public function testSuccessfulReviewAction() {
		$pageId = $this->makeDraft( 'Test' );

		[ $result, , ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['one']->getUser()
		);

		$this->assertEquals( "success", $result['pagetriageaction']['result'] );
	}

	public function testBlockedUserReview() {
		$pageId = $this->makeDraft( 'Test' );

		$this->expectException( ApiUsageException::class );
		[ $result, , ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['blockeduser']->getUser()
		);
		$this->assertNotEquals( "success", $result['pagetriageaction']['result'] );
	}

	public function testBlockedUserUnReview() {
		$pageId = $this->makeDraft( 'Test' );

		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['one']->getUser()
		);

		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['blockeduser']->getUser()
		);

		$this->assertNotEquals( "success", $result['pagetriageaction']['result'] );
	}

	public function testNoChangeReviewAction() {
		$pageId = $this->makeDraft( 'Test ' );

		[ $result ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['one']->getUser()
		);

		$this->assertSame(
			"success",
			$result['pagetriageaction']['result'],
			"First action should succeed"
		);

		[ $result ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			null,
			self::$users['one']->getUser()
		);

		$this->assertSame(
			"done",
			$result['pagetriageaction']['result'],
			"second action should return 'done' rather than 'success'"
		);
		$this->assertSame(
			$pageId,
			$result['pagetriageaction']['pagetriage_unchanged_status']
		);
	}

	public function testPermissionError() {
		$pageId = $this->makeDraft( 'Test ' );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to mark others\' edits as patrolled.' );
		$this->doApiRequestWithToken( [
			'action' => 'pagetriageaction',
			'pageid' => $pageId,
			'skipnotif' => 1,
			'reviewed' => '1'
		], null, self::$users['two']->getUser() );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to mark others\' edits as patrolled.' );
		$this->doApiRequestWithToken( [
			'action' => 'pagetriageaction',
			'pageid' => $pageId,
			'skipnotif' => 1,
			'reviewed' => '1'
		], null, self::$users['autopatrolleduser']->getUser() );
	}

	public function testPermissionErrorAnon() {
		$pageId = $this->makeDraft( 'Test' );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to mark others\' edits as patrolled.' );
		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '0',
			],
			null,
			User::newFromId( 0 )
		);
	}

	public function testPageError() {
		$exception = false;
		try {
			$this->doApiRequestWithToken(
				[
					'action' => 'pagetriageaction',
					'pageid' => 999999999,
					'reviewed' => '1' ],
				null,
				self::$users['one']->getUser()
			);
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertTrue( $e->getStatusValue()->hasMessage( 'apierror-missingtitle' ) );
		}
		$this->assertTrue( $exception, "Got exception" );
	}
}
