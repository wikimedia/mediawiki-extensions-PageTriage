<?php
/**
 * This script imports newly created articles from one wiki to another.
 * It only copies the text of the articles, not the history or editor information.
 * This script should only be used for testing purposes. For normal transwiki importing, refer to:
 * http://meta.wikimedia.org/wiki/Help:Import
 *
 * This script can only be run from the command line.
 * The syntax is:
 *   php importNewPages.php <# of articles> \
 *     <username> <password> \
 *     <source API path> <destination API path>
 * The API path parameters are optional.
 **/

/**
 * Interface to cURL
 **/
class PageTriageHttp {
	private $curlHandle;
	private $id;

	public function __construct() {
		$this->id = rand( 0, 1000000 );
		$this->curlHandle = curl_init();
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEJAR, '/tmp/cookies'.$this->id.'.dat' );
		curl_setopt( $this->curlHandle, CURLOPT_COOKIEFILE, '/tmp/cookies'.$this->id.'.dat' );
		curl_setopt( $this->curlHandle, CURLOPT_MAXCONNECTS, 10 );
	}

	/**
	 * @param string $url
	 * @return mixed
	 */
	public function get( $url ) {
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
	 * @param string $url
	 * @param array $postVars
	 * @return mixed
	 */
	public function post( $url, $postVars ) {
		curl_setopt( $this->curlHandle, CURLOPT_URL, $url );
		curl_setopt( $this->curlHandle, CURLOPT_USERAGENT, 'php PageTriageBot' );
		curl_setopt( $this->curlHandle, CURLOPT_POST, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $this->curlHandle, CURLOPT_TIMEOUT, 60 );
		curl_setopt( $this->curlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->curlHandle, CURLOPT_HTTPHEADER, [ 'Expect:' ] );
		curl_setopt( $this->curlHandle, CURLOPT_ENCODING, 'UTF-8' );
		curl_setopt( $this->curlHandle, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $this->curlHandle, CURLOPT_MAXREDIRS, 5 );
		curl_setopt( $this->curlHandle, CURLOPT_POSTFIELDS, $postVars );
		return curl_exec( $this->curlHandle );
	}

	public function __destruct() {
		curl_close( $this->curlHandle );
		Wikimedia\suppressWarnings();
		unlink( '/tmp/cookies'.$this->id.'.dat' );
		Wikimedia\restoreWarnings();
	}

}

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

if ( isset( $_SERVER ) && isset( $_SERVER['REQUEST_METHOD'] ) ) {
	print ( 'This script must be run from the command line.' );
	die();
}

if ( !isset( $argv[1] ) || !isset( $argv[2] ) || !isset( $argv[3] ) ) {
	print (
		"The correct syntax is:\nimportNewPages.php <# of articles> <username> <password>".
		"<source API path> <destination API path>\n"
	);
	die();
}

if ( isset( $argv[4] ) ) {
	$source = new WikiApi( $argv[4] );
} else {
	$source = new WikiApi( 'http://en.wikipedia.org/w/api.php' );
}

$pages = [];

if ( $argv[1] > 0 && $argv[1] <= 10000 ) {
	$pages = $source->getNewPages( 0, $argv[1] );
} else {
	$pages = $source->getNewPages( 0, 10 );
}

if ( isset( $argv[5] ) ) {
	$destination = new WikiApi( $argv[5] );
} else {
	$destination = new WikiApi( 'http://en.wikipedia.beta.wmflabs.org/w/api.php' );
	// $destination = new WikiApi( 'http://ee-prototype.wmflabs.org/w/api.php' );
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
