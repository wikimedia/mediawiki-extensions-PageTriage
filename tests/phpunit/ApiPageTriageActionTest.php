<?php
/**
 * Tests for ApiPageTriageAction class
 *
 * @group medium
 * @group EditorEngagement
 */
class ApiPageTriageActionTest extends ApiTestCase {

	/**
	 * @var Array of test users
	 */
	public static $users;

	// Prepare test environment
	function setUp() {
		parent::setUp();

		$testUserClass = class_exists( 'ApiTestUser' ) ? 'ApiTestUser' : 'TestUser';

		self::$users['one'] = new $testUserClass(
				'ApitestuserA',
				'Api Test UserA',
				'api_test_userA@example.com',
				array( 'sysop' )
		);

		self::$users['two'] = new $testUserClass(
				'ApitestuserB',
				'Api Test UserB',
				'api_test_userB@example.com',
				array()
		);
	}

	public function tearDown() {
		parent::tearDown();
	}

	function testLogin() {

		$sessionArray = array();

		foreach ( self::$users as $key => $user ) {

			$params = array(
				'action' => 'login',
				'lgname' => $user->username,
				'lgpassword' => $user->password
			);
			list( $result, , $session ) = $this->doApiRequest( $params );
			$this->assertArrayHasKey( "login", $result );
			$this->assertArrayHasKey( "result", $result['login'] );
			$this->assertEquals( "NeedToken", $result['login']['result'] );
			$token = $result['login']['token'];

			$params = array(
				'action' => 'login',
				'lgtoken' => $token,
				'lgname' => $user->username,
				'lgpassword' => $user->password
			);
			list( $result, , $session ) = $this->doApiRequest( $params, $session );
			$this->assertArrayHasKey( "login", $result );
			$this->assertArrayHasKey( "result", $result['login'] );
			$this->assertEquals( "Success", $result['login']['result'] );
			$this->assertArrayHasKey( 'lgtoken', $result['login'] );

			$this->assertNotEmpty( $session, 'API Login must return a session' );

			$sessionArray[$key] = $session;

		}

		return $sessionArray;

	}

	/**
	 * @depends testLogin
	 */
	function testSuccessfulReviewAction( $sessionArray ) {
		$this->markTestSkipped( 'Broken test' );
		global $wgUser;

		$wgUser = self::$users['one']->user;

		list( $result, , $session ) = $this->doApiRequestWithToken( array(
										'action' => 'pagetriageaction',
										'pageid' => 15,
										'reviewed' => '1' ), $sessionArray['one'], self::$users['one']->user );

		$this->assertEquals( "success", $result['pagetriageaction']['result'] );
	}

	/**
	 * @depends testLogin
	 * @expectedException UsageException
	 */
	function testPermissionError( $sessionArray ) {
		global $wgUser;

		$wgUser = self::$users['two']->user;

		$this->doApiRequestWithToken(
			array(
				'action' => 'pagetriageaction',
				'pageid' => 15,
				'reviewed' => '1'
			),
			$sessionArray['two'],
			self::$users['two']->user
		);
	}

	/**
	 * @depends testLogin
	 */
	function testPageError( $sessionArray ) {
		$exception = false;
		try {
			global $wgUser;

			$wgUser = self::$users['one']->user;

			$this->doApiRequestWithToken(
				array(
					'action' => 'pagetriageaction',
					'pageid' => 999999999,
					'reviewed' => '1' ),
				$sessionArray['one'],
				self::$users['one']->user
			);
		} catch ( UsageException $e ) {
			$exception = true;
			$this->assertEquals( "The page specified does not exist in pagetriage queue",
				$e->getMessage() );
		}
		$this->assertTrue( $exception, "Got exception" );
	}
}
