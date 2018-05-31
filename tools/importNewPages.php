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

use MediaWiki\Extension\PageTriage\WikiApi;

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
