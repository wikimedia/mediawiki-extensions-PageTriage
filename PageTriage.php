<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PageTriage' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PageTriage'] = __DIR__ . '/i18n';
	/*wfWarn(
		'Deprecated PHP entry point used for PageTriage extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);*/
	return;
} else {
	die( 'This version of the PageTriage extension requires MediaWiki 1.29+' );
}
