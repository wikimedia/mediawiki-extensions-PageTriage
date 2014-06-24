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
	'version' => '0.2.0',
	'url' => 'https://www.mediawiki.org/wiki/Extension:PageTriage',
	'author' => array(
		'Ryan Kaldari',
		'Benny Situ',
		'Ian Baker',
		'Andrew Garrett',
	),
	'descriptionmsg' => 'pagetriage-desc',
);

// Begin configuration variables
$wgPageTriagePagesPerRequest = 20; // Maximum number of articles for the API to retrieve at once
$wgPageTriageInfiniteScrolling = true; // Whether or not to use infinite scrolling in the page list
$wgPageTriageStickyControlNav = true; // Whether or not the top nav bar should float
$wgPageTriageStickyStatsNav = true; // Whether or not the bottom nav bar should float
$wgPageTriageMarkPatrolledLinkExpiry = 3600 * 24; // 1 day - How long after visiting Special:NewPagesFeed do we show review links on articles
$wgPageTriageNoIndexTemplates = 'No_index_templates'; // Pages containing templates defined in this title would not be indexed.
$wgPageTriageLearnMoreUrl = '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help';
$wgPageTriageProjectLink = 'Wikipedia:Page Curation';
$wgPageTriageFeedbackUrl = '//en.wikipedia.org/wiki/Wikipedia_talk:Page_Curation';
$wgPageTriageEnableCurationToolbar = true; // enable the curation toolbar?
$wgPageTriageCurationModules = array(
	'articleInfo' => array(
		'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#PageInfo',
		'namespace' => array( NS_MAIN, NS_USER ),
	),
	'wikiLove' => array(
		'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#WikiLove', // depends on WikiLove extension
		'namespace' => array( NS_MAIN, NS_USER ),
	),
	'mark' => array(
		'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#MarkReviewed',
		'namespace' => array( NS_MAIN, NS_USER ),
		'note' => array( NS_MAIN ),
	),
	'tags' => array(
		'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#AddTags',
		'namespace' => array( NS_MAIN ),
	),
	'delete' => array(
		'helplink' => '//en.wikipedia.org/wiki/Wikipedia:Page_Curation/Help#MarkDeletion',
		'namespace' => array( NS_MAIN, NS_USER ),
	),
);
$wgPageTriageCacheVersion = '1.4'; // version number to be added to cache key so that cache can be refreshed easily
$wgPageTriageNamespaces = array( NS_MAIN, NS_USER  ); // only include these namespaces for pagetriage
$wgTalkPageNoteTemplate = array(
	'Mark' => 'Reviewednote-NPF',
	'UnMark' => array( 'note' => 'Unreviewednote-NPF', 'nonote' => 'Unreviewednonote-NPF' ),
	'Tags' => 'Taggednote-NPF'
);
// Set which PageTriage Echo events (defined in PageTriageHooks::onBeforeCreateEchoEvent)
// will be enabled as notifications
$wgPageTriageEnabledEchoEvents = array(
	'pagetriage-mark-as-reviewed',
	'pagetriage-add-maintenance-tag',
	'pagetriage-add-deletion-tag'
);
// Set default user options
$wgDefaultUserOptions['echo-subscriptions-web-page-review'] = true;
// This is overriden for new users in PageTriageHooks::onAccountCreated
$wgDefaultUserOptions['echo-subscriptions-email-page-review'] = false;
// End configuration variables


$dir = __DIR__ . '/';

$wgMessagesDirs['PageTriage'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PageTriage'] = $dir . 'PageTriage.i18n.php';
$wgExtensionMessagesFiles['PageTriageAlias'] = $dir . 'PageTriage.alias.php';

$wgAutoloadClasses['SpecialNewPagesFeed'] = $dir . 'SpecialNewPagesFeed.php';
$wgSpecialPages['NewPagesFeed'] = 'SpecialNewPagesFeed';
$wgSpecialPageGroups['NewPagesFeed'] = 'changes';
$wgAutoloadClasses['ArticleMetadata'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['PageTriage'] = $dir . 'includes/PageTriage.php';
$wgAutoloadClasses['PageTriageUtil'] = $dir . 'includes/PageTriageUtil.php';
$wgAutoloadClasses['PageTriageHooks'] = $dir . 'PageTriage.hooks.php';
$wgAutoloadClasses['ArticleCompileProcessor'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileInterface'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileBasicData'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileLinkCount'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileCategoryCount'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileSnippet'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileUserData'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['ArticleCompileDeletionTag'] = $dir . 'includes/ArticleMetadata.php';
$wgAutoloadClasses['PageTriageExternalTagsOptions'] = $dir . 'includes/PageTriageExternalTagsOptions.php';
$wgAutoloadClasses['PageTriageExternalDeletionTagsOptions'] = $dir . 'includes/PageTriageExternalDeletionTagsOptions.php';
$wgAutoloadClasses['PageTriageLogFormatter'] = $dir . 'includes/PageTriageLogFormatter.php';
$wgAutoloadClasses['PageTriageNotificationFormatter'] = $dir . 'includes/PageTriageNotificationFormatter.php';

$wgAutoloadClasses['ApiPageTriageList'] = $dir . 'api/ApiPageTriageList.php';
$wgAutoloadClasses['ApiPageTriageStats'] = $dir . 'api/ApiPageTriageStats.php';
$wgAutoloadClasses['ApiPageTriageAction'] = $dir . 'api/ApiPageTriageAction.php';
$wgAutoloadClasses['ApiPageTriageTemplate'] = $dir . 'api/ApiPageTriageTemplate.php';
$wgAutoloadClasses['ApiPageTriageTagging'] = $dir . 'api/ApiPageTriageTagging.php';

// custom exceptions
$wgAutoloadClasses['MWPageTriageUtilInvalidNumberException'] = $dir . 'includes/PageTriageUtil.php';
$wgAutoloadClasses['MWPageTriageMissingRevisionException'] = $dir . 'includes/PageTriage.php';

// api modules
$wgAPIModules['pagetriagelist'] = 'ApiPageTriageList';
$wgAPIModules['pagetriagestats'] = 'ApiPageTriageStats';
$wgAPIModules['pagetriageaction'] = 'ApiPageTriageAction';
$wgAPIModules['pagetriagetemplate'] = 'ApiPageTriageTemplate';
$wgAPIModules['pagetriagetagging'] = 'ApiPageTriageTagging';

// hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efPageTriageSchemaUpdates';
$wgHooks['SpecialMovepageAfterMove'][] = 'PageTriageHooks::onSpecialMovepageAfterMove';
$wgHooks['NewRevisionFromEditComplete'][] = 'PageTriageHooks::onNewRevisionFromEditComplete';
$wgHooks['ArticleInsertComplete'][] = 'PageTriageHooks::onArticleInsertComplete';
$wgHooks['ArticleSaveComplete'][] = 'PageTriageHooks::onArticleSaveComplete';
$wgHooks['UnitTestsList'][] = 'efPageTriageUnitTests'; // unit tests
$wgHooks['GetPreferences'][] = 'PageTriageHooks::onGetPreferences';
$wgHooks['ArticleViewFooter'][] = 'PageTriageHooks::onArticleViewFooter';
$wgHooks['ArticleDeleteComplete'][] = 'PageTriageHooks::onArticleDeleteComplete';
$wgHooks['MarkPatrolledComplete'][] = 'PageTriageHooks::onMarkPatrolledComplete';
$wgHooks['BlockIpComplete'][] = 'PageTriageHooks::onBlockIpComplete';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'PageTriageHooks::onResourceLoaderGetConfigVars';
$wgHooks['BeforeCreateEchoEvent'][] = 'PageTriageHooks::onBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'PageTriageHooks::onEchoGetDefaultNotifiedUsers';
$wgHooks['AddNewAccount'][] = 'PageTriageHooks::onAccountCreated';

// logging
$wgLogTypes[] = 'pagetriage-curation';
$wgLogTypes[] = 'pagetriage-deletion';
$wgLogActionsHandlers['pagetriage-curation/reviewed'] = 'LogFormatter';
$wgLogActionsHandlers['pagetriage-curation/unreviewed'] = 'LogFormatter';
$wgLogActionsHandlers['pagetriage-curation/tag'] = 'PageTriageLogFormatter';
$wgLogActionsHandlers['pagetriage-curation/delete'] = 'PageTriageLogFormatter';
$wgLogActionsHandlers['pagetriage-deletion/delete'] = 'PageTriageLogFormatter';

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efPageTriageSchemaUpdates( $updater = null ) {
	$base = dirname( __FILE__ ) . "/sql";
	// tables
	$updater->addExtensionTable( 'pagetriage_tags', $base . '/PageTriageTags.sql' );
	$updater->addExtensionTable( 'pagetriage_page_tags', $base . '/PageTriagePageTags.sql' );
	$updater->addExtensionTable( 'pagetriage_page', $base . '/PageTriagePage.sql' );
	$updater->addExtensionTable( 'pagetriage_log', $base . '/PageTriageLog.sql' );
	// patches
	$updater->addExtensionIndex( 'pagetriage_page', 'ptrp_reviewed_updated', $base . '/PageTriagePagePatch.sql' );
	return true;
}

/**
 * UnitTestsList hook handler - adds unit test files to the unit tester
 * @param $files array
 * @return bool
 */
function efPageTriageUnitTests( &$files ) {
	$base = dirname( __FILE__ ) . '/tests';
	$files[] = $base . '/phpunit/SpecialNewPagesFeedTest.php';
	$files[] = $base . '/phpunit/ArticleMetadataTest.php';
	$files[] = $base . '/phpunit/ApiPageTriageActionTest.php';
	return true;
}

// Register ResourceLoader modules
$ptResourceTemplate = array(
	'localBasePath' => dirname( __FILE__ ). '/modules',
	'remoteExtPath' => 'PageTriage/modules'
);

// where can the template API find the templates?
$ptTemplatePath = $ptResourceTemplate['localBasePath'];

// Tags options message
$wgPageTriageTagsOptionsMessages = array (
	'pagetriage-tags-title',
	'pagetriage-tags-cat-common-label',
	'pagetriage-tags-cat-metadata-label',
	'pagetriage-tags-cat-cleanup-label',
	'pagetriage-tags-cat-neutrality-label',
	'pagetriage-tags-cat-sources-label',
	'pagetriage-tags-cat-structure-label',
	'pagetriage-tags-cat-unwantedcontent-label',
	'pagetriage-tags-cat-verifiability-label',
	'pagetriage-tags-cat-writingstyle-label',
	'pagetriage-tags-cat-moretags-label',
	'pagetriage-tags-cat-all-label',
	'pagetriage-tags-linkrot-label',
	'pagetriage-tags-linkrot-desc',
	'pagetriage-tags-copyedit-label',
	'pagetriage-tags-copyedit-desc',
	'pagetriage-tags-morefootnotes-label',
	'pagetriage-tags-morefootnotes-desc',
	'pagetriage-tags-refimprove-label',
	'pagetriage-tags-refimprove-desc',
	'pagetriage-tags-uncategorised-label',
	'pagetriage-tags-uncategorised-desc',
	'pagetriage-tags-unreferenced-label',
	'pagetriage-tags-unreferenced-desc',
	'pagetriage-tags-deadend-label',
	'pagetriage-tags-deadend-desc',
	'pagetriage-tags-externallinks-label',
	'pagetriage-tags-externallinks-desc',
	'pagetriage-tags-catimprove-label',
	'pagetriage-tags-catimprove-desc',
	'pagetriage-tags-orphan-label',
	'pagetriage-tags-orphan-desc',
	'pagetriage-tags-overlinked-label',
	'pagetriage-tags-overlinked-desc',
	'pagetriage-tags-cleanup-label',
	'pagetriage-tags-cleanup-desc',
	'pagetriage-tags-expertsubject-label',
	'pagetriage-tags-expertsubject-desc',
	'pagetriage-tags-prose-label',
	'pagetriage-tags-prose-desc',
	'pagetriage-tags-roughtranslation-label',
	'pagetriage-tags-roughtranslation-desc',
	'pagetriage-tags-advert-label',
	'pagetriage-tags-advert-desc',
	'pagetriage-tags-autobiography-label',
	'pagetriage-tags-autobiography-desc',
	'pagetriage-tags-coi-label',
	'pagetriage-tags-coi-desc',
	'pagetriage-tags-peacock-label',
	'pagetriage-tags-peacock-desc',
	'pagetriage-tags-pov-label',
	'pagetriage-tags-pov-desc',
	'pagetriage-tags-weasel-label',
	'pagetriage-tags-weasel-desc',
	'pagetriage-tags-blpsources-label',
	'pagetriage-tags-blpsources-desc',
	'pagetriage-tags-originalresearch-label',
	'pagetriage-tags-originalresearch-desc',
	'pagetriage-tags-primarysources-label',
	'pagetriage-tags-primarysources-desc',
	'pagetriage-tags-onesource-label',
	'pagetriage-tags-onesource-desc',
	'pagetriage-tags-condense-label',
	'pagetriage-tags-condense-desc',
	'pagetriage-tags-leadmissing-label',
	'pagetriage-tags-leadmissing-desc',
	'pagetriage-tags-leadrewrite-label',
	'pagetriage-tags-leadrewrite-desc',
	'pagetriage-tags-leadtoolong-label',
	'pagetriage-tags-leadtoolong-desc',
	'pagetriage-tags-leadtooshort-label',
	'pagetriage-tags-leadtooshort-desc',
	'pagetriage-tags-cleanupreorganise-label',
	'pagetriage-tags-cleanupreorganise-desc',
	'pagetriage-tags-sections-label',
	'pagetriage-tags-sections-desc',
	'pagetriage-tags-stub-label',
	'pagetriage-tags-stub-desc',
	'pagetriage-tags-verylong-label',
	'pagetriage-tags-verylong-desc',
	'pagetriage-tags-closeparaphrasing-label',
	'pagetriage-tags-closeparaphrasing-desc',
	'pagetriage-tags-copypaste-label',
	'pagetriage-tags-copypaste-desc',
	'pagetriage-tags-nonfree-label',
	'pagetriage-tags-nonfree-desc',
	'pagetriage-tags-notability-label',
	'pagetriage-tags-notability-desc',
	'pagetriage-tags-disputed-label',
	'pagetriage-tags-disputed-desc',
	'pagetriage-tags-citationstyle-label',
	'pagetriage-tags-citationstyle-desc',
	'pagetriage-tags-hoax-label',
	'pagetriage-tags-hoax-desc',
	'pagetriage-tags-nofootnotes-label',
	'pagetriage-tags-nofootnotes-desc',
	'pagetriage-tags-confusing-label',
	'pagetriage-tags-confusing-desc',
	'pagetriage-tags-essaylike-label',
	'pagetriage-tags-essaylike-desc',
	'pagetriage-tags-fansite-label',
	'pagetriage-tags-fansite-desc',
	'pagetriage-tags-notenglish-label',
	'pagetriage-tags-notenglish-desc',
	'pagetriage-tags-technical-label',
	'pagetriage-tags-technical-desc',
	'pagetriage-tags-tense-label',
	'pagetriage-tags-tense-desc',
	'pagetriage-tags-tone-label',
	'pagetriage-tags-tone-desc',
	'pagetriage-tags-allplot-label',
	'pagetriage-tags-allplot-desc',
	'pagetriage-tags-fiction-label',
	'pagetriage-tags-fiction-desc',
	'pagetriage-tags-inuniverse-label',
	'pagetriage-tags-inuniverse-desc',
	'pagetriage-tags-outofdate-label',
	'pagetriage-tags-outofdate-desc',
	'pagetriage-tags-overlydetailed-label',
	'pagetriage-tags-overlydetailed-desc',
	'pagetriage-tags-plot-label',
	'pagetriage-tags-plot-desc',
	'pagetriage-tags-recentism-label',
	'pagetriage-tags-recentism-desc',
	'pagetriage-tags-toofewopinions-label',
	'pagetriage-tags-toofewopinions-desc',
	'pagetriage-tags-unbalanced-label',
	'pagetriage-tags-unbalanced-desc',
	'pagetriage-tags-update-label',
	'pagetriage-tags-update-desc',
	'pagetriage-tags-param-date-label',
	'pagetriage-tags-param-issues-label',
	'pagetriage-tags-param-blp-label',
	'pagetriage-tags-param-source-label',
	'pagetriage-tags-param-free-label',
	'pagetriage-tags-param-url-label',
	'pagetriage-tags-param-category-label',
	'pagetriage-tag-count-total',
	'pagetriage-button-add-tag',
	'pagetriage-button-add-tag-number',
	'pagetriage-button-add-parameters',
	'pagetriage-button-add-details',
	'pagetriage-button-edit-details',
	'pagetriage-button-mark-for-deletion',
	'cancel',
	'pagetriage-tags-param-free-yes-label',
	'pagetriage-tags-param-free-no-label',
	'pagetriage-tags-param-missing-required',
	'pagetriage-tags-param-date-format',
	'pagetriage-tags-param-for-label',
	'pagetriage-tags-tooltip',
);

// Deletion Tags options message
$wgPageTriageDeletionTagsOptionsMessages = array (
	'pagetriage-del-tags-cat-csd-label',
	'pagetriage-del-tags-cat-csd-desc',
	'pagetriage-del-tags-cat-prod-label',
	'pagetriage-del-tags-cat-prod-desc',
	'pagetriage-del-tags-cat-discussion-desc',
	'pagetriage-del-tags-dbg3-label',
	'pagetriage-del-tags-dbg3-desc',
	'pagetriage-del-tags-dbg10-label',
	'pagetriage-del-tags-dbg10-desc',
	'pagetriage-del-tags-dbg11-label',
	'pagetriage-del-tags-dbg11-desc',
	'pagetriage-del-tags-dbg12-label',
	'pagetriage-del-tags-dbg12-desc',
	'pagetriage-del-tags-dba1-label',
	'pagetriage-del-tags-dba1-desc',
	'pagetriage-del-tags-dba7-label',
	'pagetriage-del-tags-dba7-desc',
	'pagetriage-del-tags-dbg1-label',
	'pagetriage-del-tags-dbg1-desc',
	'pagetriage-del-tags-dba3-label',
	'pagetriage-del-tags-dba3-desc',
	'pagetriage-del-tags-dbg2-label',
	'pagetriage-del-tags-dbg2-desc',
	'pagetriage-del-tags-dbg4-label',
	'pagetriage-del-tags-dbg4-desc',
	'pagetriage-del-tags-dbg5-label',
	'pagetriage-del-tags-dbg5-desc',
	'pagetriage-del-tags-dbg7-label',
	'pagetriage-del-tags-dbg7-desc',
	'pagetriage-del-tags-dba10-label',
	'pagetriage-del-tags-dba10-desc',
	'pagetriage-del-tags-dba2-label',
	'pagetriage-del-tags-dba2-desc',
	'pagetriage-del-tags-dbu2-label',
	'pagetriage-del-tags-dbu2-desc',
	'pagetriage-del-tags-dbu3-label',
	'pagetriage-del-tags-dbu3-desc',
	'pagetriage-del-tags-dba9-label',
	'pagetriage-del-tags-dba9-desc',
	'pagetriage-del-tags-blpprod-label',
	'pagetriage-del-tags-blpprod-desc',
	'pagetriage-del-tags-prod-label',
	'pagetriage-del-tags-prod-desc',
	'pagetriage-del-tags-articlefordeletion-label',
	'pagetriage-del-tags-redirectsfordiscussion-label',
	'pagetriage-del-tags-miscellanyfordeletion-label',
	'pagetriage-tags-param-article-label',
	'pagetriage-tags-param-url-label',
	'pagetriage-tags-param-source-label',
	'pagetriage-del-tags-param-discussion-label',
	'pagetriage-del-title',
	'pagetriage-del-tooltip',
	'pagetriage-tags-param-url-label',
	'pagetriage-tags-param-article-label',
	'pagetriage-tags-param-source-label',
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
		'wednesday',
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
		'may_long',
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

$wgResourceModules['ext.pageTriage.util'] = $ptResourceTemplate + array(
	'scripts' => array(
		'ext.pageTriage.util/ext.pageTriage.viewUtil.js' // convenience functions for all views
	),
	'messages' => array(
		'pagetriage-api-error'
	)
);

$wgResourceModules['ext.pageTriage.models'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'mediawiki.Title',
		'mediawiki.user',
		'ext.pageTriage.external'
	),
	'scripts' => array(
		'ext.pageTriage.models/ext.pageTriage.article.js',
		'ext.pageTriage.models/ext.pageTriage.revision.js',
		'ext.pageTriage.models/ext.pageTriage.stats.js'
	),
	'messages' => array(
		'pipe-separator',
		'pagetriage-info-timestamp-date-format',
		'pagetriage-page-status-unreviewed',
		'pagetriage-page-status-autoreviewed',
		'pagetriage-page-status-reviewed',
		'pagetriage-page-status-delete',
		'pagetriage-page-status-reviewed-anonymous'
	)
);

$wgResourceModules['jquery.tipoff'] = $ptResourceTemplate + array(
	'styles' => 'jquery.tipoff/jquery.tipoff.css',
	'scripts' => 'jquery.tipoff/jquery.tipoff.js'
);

$wgResourceModules['ext.pageTriage.views.list'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'mediawiki.jqueryMsg',
		'ext.pageTriage.models',
		'ext.pageTriage.util',
		'jquery.tipoff',
		'jquery.ui.button',
		'jquery.spinner',
		'jquery.client'
	),
	'scripts' => array(
		'ext.pageTriage.views.list/ext.pageTriage.listItem.js',
		'ext.pageTriage.views.list/ext.pageTriage.listControlNav.js',
		'ext.pageTriage.views.list/ext.pageTriage.listStatsNav.js',
		'ext.pageTriage.views.list/ext.pageTriage.listView.js'
	),
	'styles' => array(
		'ext.pageTriage.css', // stuff that's shared across all views
		'ext.pageTriage.views.list/ext.pageTriage.listItem.css',
		'ext.pageTriage.views.list/ext.pageTriage.listControlNav.css',
		'ext.pageTriage.views.list/ext.pageTriage.listStatsNav.css',
		'ext.pageTriage.views.list/ext.pageTriage.listView.css'
	),
	'messages' => array(
		'comma-separator',
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
		'pagetriage-byline-new-editor',
		'pipe-separator',
		'pagetriage-editcount',
		'pagetriage-author-not-autoconfirmed',
		'pagetriage-no-patrol-right',
		'pagetriage-author-blocked',
		'pagetriage-author-bot',
		'pagetriage-creation-dateformat',
		'pagetriage-user-creation-dateformat',
		'pagetriage-special-contributions',
		'pagetriage-showing',
		'pagetriage-filter-list-prompt',
		'pagetriage-unreviewed-article-count',
		'pagetriage-reviewed-article-count-past-week',
		'pagetriage-sort-by',
		'pagetriage-newest',
		'pagetriage-oldest',
		'pagetriage-triage',
		'pagetriage-show-only',
		'pagetriage-filter-show-heading',
		'pagetriage-filter-reviewed-edits',
		'pagetriage-filter-unreviewed-edits',
		'pagetriage-filter-nominated-for-deletion',
		'pagetriage-filter-bot-edits',
		'pagetriage-filter-redirects',
		'pagetriage-filter-namespace-heading',
		'pagetriage-filter-article',
		'pagetriage-filter-user-heading',
		'pagetriage-filter-username',
		'pagetriage-filter-tag-heading',
		'pagetriage-filter-second-show-heading',
		'pagetriage-filter-no-categories',
		'pagetriage-filter-orphan',
		'pagetriage-filter-non-autoconfirmed',
		'pagetriage-filter-blocked',
		'pagetriage-filter-set-button',
		'pagetriage-stats-less-than-a-day',
		'blanknamespace',
		'pagetriage-filter-ns-all',
		'pagetriage-more',
		'pagetriage-filter-stat-reviewed',
		'pagetriage-filter-stat-unreviewed',
		'pagetriage-filter-stat-bots',
		'pagetriage-filter-stat-redirects',
		'pagetriage-filter-stat-nominated-for-deletion',
		'pagetriage-filter-stat-all',
		'pagetriage-filter-stat-no-categories',
		'pagetriage-filter-stat-orphan',
		'pagetriage-filter-stat-non-autoconfirmed',
		'pagetriage-filter-stat-blocked',
		'pagetriage-filter-stat-username',
		'pagetriage-filter-all',
		'pagetriage-no-pages',
		'pagetriage-warning-browser',
		'pagetriage-note-reviewed',
		'pagetriage-note-not-reviewed',
		'pagetriage-note-deletion',
		'pagetriage-refresh-list',
		'sp-contributions-talk',
		'contribslink',
		'comma-separator',
		'pagetriage-info-timestamp-date-format',
		'pagetriage-no-reference',
		'pagetriage-stats-filter-page-count'
	)
);

$wgResourceModules['ext.pageTriage.views.toolbar'] = $ptResourceTemplate + array(
	'dependencies' => array(
		'mediawiki.jqueryMsg',
		'ext.pageTriage.models',
		'ext.pageTriage.util',
		'jquery.badge',
		'jquery.ui.button',
		'jquery.ui.draggable',
		'jquery.spinner',
		'jquery.client',
		'ext.pageTriage.externalTagsOptions',
		'ext.pageTriage.externalDeletionTagsOptions'
	),
	'scripts' => array(
		'ext.pageTriage.views.toolbar/ext.pageTriage.toolView.js', // abstract class first
		'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfo.js', // article metadata
		'ext.pageTriage.views.toolbar/ext.pageTriage.minimize.js', // minimize
		'ext.pageTriage.views.toolbar/ext.pageTriage.tags.js', // tagging
		'ext.pageTriage.views.toolbar/ext.pageTriage.mark.js', // mark as reviewed
		'ext.pageTriage.views.toolbar/ext.pageTriage.next.js', // next article
		'ext.pageTriage.views.toolbar/ext.pageTriage.delete.js', // mark for deletion
		'ext.pageTriage.views.toolbar/ext.pageTriage.wikilove.js', // mark for deletion
		'ext.pageTriage.views.toolbar/ext.pageTriage.toolbarView.js', // overall toolbar view last
		'external/jquery.effects.core.js',
		'external/jquery.effects.squish.js',
	),
	'styles' => array(
		'ext.pageTriage.css', // stuff that's shared across all views
		'ext.pageTriage.views.toolbar/ext.pageTriage.toolbarView.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.toolView.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.articleInfo.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.mark.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.tags.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.wikilove.css',
		'ext.pageTriage.views.toolbar/ext.pageTriage.delete.css'
	),
	'messages' => array(
		'pagetriage-creation-dateformat',
		'pagetriage-user-creation-dateformat',
		'pagetriage-mark-as-reviewed',
		'pagetriage-mark-as-unreviewed',
		'pagetriage-info-title',
		'pagetriage-byline',
		'pagetriage-byline-new-editor',
		'pagetriage-articleinfo-byline',
		'pagetriage-articleinfo-byline-new-editor',
		'pipe-separator',
		'pagetriage-edits',
		'pagetriage-editcount',
		'pagetriage-author-bot',
		'pagetriage-no-author',
		'pagetriage-info-problem-header',
		'pagetriage-info-history-header',
		'pagetriage-info-history-show-full',
		'pagetriage-info-help',
		'pagetriage-info-no-problems',
		'pagetriage-info-problem-non-autoconfirmed',
		'pagetriage-info-problem-non-autoconfirmed-desc',
		'pagetriage-info-problem-blocked',
		'pagetriage-info-problem-blocked-desc',
		'pagetriage-info-problem-no-categories',
		'pagetriage-info-problem-no-categories-desc',
		'pagetriage-info-problem-orphan',
		'pagetriage-info-problem-orphan-desc',
		'pagetriage-info-problem-no-references',
		'pagetriage-info-problem-no-references-desc',
		'pagetriage-info-timestamp-date-format',
		'pagetriage-info-timestamp-time-format',
		'pagetriage-info-tooltip',
		'pagetriage-toolbar-collapsed',
		'pagetriage-toolbar-linktext',
		'pagetriage-toolbar-learn-more',
		'pagetriage-mark-as-reviewed-helptext',
		'pagetriage-mark-as-unreviewed-helptext',
		'pagetriage-mark-as-reviewed-error',
		'pagetriage-mark-as-unreviewed-error',
		'pagetriage-markpatrolled',
		'pagetriage-markunpatrolled',
		'pagetriage-note-reviewed',
		'pagetriage-note-not-reviewed',
		'pagetriage-note-deletion',
		'pagetriage-wikilove-page-creator',
		'pagetriage-wikilove-edit-count',
		'pagetriage-wikilove-helptext',
		'pagetriage-wikilove-tooltip',
		'pagetriage-next-tooltip',
		'wikilove',
		'wikilove-button-send',
		'sp-contributions-talk',
		'contribslink',
		'comma-separator',
		'unknown-error',
		'pagetriage-add-a-note-creator',
		'pagetriage-add-a-note-reviewer',
		'pagetriage-characters-left',
		'pagetriage-personal-default-note',
		'pagetriage-special-contributions',
		'pagetriage-tagging-error',
		'pagetriage-del-log-page-missing-error',
		'pagetriage-del-log-page-adding-error',
		'pagetriage-del-talk-page-notify-error',
		'pagetriage-del-discussion-page-adding-error',
		'pagetriage-page-status-reviewed',
		'pagetriage-page-status-reviewed-anonymous',
		'pagetriage-page-status-unreviewed',
		'pagetriage-page-status-autoreviewed',
		'pagetriage-page-status-delete',
		'pagetriage-dot-separator',
		'pagetriage-articleinfo-stat',
		'pagetriage-bytes',
		'pagetriage-edits',
		'pagetriage-categories',
		'pagetriage-add-tag-confirmation',
		'pagetriage-tags-note-edit-summary',
		'pagetriage-del-talk-page-notify-summary',
		'pagetriage-tag-deletion-error',
		'pagetriage-toolbar-close',
		'pagetriage-toolbar-minimize',
		'pagetriage-tag-warning-notice'
	)
);

$wgResourceModules['ext.pageTriage.defaultTagsOptions'] = $ptResourceTemplate + array(
	'scripts' => 'ext.pageTriage.defaultTagsOptions/ext.pageTriage.defaultTagsOptions.js',
	'messages' => $wgPageTriageTagsOptionsMessages,
);

$wgResourceModules['ext.pageTriage.externalTagsOptions'] = $ptResourceTemplate + array(
	'class' => 'PageTriageExternalTagsOptions',
);

$wgResourceModules['ext.pageTriage.defaultDeletionTagsOptions'] = $ptResourceTemplate + array(
	'scripts' => 'ext.pageTriage.defaultDeletionTagsOptions/ext.pageTriage.defaultDeletionTagsOptions.js',
	'messages' => $wgPageTriageDeletionTagsOptionsMessages,
	'dependencies' => array(
		'mediawiki.Title'
	)
);

$wgResourceModules['ext.pageTriage.externalDeletionTagsOptions'] = $ptResourceTemplate + array(
	'class' => 'PageTriageExternalDeletionTagsOptions',
);

$wgResourceModules['ext.pageTriage.toolbarStartup'] = $ptResourceTemplate + array(
	'scripts' => 'ext.pageTriage.toolbarStartup/ext.pageTriage.toolbarStartup.js',
);

$wgResourceModules['ext.pageTriage.article'] = $ptResourceTemplate + array(
	'styles' => 'ext.pageTriage.article/ext.pageTriage.article.css',
	'scripts' => 'ext.pageTriage.article/ext.pageTriage.article.js',
	'messages' => array (
			'pagetriage-reviewed',
			'pagetriage-mark-as-reviewed-error',
	),
);

/** Rate limit setting for PageTriage **/
$wgRateLimits += array(
	'pagetriage-mark-action' => array(
			'anon' => array( 1, 3 ),
			'user' => array( 1, 3 )
	),

	'pagetriage-tagging-action' => array(
			'anon' => array( 1, 10 ),
			'user' => array( 1, 10 )
	)
);
