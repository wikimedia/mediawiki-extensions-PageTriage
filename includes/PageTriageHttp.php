<?php

namespace MediaWiki\Extension\PageTriage;

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
		\Wikimedia\suppressWarnings();
		unlink( '/tmp/cookies'.$this->id.'.dat' );
		\Wikimedia\restoreWarnings();
	}

}
