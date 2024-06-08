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

	public function testLogin() {
		$sessionArray = [];

		foreach ( self::$users as $key => $user ) {
			$params = [
				'action' => 'login',
				'lgname' => $user->getUser()->getName(),
				'lgpassword' => $user->getPassword()
			];
			[ $result, , $session ] = $this->doApiRequest( $params );
			$this->assertArrayHasKey( "login", $result );
			$this->assertArrayHasKey( "result", $result['login'] );
			$this->assertEquals( "NeedToken", $result['login']['result'] );
			$token = $result['login']['token'];

			$params = [
				'action' => 'login',
				'lgtoken' => $token,
				'lgname' => $user->getUser()->getName(),
				'lgpassword' => $user->getPassword()
			];
			[ $result, , $session ] = $this->doApiRequest( $params, $session );
			$this->assertArrayHasKey( "login", $result );
			$this->assertArrayHasKey( "result", $result['login'] );
			$this->assertEquals( "Success", $result['login']['result'] );

			$this->assertNotEmpty( $session, 'API Login must return a session' );

			$sessionArray[$key] = $session;

		}

		return $sessionArray;
	}

	/**
	 * @depends testLogin
	 */
	public function testSuccessfulReviewAction( $sessionArray ) {
		$pageId = $this->makeDraft( 'Test' );

		[ $result, , ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			$sessionArray['one'],
			self::$users['one']->getUser()
		);

		$this->assertEquals( "success", $result['pagetriageaction']['result'] );
	}

	/**
	 * @depends testLogin
	 */
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

	/**
	 * @depends testLogin
	 */
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

	/**
	 * @depends testLogin
	 */
	public function testNoChangeReviewAction( $sessionArray ) {
		$pageId = $this->makeDraft( 'Test ' );

		[ $result ] = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => $pageId,
				'reviewed' => '1',
				'skipnotif' => '1'
			],
			$sessionArray['one'],
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
			$sessionArray['one'],
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

	/**
	 * @depends testLogin
	 */
	public function testPermissionError( $sessionArray ) {
		$pageId = $this->makeDraft( 'Test ' );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to mark others\' edits as patrolled.' );
		$this->doApiRequestWithToken( [
			'action' => 'pagetriageaction',
			'pageid' => $pageId,
			'skipnotif' => 1,
			'reviewed' => '1'
		], $sessionArray['two'], self::$users['two']->getUser() );

		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'You don\'t have permission to mark others\' edits as patrolled.' );
		$this->doApiRequestWithToken( [
			'action' => 'pagetriageaction',
			'pageid' => $pageId,
			'skipnotif' => 1,
			'reviewed' => '1'
		], $sessionArray['autopatrolleduser'], self::$users['autopatrolleduser']->getUser() );
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

	/**
	 * @depends testLogin
	 */
	public function testPageError( $sessionArray ) {
		$exception = false;
		try {
			$this->doApiRequestWithToken(
				[
					'action' => 'pagetriageaction',
					'pageid' => 999999999,
					'reviewed' => '1' ],
				$sessionArray['one'],
				self::$users['one']->getUser()
			);
		} catch ( ApiUsageException $e ) {
			$exception = true;
			$this->assertTrue( $e->getStatusValue()->hasMessage( 'apierror-missingtitle' ) );
		}
		$this->assertTrue( $exception, "Got exception" );
	}
}
