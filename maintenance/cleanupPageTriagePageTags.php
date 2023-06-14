<?php
/**
 * Remove page from pagetriage_page_tags if they are not in pagetriage_page
 *
 * @ingroup Maintenance
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../includes/Maintenance/CleanupPageTriagePageTags.php";

$maintClass = \MediaWiki\Extension\PageTriage\Maintenance\CleanupPageTriagePageTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
