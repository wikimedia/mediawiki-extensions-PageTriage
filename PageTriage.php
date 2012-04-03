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
$wgAutoloadClasses['ApiPageTriageStats'] = $dir . 'api/ApiPageTriageStats.php';

// custom exceptions
$wgAutoloadClasses['MWArticleMetadataMissingPageIdException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWArticleMetadataMetaDataOutofBoundException'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['MWPageTriageUtilInvalidNumberException'] = $dir . 'includes/PageTriageUtil.php';

// api modules
$wgAPIModules['pagetriagelist'] = 'ApiPageTriageList';
$wgAPIModules['pagetriagegetmetadata'] = 'ApiPageTriageGetMetadata';
$wgAPIModules['pagetriagestats'] = 'ApiPageTriageStats';

// hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efPageTriageSchemaUpdates';
$wgHooks['SpecialMovepageAfterMove'][] = 'PageTriageHooks::onSpecialMovepageAfterMove';
$wgHooks['NewRevisionFromEditComplete'][] = 'PageTriageHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticleInsertComplete'][] = 'PageTriageHooks::onArticleInsertComplete';
$wgHooks['ArticleSaveComplete'][] = 'PageTriageHooks::onArticleSaveComplete';
$wgHooks['UnitTestsList'][] = 'efPageTriageUnitTests'; // unit tests
$wgHooks['GetPreferences'][] = 'PageTriageHooks::onGetPreferences';
$wgHooks['ArticleViewHeader'][] = 'PageTriageHooks::onArticleViewHeader';
$wgHooks['ArticleDeleteComplete'][] = 'PageTriageHooks::onArticleDeleteComplete';

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
		'external/backbone.js', // required for underscore
		'external/date.js',
		'external/datejs-mw.js',
		'external/jquery.waypoints.js'
	),
	'messages' => array(
		'sunday',
		'monday',
		'tuesday',
		'wedneday',
		'thursday',
		'friday',
		'saturday',
		'sun',
		'mon',
		'tue',
		'wed',
		'thu',
		'fri',
		'sat',		
		'january',
		'february',
		'march',
		'april',
		'may-long',
		'june',
		'july',
		'august',
		'september',
		'october',
		'november',
		'december',
		'jan',
		'feb',
		'mar',
		'apr',
		'may',
		'jun',
		'jul',
		'aug',
		'sep',
		'oct',
		'nov',
		'dec'
	)
);

$wgResourceModules['ext.pageTriage.models'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'mediawiki.Title',
		'ext.pageTriage.external'
	),
	'scripts' => array(
		'ext.pageTriage.models/ext.pageTriage.article.js',
		'ext.pageTriage.models/ext.pageTriage.stats.js'
	)
);


$wgResourceModules['ext.pageTriage.views'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'mediawiki.jqueryMsg',
		'ext.pageTriage.models',
		'jquery.ui.button'
	),
	'scripts' => array(
		'ext.pageTriage.views/ext.pageTriage.listItem.js',
		'ext.pageTriage.views/ext.pageTriage.listControlNav.js',
		'ext.pageTriage.views/ext.pageTriage.listStatsNav.js',
		'ext.pageTriage.views/ext.pageTriage.listView.js'
	),
	'styles' => array(
		'ext.pageTriage.views/ext.pageTriage.css', // stuff that's shared across all views
		'ext.pageTriage.views/ext.pageTriage.listItem.css',
		'ext.pageTriage.views/ext.pageTriage.listControlNav.css',
		'ext.pageTriage.views/ext.pageTriage.listStatsNav.css'
	),
	'messages' => array(
		'days',
		'pagetriage-hist',
		'pagetriage-bytes',
		'pagetriage-edits',
		'pagetriage-categories',
		'pagetriage-no-categories',
		'pagetriage-images',
		'pagetriage-orphan',
		'pagetriage-no-author',
		'pagetriage-byline',
		'pagetriage-editcount',
		'pagetriage-author-not-autoconfirmed',
		'pagetriage-author-blocked',
		'pagetriage-author-bot',
		'pagetriage-creation-dateformat',
		'pagetriage-user-creation-dateformat',
		'pagetriage-special-contributions',
		'pagetriage-showing',
		'pagetriage-filter-list-prompt',
		'pagetriage-article-count',
		'pagetriage-viewing',
		'pagetriage-triage',
		'pagetriage-show-only',
		'pagetriage-filter-show-heading',
		'pagetriage-filter-triaged-edits',
		'pagetriage-filter-nominated-for-deletion',
		'pagetriage-filter-bot-edits',
		'pagetriage-filter-redirects',
		'pagetriage-filter-namespace-heading',
		'pagetriage-filter-user-heading',
		'pagetriage-filter-tag-heading',
		'pagetriage-filter-second-show-heading',
		'pagetriage-filter-no-categories',
		'pagetriage-filter-orphan',
		'pagetriage-filter-non-autoconfirmed',
		'pagetriage-filter-blocked',
		'pagetriage-filter-set-button',
		'pagetriage-stats-untriaged-age',
		'pagetriage-stats-less-than-a-day',
		'pagetriage-stats-top-triagers',
		'days',
		'pagetriage-filter-ns-article',
		'pagetriage-filter-ns-all',
	)
);

$wgResourceModules['ext.pageTriage.article'] = $ptResourceTemplate + array(
	'styles' => 'ext.pageTriage.article/ext.pageTriage.article.css',
);
