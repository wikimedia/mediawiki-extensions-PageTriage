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
);
