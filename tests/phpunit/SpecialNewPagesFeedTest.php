<?php
/**
 * Tests for SpecialNewPagesFeedTest class (PageTriage list view)
 *
 * @group medium
 * @group EditorEngagement
 * @author Ryan Kaldari
 */
class SpecialNewPagesFeedTest extends ApiTestCase {

	protected $pageTriage;

	/**
	 * @var TestUser[] test user
	 */
	public static $users;

	public function setUp() {
		$testUserClass = class_exists( 'ApiTestUser' ) ? 'ApiTestUser' : 'TestUser';
		self::$users['one'] = new $testUserClass(
				'PageTriageUser1',
				'PageTriage Test User 1',
				'pagetriage_test_user_1@example.com',
				array()
		);

		parent::setUp();
		$this->pageTriage = new SpecialNewPagesFeed;
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
				'lgname' => $user->getUser()->getName(),
				'lgpassword' => $user->getPassword()
			);
			list( $result, , $session ) = $this->doApiRequest( $params );
			$this->assertArrayHasKey( "login", $result );
			$this->assertArrayHasKey( "result", $result['login'] );
			$this->assertEquals( "NeedToken", $result['login']['result'] );
			$token = $result['login']['token'];

			$params = array(
				'action' => 'login',
				'lgtoken' => $token,
				'lgname' => $user->getUser()->getName(),
				'lgpassword' => $user->getPassword()
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

		$wgUser = self::$users['one']->getUser();

		$params = array(
			'action' => 'edit',
			'title' => 'Vacation Disaster Mania',
			'summary' => 'Creating test article',
			'createonly' => 1,
			'text' => 'Hello World'
		);

		$alreadyCreated = false;
		try {
			list( $result, , $session ) =  $this->doApiRequestWithToken(
				$params,
				$sessionArray['one'],
				self::$users['one']->getUser()
			);
		} catch ( UsageException $e ) {
			$this->assertEquals( "The article you tried to create has been created already",
				$e->getMessage() );
			$alreadyCreated = true;
		}

		if ( !$alreadyCreated ) {
			$this->assertEquals( "Success", $result['edit']['result'] );
		}

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

			try {
				$this->doApiRequestWithToken(
					$params,
					$sessionArray['one'],
					self::$users['one']->getUser()
				);
			} catch ( UsageException $e ) {
				$this->assertEquals( "The article you tried to create has been created already",
					$e->getMessage() );
			}
		}
	}
}
