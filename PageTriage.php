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
$wgAutoloadClasses['SpecialPageTriageList'] = $dir . 'SpecialPageTriageList.php';
$wgExtensionMessagesFiles['PageTriage'] = $dir . 'PageTriage.i18n.php';
$wgExtensionAliasesFiles['PageTriage'] = $dir . 'PageTriage.alias.php';
$wgSpecialPages['PageTriage'] = 'SpecialPageTriage';
$wgSpecialPageGroups['PageTriage'] = 'changes';
$wgSpecialPages['PageTriageList'] = 'SpecialPageTriageList';
$wgSpecialPageGroups['PageTriageList'] = 'changes';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'efPageTriageSchemaUpdates';

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efPageTriageSchemaUpdates( $updater = null ) {
	$base = dirname( __FILE__ ) . "/sql";
	if ( $updater === null ) {
		global $wgDBtype, $wgExtNewTables, $wgExtNewFields;
		if ( $wgDBtype == 'mysql' ) {
			$wgExtNewTables[] = array( 'pagetriage', $base . '/PageTriage.sql' );
		}
	} else {
		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionTable( 'pagetriage', "$base/PageTriage.sql" );
		}
	}
	return true;
}
