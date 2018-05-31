<?php

namespace MediaWiki\Extension\PageTriage;

/**
 * Interface to the wiki's API
 **/
class WikiApi {
	private $http, $token, $url;

	/**
	 * Construct the class instance
	 * @param string $url The URL used to access the API
	 */
	public function __construct( $url ) {
		$this->http = new PageTriageHttp;
		$this->url = $url;
	}

	/**
	 * Send a get query to the API
	 * @param string $query The query string
	 * @return string The result from the API
	 */
	public function get( $query ) {
		$result = $this->http->get( $this->url.$query );
		return unserialize( $result );
	}

	/**
	 * Send a post query to the API
	 * @param string $query The query string
	 * @param array $postVars
	 * @return string The result from the API
	 */
	public function post( $query, $postVars ) {
		$result = $this->http->post( $this->url.$query, $postVars );
		return unserialize( $result );
	}

	/**
	 * Log into the wiki via the API
	 * @param string $username The user's username
	 * @param string $password The user's password
	 * @return string The result from the API
	 */
	public function login( $username, $password ) {
		$postVars = [ 'lgname' => $username, 'lgpassword' => $password ];
		$result = $this->post( '?action=login&format=php', $postVars );
		if ( $result['login']['result'] === 'NeedToken' ) {
			// Do it again with the token
			$postVars['lgtoken'] = $result['login']['token'];
			$result = $this->post( '?action=login&format=php', $postVars );
		}
		if ( $result['login']['result'] !== 'Success' ) {
			echo "Login failed.\n";
			die();
		} else {
			return $result;
		}
	}

	/**
	 * Get an edit token for the user
	 * @return string The token
	 */
	public function getToken() {
		$params = [
			'action' => 'query',
			'format' => 'php',
			'prop' => 'info',
			'intoken' => 'edit',
			'titles' => 'Main Page'
		];
		$params = http_build_query( $params );
		$result = $this->get( '?'.$params );
		foreach ( $result['query']['pages'] as $page ) {
			return $page['edittoken'];
		}
	}

	/**
	 * Get the contents of a page
	 * @param string $title The title of the wikipedia page to fetch
	 * @return string|bool The wikitext for the page (or false)
	 */
	public function getPage( $title ) {
		$params = [
			'action' => 'query',
			'format' => 'php',
			'prop' => 'revisions',
			'titles' => $title,
			'rvlimit' => 1,
			'rvprop' => 'content'
		];
		$params = http_build_query( $params );
		$result = $this->get( '?'.$params );
		foreach ( $result['query']['pages'] as $page ) {
			if ( isset( $page['revisions'][0]['*'] ) ) {
				return $page['revisions'][0]['*'];
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * Get the newest pages from the wiki
	 * @param int $namespace The namespace to limit the search to
	 * @param int $limit The maximum number of pages to return
	 * @return array of titles
	 */
	public function getNewPages( $namespace = 0, $limit = 10 ) {
		$params = [
			'action' => 'query',
			'list' => 'recentchanges',
			'format' => 'php',
			'rctype' => 'new',
			'rcprop' => 'title',
			'rcnamespace' => $namespace,
			'rclimit' => $limit
		];
		$params = http_build_query( $params );
		$result = $this->get( '?'.$params );
		$pages = $result['query']['recentchanges'];
		$pageTitles = [];
		foreach ( $pages as $page ) {
			$pageTitles[] = $page['title'];
		}
		return $pageTitles;
	}

	/**
	 * Create a new page on the wiki
	 * @param string $title The title of the new page
	 * @param string $text The text of the new page
	 * @return string The result from the API
	 */
	public function createPage( $title, $text ) {
		if ( !$this->token ) {
			$this->token = $this->getToken();
		}
		$params = [
			'title' => $title,
			'text' => $text,
			'token' => $this->token,
			'summary' => 'Importing article from another wiki for testing purposes',
			'createonly' => '1'
		];
		return $this->post( '?action=edit&format=php', $params );
	}
}
