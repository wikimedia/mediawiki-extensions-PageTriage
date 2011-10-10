<?php

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install this extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/PageTriage/PageTriage.php" );
EOT;
	exit( 1 );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'PageTriage',
	'version' => '0.1',
	'url' => 'http://www.mediawiki.org/wiki/Extension:PageTriage',
	'author' => '',
	'descriptionmsg' => 'pagetriage-desc',
);

$dir = dirname( __FILE__ ) . '/';

$wgAutoloadClasses['SpecialPageTriage'] = $dir . 'SpecialPageTriage.php';
$wgExtensionMessagesFiles['PageTriage'] = $dir . 'PageTriage.i18n.php';
$wgExtensionAliasesFiles['PageTriage'] = $dir . 'PageTriage.alias.php';
$wgSpecialPages['PageTriage'] = 'SpecialPageTriage';
$wgSpecialPageGroups['PageTriage'] = 'changes';
