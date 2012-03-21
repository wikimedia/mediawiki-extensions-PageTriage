<?php
/**
 * MediaWiki PageTriage extension
 * http://www.mediawiki.org/wiki/Extension:PageTriage
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * This program is distributed WITHOUT ANY WARRANTY.
 */

/**
 * This file loads everything needed for the PageTriage extension to function.
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */

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
	'url' => 'https://www.mediawiki.org/wiki/Extension:PageTriage',
	'author' => '',
	'descriptionmsg' => 'pagetriage-desc',
);

// Begin configuration variables
$wgPageTriagePageIdPerRequest = 20;
// End configuration variables

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['PageTriage'] = $dir . 'PageTriage.i18n.php';
$wgExtensionMessagesFiles['PageTriageAlias'] = $dir . 'PageTriage.alias.php';

$wgAutoloadClasses['SpecialPageTriage'] = $dir . 'SpecialPageTriage.php';
$wgSpecialPages['PageTriage'] = 'SpecialPageTriage';
$wgSpecialPageGroups['PageTriage'] = 'changes';
$wgAutoloadClasses['ArticleMetadata'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['PageTriageUtil'] = $dir . 'includes/PageTriageUtil.php';
$wgAutoloadClasses['PageTriageHooks'] = $dir . 'PageTriage.hooks.php';

$wgAutoloadClasses['ApiQueryPageTriage'] = $dir . 'api/ApiQueryPageTriage.php';
$wgAutoloadClasses['ApiPageTriageList'] = $dir . 'api/ApiPageTriageList.php';
$wgAutoloadClasses['ApiPageTriageGetMetadata'] = $dir . 'api/ApiPageTriageGetMetadata.php';

// custom exceptions
$wgAutoloadClasses['MWArticleMetadataMissingPageIdException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWArticleMetadataMetaDataOutofBoundException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWPageTriageUtilInvalidNumberException'] = $dir . 'includes/PageTriageUtil.php';

// api modules
$wgAPIModules['pagetriage'] = 'ApiQueryPageTriage';
$wgAPIModules['pagetriagelist'] = 'ApiPageTriageList';
$wgAPIModules['pagetriagegetmetadata'] = 'ApiPageTriageGetMetadata';

// hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efPageTriageSchemaUpdates';
$wgHooks['SpecialMovepageAfterMove'][] = 'PageTriageHooks::onSpecialMovepageAfterMove';
$wgHooks['NewRevisionFromEditComplete'][] = 'PageTriageHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticleInsertComplete'][] = 'PageTriageHooks::onArticleInsertComplete';
$wgHooks['ArticleSaveComplete'][] = 'PageTriageHooks::onArticleSaveComplete';
$wgHooks['UnitTestsList'][] = 'efPageTriageUnitTests'; // unit tests

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
	$updater->addExtensionTable( 'pagetriage_tags', $base . '/PageTriageTags.sql' );
	$updater->addExtensionTable( 'pagetriage_page_tags', $base . '/PageTriagePageTags.sql' );
	$updater->addExtensionTable( 'pagetriage_page', $base . '/PageTriagePage.sql' );
	$updater->addExtensionTable( 'pagetriage_log', $base . '/PageTriageLog.sql' );

	return true;
}

/**
 * UnitTestsList hook handler - adds unit test files to the unit tester
 * @param $files array
 * @return bool
 */
function efPageTriageUnitTests( &$files ) {
	$base = dirname( __FILE__ ) . '/tests';
	$files[] = $base . '/phpunit/SpecialPageTriageTest.php';
	return true;
}

// Register ResourceLoader modules
$wgResourceModules['ext.pageTriage.core'] = array(
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'PageTriage',
	'scripts' => 'ext.pageTriage.core.js'
);
