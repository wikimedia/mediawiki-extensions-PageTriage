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
	'pagetriage-author-not-autoconfirmed' => 'Non-autoconfirmed',
	'pagetriage-author-blocked' => 'Blocked',
	'pagetriage-author-bot' => 'Bot',
	'pagetriage-creation-dateformat' => 'HH:mm, d MMMM yyyy',
	'pagetriage-user-creation-dateformat' => 'yyyy-MM-dd',
	'pagetriage-special-contributions' => 'Special:Contributions',
	'pagetriage-showing' => 'Showing',
	'pagetriage-filter-list-prompt' => 'Filter List',
	'pagetriage-article-count' => 'There are currently $1 $2 articles',
	'pagetriage-viewing' => 'Viewing',
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
	'pagetriage-article-count' => 'A description of the number of articles in the list. $1 is the count, $2 is the type (for example, "untriaged")',
	'pagetriage-viewing' => 'Label for the sort-order buttons (oldest/newest)',
	
);
