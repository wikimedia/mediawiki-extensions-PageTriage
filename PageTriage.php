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

$wgAutoloadClasses['ApiPageTriageList'] = $dir . 'api/ApiPageTriageList.php';
$wgAutoloadClasses['ApiPageTriageGetMetadata'] = $dir . 'api/ApiPageTriageGetMetadata.php';

// custom exceptions
$wgAutoloadClasses['MWArticleMetadataMissingPageIdException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWArticleMetadataMetaDataOutofBoundException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWPageTriageUtilInvalidNumberException'] = $dir . 'includes/PageTriageUtil.php';

// api modules
$wgAPIModules['pagetriagelist'] = 'ApiPageTriageList';
$wgAPIModules['pagetriagegetmetadata'] = 'ApiPageTriageGetMetadata';

// hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efPageTriageSchemaUpdates';
$wgHooks['SpecialMovepageAfterMove'][] = 'PageTriageHooks::onSpecialMovepageAfterMove';
$wgHooks['NewRevisionFromEditComplete'][] = 'PageTriageHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticleInsertComplete'][] = 'PageTriageHooks::onArticleInsertComplete';
$wgHooks['ArticleSaveComplete'][] = 'PageTriageHooks::onArticleSaveComplete';
$wgHooks['UnitTestsList'][] = 'efPageTriageUnitTests'; // unit tests
$wgHooks['GetPreferences'][] = 'PageTriageHooks::onGetPreferences';
$wgHooks['ArticleViewHeader'][] = 'PageTriageHooks::onArticleViewHeader';

$wgPageTriageMarkPatrolledLinkExpiry = 3600 * 24 * 30; // 30 days

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efPageTriageSchemaUpdates( $updater = null ) {
	$base = dirname( __FILE__ ) . "/sql";
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
	$files[] = $base . '/phpunit/ArticleMetadataTest.php';
	$files[] = $base . '/phpunit/ApiPageTriageGetMetadataTest.php';
	return true;
}

// Register ResourceLoader modules
$ptResourceTemplate = array(
	'localBasePath' => dirname( __FILE__ ). '/modules',
	'remoteExtPath' => 'PageTriage/modules'
);

$wgResourceModules['ext.pageTriage.external'] = $ptResourceTemplate + array(
	'scripts' => array(
		'external/underscore.js',
		'external/backbone.js'
	)
);

/*$wgResourceModules['ext.pageTriage.core'] = $ptResourceTemplate + array(
	'scripts' => 'ext.pageTriage.core/ext.pageTriage.core.js'
);
*/

$wgResourceModules['ext.pageTriage.models'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'ext.pageTriage.external'
	),
	'scripts' => array(
		'ext.pageTriage.models/ext.pageTriage.article.js',
		'ext.pageTriage.views/ext.pageTriage.articleListItem.js'		
	),
	'styles' => array(
		'ext.pageTriage.views/ext.pageTriage.articleListItem.css'
	)
);

/*
$wgResourceModules['ext.pageTriage.views'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'ext.pageTriage.models'
	),
	'scripts' => array(
		'ext.pageTriage.views/ext.pageTriage.articleListItem.js'
	),
	'styles' => array(
		'ext.pageTriage.views/ext.pageTriage.articleListItem.css'
	)
);
*/

$wgResourceModules['ext.pageTriage.article'] = $ptResourceTemplate + array(
	'styles' => 'ext.pageTriage.article/ext.pageTriage.article.css',
);
