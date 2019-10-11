<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\SpecialNewPagesFeed;
use ApiTestCase;
use ApiUsageException;
use TestUser;

/**
 * Tests for SpecialNewPagesFeed class (PageTriage list view)
 *
 * @group medium
 * @group EditorEngagement
 * @author Ryan Kaldari
 * @covers MediaWiki\Extension\PageTriage\SpecialNewPagesFeed
 */
class SpecialNewPagesFeedTest extends ApiTestCase {

	protected $pageTriage;

	/**
	 * @var TestUser[] test user
	 */
	public static $users;

	public function setUp() : void {
		self::$users['one'] = new TestUser(
			'PageTriageUser1',
			'PageTriage Test User 1',
			'pagetriage_test_user_1@example.com',
			[]
		);

		parent::setUp();
		$this->pageTriage = new SpecialNewPagesFeed;
	}

	public function tearDown() : void {
		parent::tearDown();

		// Remove the made up articles
	}

	// Create a fake logged in user
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
	public function testAddArticles( $sessionArray ) {
		global $wgUser;

		$wgUser = self::$users['one']->getUser();

		$params = [
			'action' => 'edit',
			'title' => 'Vacation Disaster Mania',
			'summary' => 'Creating test article',
			'createonly' => 1,
			'text' => 'Hello World'
		];

		$alreadyCreated = false;
		try {
			list( $result, , ) = $this->doApiRequestWithToken(
				$params,
				$sessionArray['one'],
				self::$users['one']->getUser()
			);
		} catch ( ApiUsageException $e ) {
			$this->assertEquals( $e->getStatusValue()->hasMessage( 'apierror-articleexists' ) );
			$alreadyCreated = true;
		}

		if ( !$alreadyCreated ) {
			$this->assertEquals( "Success", $result['edit']['result'] );
		}

		$newArticles = [
			'My Lame Garage Band' => 'We rock!',
			'The Chronicals of Grok' => 'OK, I get it.',
			'Very thin wafers' => 'Eat it!'
		];

		foreach ( $newArticles as $title => $text ) {
			$params = [
				'action' => 'edit',
				'title' => $title,
				'summary' => 'Creating test article',
				'createonly' => 1,
				'text' => $text
			];

			try {
				$this->doApiRequestWithToken(
					$params,
					$sessionArray['one'],
					self::$users['one']->getUser()
				);
			} catch ( ApiUsageException $e ) {
				$this->assertEquals( $e->getStatusValue()->hasMessage( 'apierror-articleexists' ) );
			}
		}
	}
}
