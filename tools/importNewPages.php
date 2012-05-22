<?php
/**
 * This script imports newly created articles from one wiki to another.
 * It only copies the text of the articles, not the history or editor information.
 * This script should only be used for testing purposes. For normal transwiki importing, refer to:
 * http://meta.wikimedia.org/wiki/Help:Import
 *
 * This script can only be run from the command line.
 * The syntax is:
 * importNewPages.php <# of articles> <username> <password> <source API path> <destination API path>
 * The API path parameters are optional.
 **/

/**
 * Interface to cURL
 **/
class PageTriageHttp {
	private $curlHandle;
	private $id;

	function __construct() {
		$this->id = rand( 0, 1000000 );
		$this->curlHandle = curl_init();
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEJAR, '/tmp/cookies'.$this->id.'.dat' );
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEFILE, '/tmp/cookies'.$this->id.'.dat' );
		curl_setopt( $this->curlHandle, CURLOPT_MAXCONNECTS, 10 );
		curl_setopt( $this->curlHandle, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
	}

	/**
	 * @param $url string
	 */
	function get( $url ) {
		curl_setopt( $this->curlHandle, CURLOPT_URL, $url );
		curl_setopt( $this->curlHandle, CURLOPT_USERAGENT, 'php PageTriageBot' );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPGET, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_TIMEOUT, 60 );
		curl_setopt( $this->curlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->curlHandle, CURLOPT_HEADER, 0 );
		curl_setopt( $this->curlHandle, CURLOPT_ENCODING, 'UTF-8' );
		curl_setopt( $this->curlHandle, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_MAXREDIRS, 5 );
		return curl_exec( $this->curlHandle );
	}

	/**
	 * @param $url string
	 * @param $postVars
	 * @return mixed
	 */
	function post( $url, $postVars ) {
		curl_setopt( $this->curlHandle, CURLOPT_URL, $url );
		curl_setopt( $this->curlHandle, CURLOPT_USERAGENT, 'php PageTriageBot' );
		curl_setopt( $this->curlHandle, CURLOPT_POST, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_TIMEOUT, 60 );
		curl_setopt( $this->curlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPHEADER, array( 'Expect:' ) );
		curl_setopt( $this->curlHandle, CURLOPT_ENCODING, 'UTF-8' );
		curl_setopt( $this->curlHandle, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $this->curlHandle, CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $this->curlHandle, CURLOPT_POSTFIELDS, $postVars );
		return curl_exec( $this->curlHandle );
	}

	function __destruct() {
		curl_close( $this->curlHandle );
		@unlink('/tmp/cookies'.$this->id.'.dat');
	}

}

/**
 * Interface to the wiki's API
 **/
class WikiApi {
	private $http, $token, $url;

	/**
	 * Construct the class instance
	 * @param $url string The URL used to access the API
	 **/
	function __construct( $url ) {
		$this->http = new PageTriageHttp;
		$this->url = $url;
	}

	/**
	 * Send a get query to the API
	 * @param $query string y The query string
	 * @return string The result from the API
	 **/
	function get( $query ) {
		$result = $this->http->get( $this->url.$query );
		return unserialize( $result );
	}

	/**
	 * Send a post query to the API
	 * @param $query string The query string
	 * @param $postVars
	 * @return string The result from the API
	 */
	function post( $query, $postVars ) {
		$result = $this->http->post( $this->url.$query, $postVars );
		return unserialize( $result );
	}

	/**
	 * Log into the wiki via the API
	 * @param $username string The user's username
	 * @param $password string The user's password
	 * @return string The result from the API
	 **/
	function login( $username, $password ) {
		$postVars = array( 'lgname' => $username, 'lgpassword' => $password );
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
	 **/
	function getToken () {
		$params = array(
			'action' => 'query',
			'format' => 'php',
			'prop' => 'info',
			'intoken' => 'edit',
			'titles' => 'Main Page'
		);
		$params = http_build_query( $params );
		$result = $this->get( '?'.$params );
		foreach ( $result['query']['pages'] as $page ) {
			return $page['edittoken'];
		}
	}

	/**
	 * Get the contents of a page
	 * @param $title string The title of the wikipedia page to fetch
	 * @return string The wikitext for the page (or false)
	 **/
	function getPage( $title ) {
		$params = array(
			'action' => 'query',
			'format' => 'php',
			'prop' => 'revisions',
			'titles' => $title,
			'rvlimit' => 1,
			'rvprop' => 'content'
		);
		$params = http_build_query( $params );
		$result = $this->get('?'.$params );
		foreach ( $result['query']['pages'] as $page ) {
			if ( isset( $page['revisions'][0]['*'] ) ) {
				return $page['revisions'][0]['*'];
			} else {
				return false;
			}
		}
	}

	/**
	 * Get the newest pages from the wiki
	 * @param $namespace int The namespace to limit the search to
	 * @param $limit int The maximum number of pages to return
	 * @return array of titles
	 **/
	function getNewPages( $namespace = 0, $limit = 10 ) {
		$params = array(
			'action' => 'query',
			'list' => 'recentchanges',
			'format' => 'php',
			'rctype' => 'new',
			'rcprop' => 'title',
			'rcnamespace' => $namespace,
			'rclimit' => $limit
		);
		$params = http_build_query( $params );
		$result = $this->get( '?'.$params );
		$pages = $result['query']['recentchanges'];
		$pageTitles = array();
		foreach ( $pages as $page ) {
			$pageTitles[] = $page['title'];
		}
		return $pageTitles;
	}

	/**
	 * Create a new page on the wiki
	 * @param $title string The title of the new page
	 * @param $text string The text of the new page
	 * @return string The result from the API
	 **/
	function createPage ( $title, $text ) {
		if ( !$this->token ) {
			$this->token = $this->getToken();
		}
		$params = array(
			'title' => $title,
			'text' => $text,
			'token' => $this->token,
			'summary' => 'Importing article from another wiki for testing purposes',
			'createonly' => '1'
		);
		return $this->post('?action=edit&format=php', $params);
	}
}

if ( isset( $_SERVER ) && isset( $_SERVER['REQUEST_METHOD'] ) ) {
	print( 'This script must be run from the command line.' );
	die();
}

if ( !isset( $argv[1] ) || !isset( $argv[2] ) || !isset( $argv[3] ) ) {
	print( "The correct syntax is:\nimportNewPages.php <# of articles> <username> <password> <source API path> <destination API path>\n" );
	die();
}

if ( isset( $argv[4] ) ) {
	$source = new WikiApi( $argv[4] );
} else {
	$source = new WikiApi( 'http://en.wikipedia.org/w/api.php' );
}

$pages = array();

if ( $argv[1] > 0 && $argv[1] <= 10000 ) {
	$pages = $source->getNewPages( 0, $argv[1] );
} else {
	$pages = $source->getNewPages( 0, 10 );
}

if ( isset( $argv[5] ) ) {
	$destination = new WikiApi( $argv[5] );
} else {
	$destination = new WikiApi( 'http://ee-prototype.wmflabs.org/w/api.php' );
}
$destination->login( $argv[2], $argv[3] );

foreach ( $pages as $page ) {
	$text = $source->getPage( $page );
	$text = $text."\n[[Category:Copied from another wiki for testing purposes only]]";
	$result = $destination->createPage( $page, $text );
	if ( isset( $result['error'] ) ) {
		echo "Error: $page\n";
	} else {
		echo "Success: $page\n";
	}
}

echo "Done.\n";
