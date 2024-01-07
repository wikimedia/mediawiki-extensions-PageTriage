<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiUsageException;
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
	}

	public function testLogin() {
		$sessionArray = [];

		foreach ( self::$users as $key => $user ) {
			$params = [
				'action' => 'login',
				'lgname' => $user->getUser()->getName(),
				'lgpassword' => $user->getPassword()
			];
			list( $result, , $session ) = $this->doApiRequest( $params );
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
			list( $result, , $session ) = $this->doApiRequest( $params, $session );
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
		$pageId = $this->makeDraft( 'Test ' );

		list( $result, , ) = $this->doApiRequestWithToken(
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
	public function testNoChangeReviewAction( $sessionArray ) {
		$pageId = $this->makeDraft( 'Test ' );

		list( $result ) = $this->doApiRequestWithToken(
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

		list( $result ) = $this->doApiRequestWithToken(
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
		$this->expectExceptionMessage( 'The action you have requested is limited to users' );
		$this->doApiRequestWithToken( [
			'action' => 'pagetriageaction',
			'pageid' => $pageId,
			'skipnotif' => 1,
			'reviewed' => '1'
		], $sessionArray['two'], self::$users['two']->getUser() );
	}

	public function testPermissionErrorAnon() {
		$pageId = $this->makeDraft( 'Test' );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The action you have requested is limited to users' );
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
