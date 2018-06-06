<?php
/**
 * Tests for ApiPageTriageAction class
 *
 * @group medium
 * @group EditorEngagement
 * @covers ApiPageTriageAction
 */
class ApiPageTriageActionTest extends ApiTestCase {

	/**
	 * @var TestUser[] of test users
	 */
	public static $users;

	// Prepare test environment
	public function setUp() {
		parent::setUp();

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

	public function tearDown() {
		parent::tearDown();
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
		$this->markTestSkipped( 'Broken test' );
		global $wgUser;

		$wgUser = self::$users['one']->getUser();

		list( $result, , $session ) = $this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => 15,
				'reviewed' => '1'
			],
			$sessionArray['one'],
			self::$users['one']->getUser()
		);

		$this->assertEquals( "success", $result['pagetriageaction']['result'] );
	}

	/**
	 * @depends testLogin
	 * @expectedException ApiUsageException
	 */
	public function testPermissionError( $sessionArray ) {
		global $wgUser;

		$wgUser = self::$users['two']->getUser();

		$this->doApiRequestWithToken(
			[
				'action' => 'pagetriageaction',
				'pageid' => 15,
				'reviewed' => '1'
			],
			$sessionArray['two'],
			self::$users['two']->getUser()
		);
	}

	/**
	 * @depends testLogin
	 */
	public function testPageError( $sessionArray ) {
		$exception = false;
		try {
			global $wgUser;

			$wgUser = self::$users['one']->getUser();

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
			$this->assertTrue( $e->getStatusValue()->hasMessage( 'apierror-bad-pagetriage-page' ) );
		}
		$this->assertTrue( $exception, "Got exception" );
	}
}
