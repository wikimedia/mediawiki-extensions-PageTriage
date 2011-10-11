<?php
/**
 * Aliases for Special:PageTriage
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = array();

/** English
 * @author  Ryan Kaldari
 */
$specialPageAliases['en'] = array(
	'PageTriage' => array( 'PageTriage' ),
	'PageTriageList' => array( 'PageTriageList' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;
