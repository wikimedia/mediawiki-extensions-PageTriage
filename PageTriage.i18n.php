<?php
/**
 * Internationalisation for Page Triage extension
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English
 * @author Ryan Kaldari
 */
$messages['en'] = array(
	'pagetriage' => 'Page Triage',
	'pagetriage-desc' => 'Facilitates reviewing and approving new pages',
	'pagetriage-api-invalidid' => 'The ID you provided ($1) is not valid.',
	'pagetriage-markpatrolled' => 'Unpatrolled',
	'pagetriage-hist' => 'hist',
	'pagetriage-bytes' => '$1 {{PLURAL:$1|byte|bytes}}',
	'pagetriage-edits' => '$1 {{PLURAL:$1|edit|edits}}',
	'pagetriage-categories' => '$1 {{PLURAL:$1|category|categories}}',
	'pagetriage-no-categories' => 'No categories',
	'pagetriage-orphan' => 'Orphan',
	'pagetriage-no-author' => 'No author information present',
	'pagetriage-byline' => 'By',
	'pagetriage-editcount' => '$1 {{PLURAL:$1|edit|edits}} since $2',
	'pagetriage-author-not-autoconfirmed' => 'New editor',
	'pagetriage-author-blocked' => 'Blocked',
	'pagetriage-author-bot' => 'Bot',
	'pagetriage-creation-dateformat' => 'HH:mm, d MMMM yyyy',
	'pagetriage-user-creation-dateformat' => 'yyyy-MM-dd',
	'pagetriage-special-contributions' => 'Special:Contributions',
	'pagetriage-showing' => 'Showing',
	'pagetriage-filter-list-prompt' => 'Filter List',
	'pagetriage-article-count' => 'There are currently $1 untriaged articles',
	'pagetriage-viewing' => 'Viewing',
	'pagetriage-triage' => 'Triage',
	'pagetriage-filter-show-heading' => 'Show Only:',
	'pagetriage-filter-triaged-edits' => 'Triaged Articles',
	'pagetriage-filter-nominated-for-deletion' => 'Nominated for Deletion',
	'pagetriage-filter-bot-edits' => 'Articles by Bots',
	'pagetriage-filter-redirects' => 'Redirects',
	'pagetriage-filter-namespace-heading' => 'In Namespace:',
	'pagetriage-filter-user-heading' => 'By User:',
	'pagetriage-filter-tag-heading' => 'With Tag:',
	'pagetriage-filter-second-show-heading' => 'That:',
	'pagetriage-filter-no-categories' => 'Have no categories',
	'pagetriage-filter-orphan' => 'Are orphaned',
	'pagetriage-filter-non-autoconfirmed' => 'Are by new editors',
	'pagetriage-filter-blocked' => 'Are by blocked users',
	'pagetriage-filter-set-button' => 'Set Filters',
	'pagetriage-stats-untriaged-age' => 'Article Ages: Average: $1, Oldest: $2',
	'pagetriage-stats-less-than-a-day' => 'less than one day',
	'pagetriage-stats-top-triagers' => 'Top {{PLURAL:$1|triager|$1 triagers}}:',
	'pagetriage-filter-ns-article' => 'Article',
	'pagetriage-filter-ns-all' => 'All',
	'pagetriage-more' => 'More',
	'pagetriage-filter-stat-all' => 'All',
	'pagetriage-filter-stat-namespace' => 'Namespace: $1',
	'pagetriage-filter-stat-triaged' => 'Triaged',
	'pagetriage-filter-stat-bots' => 'Bots',
	'pagetriage-filter-stat-redirects' => 'Redirects',
	'pagetriage-filter-stat-no-categories' => 'No categories',
	'pagetriage-filter-stat-orphan' => 'Orphans',
	'pagetriage-filter-stat-non-autoconfirmed' => 'New editors',
	'pagetriage-filter-stat-blocked' => 'Blocked users',
	
);

/**
 * Message documentation (Message documentation)
 */
$messages['qqq'] = array(
	'pagetriage' => 'The name of this application (Page Triage)',
	'pagetriage-desc' => '{{desc}}',
	'pagetriage-api-invalidid' => 'Invalid title error message for pagetriage API',
	'pagetriage-markpatrolled' => 'Button text for the mark-as-patrolled button',
	'pagetriage-bytes' => 'The number of bytes in the article',
	'pagetriage-edits' => 'The number of times the article has been edited',
	'pagetriage-categories' => 'The number of categories in the article',
	'pagetriage-no-categories' => 'Label indicating an article with no categories',
	'pagetriage-orphan' => 'Label indicating an article has no external links (orphan)',
	'pagetriage-no-author' => 'Error message for missing article author information',
	'pagetriage-byline' => 'Text indicating the article author (username comes after). No $1 because the username is a hyperlink.',
	'pagetriage-editcount' => 'Display of article author\'s editing experience. $1 is total edit count, $2 is author\'s join date',
	'pagetriage-author-not-autoconfirmed' => 'String indicating that the author was not yet autoconfirmed when the article was last edited',
	'pagetriage-author-blocked' => 'String indicating that the author was blocked when the article was last edited',
	'pagetriage-author-bot' => 'String indicating that the author is a bot',
	'pagetriage-creation-dateformat' => 'Format specifier for the article creation date. Month and weekday names will be localized. For formats, see: http://code.google.com/p/datejs/wiki/FormatSpecifiers',
	'pagetriage-user-creation-dateformat' => 'Format specifier for the author\'s account creation date. Month and weekday names will be localized. For formats, see: http://code.google.com/p/datejs/wiki/FormatSpecifiers',
	'pagetriage-special-contributions' => 'The name of Special:Contributions on this wiki',
	'pagetriage-showing' => 'The label for which filters are being shown',
	'pagetriage-filter-list-prompt' => 'Prompt to choose filters for the list view',
	'pagetriage-article-count' => 'A description of the number of untriaged articles. $1 is the count.',
	'pagetriage-viewing' => 'Label for the sort-order buttons (oldest/newest)',
	'pagetriage-filter-show-heading' => 'Prompt for the first set of checkboxes in the filter menu',
	'pagetriage-filter-triaged-edits' => 'Checkbox text for triaged articles',
	'pagetriage-filter-nominated-for-deletion' => 'Checkbox text for articles nominated for deletion',
	'pagetriage-filter-bot-edits' => 'Checkbox text for articles by bots',
	'pagetriage-filter-redirects' => 'Checkbox text for redirect articles',
	'pagetriage-filter-namespace-heading' => 'Prompt for the namespace to display',
	'pagetriage-filter-user-heading' => 'Prompt for the user to find articles by',
	'pagetriage-filter-tag-heading' => 'Prompt to find articles with a given tag',
	'pagetriage-filter-second-show-heading' => 'Prompt for the second set of checkboxes in the filter menu',
	'pagetriage-filter-no-categories' => 'Checkbox text for articles with no categories',
	'pagetriage-filter-orphan' => 'Checkbox text for orphan articles',
	'pagetriage-filter-non-autoconfirmed' => 'Checkbox text for articles by non-Autoconfirmed users',
	'pagetriage-filter-blocked' => 'Checkbox text for articles by blocked users',
	'pagetriage-filter-set-button' => 'Button text for the set filter button',
	'pagetriage-stats-untriaged-age' => 'Navigation text displaying triage stats, $1 and $2 are the ages of average and oldest articles respectively',
	'pagetriage-stats-less-than-a-day' => 'show this message if the article age is less than one day, part of variable $1 and $2 of {{msg-pagetriage|pagetriage-stats-untriaged-age}} ',
	'pagetriage-stats-top-triagers' => 'Text that shows top triagers, $1 is the total number, $2 shows the detail',
	'pagetriage-filter-ns-article' => 'The name of the main article namespace, for the namespace filter select list',
	'pagetriage-filter-ns-all' => 'For the namespace filter select list, text indicating that all namespaces will be selected',
	'pagetriage-more' => 'Text for a link that loads more articles into list',
	'pagetriage-filter-stat-all' => 'Status display component for all pages (no filter)',
	'pagetriage-filter-stat-namespace' => 'Status display component for the namespace filter.  $1 is the name of the namespace.',
	'pagetriage-filter-stat-triaged' => 'Status display component for triaged pages',
	'pagetriage-filter-stat-bots' => 'Status display component for bot-created pages',
	'pagetriage-filter-stat-redirects' => 'Status display component for redirects',
	'pagetriage-filter-stat-no-categories' => 'Status display component for articles with no categories',
	'pagetriage-filter-stat-orphan' => 'Status display component for orphan articles',
	'pagetriage-filter-stat-non-autoconfirmed' => 'Status display component for articles by non-autoconfirmed editors',
	'pagetriage-filter-stat-blocked' => 'Status display component for articles by blocked users',
	

	
);
