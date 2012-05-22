<?php
/**
 * Tests for SpecialPageTriage class (PageTriage list view)
 *
 * @group EditorEngagement
 * @author Ryan Kaldari
 */
class SpecialPageTriageTest extends ApiTestCase {

	protected $pageTriage;

	/**
	 * @var User test user
	 */
	public static $users;

	public function setUp() {
		self::$users['one'] = new ApiTestUser(
				'PageTriageUser1',
				'PageTriage Test User 1',
				'pagetriage_test_user_1@example.com',
				array()
		);

		parent::setUp();
		$this->pageTriage = new SpecialPageTriage;
	}

	public function tearDown() {
		parent::tearDown();

		// Remove the made up articles
	}

	// Create a fake logged in user
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
	function testAddArticles( $sessionArray ) {

		global $wgUser;

		$wgUser = self::$users['one']->user;

		$params = array(
			'action' => 'edit',
			'title' => 'Vacation Disaster Mania',
			'summary' => 'Creating test article',
			'createonly' => 1,
			'text' => 'Hello World'
		);

		list( $result, , $session ) =  $this->doApiRequestWithToken(
			$params,
			$sessionArray['one'],
			self::$users['one']->user
		);

		$this->assertEquals( "Success", $result['edit']['result'] );

		// If it worked, make some more articles for use as test data
		if ( $result['edit']['result'] == "Success" ) {

			$newArticles = array(
				'My Lame Garage Band' => 'We rock!',
				'The Chronicals of Grok' => 'OK, I get it.',
				'Very thin wafers' => 'Eat it!'
			);

			foreach ( $newArticles as $title => $text ) {
				$params = array(
					'action' => 'edit',
					'title' => $title,
					'summary' => 'Creating test article',
					'createonly' => 1,
					'text' => $text
				);

				list( $result, , $session ) =  $this->doApiRequestWithToken(
					$params,
					$sessionArray['one'],
					self::$users['one']->user
				);
			}

		}

	}

}
