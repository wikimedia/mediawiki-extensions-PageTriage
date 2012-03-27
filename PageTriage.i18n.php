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
	'pagetriage-byline' => 'By $1',
	'pagetriage-editcount' => '$1 edits since $2',
	'pagetriage-author-not-autoconfirmed' => 'Non-autoconfirmed',
	'pagetriage-author-blocked' => 'Blocked',
	'pagetriage-author-bot' => 'Bot',
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
	'pagetriage-byline' => 'Text indicating the article author. $1 is the author username',
	'pagetriage-editcount' => 'Display of article author\'s editing experience. $1 is total edit count, $2 is author\'s join date',
	'pagetriage-author-not-autoconfirmed' => 'String indicating that the author was not yet autoconfirmed when the article was last edited',
	'pagetriage-author-blocked' => 'String indicating that the author was blocked when the article was last edited',
	'pagetriage-author-bot' => 'String indicating that the author is a bot',
	
	
);
