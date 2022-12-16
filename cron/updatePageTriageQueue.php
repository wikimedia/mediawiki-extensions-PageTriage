<?php

/**
 * There is a cron job that runs this maintenance script every 48 hours on enwiki,
 * testwiki, and test2wiki. The Puppet file controlling the cron job is located at
 * https://gerrit.wikimedia.org/r/plugins/gitiles/operations/puppet/+/refs/heads/production/modules/profile/manifests/mediawiki/maintenance/pagetriage.pp
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../includes/Maintenance/RemoveOldRows.php";

$maintClass = \MediaWiki\Extension\PageTriage\Maintenance\RemoveOldRows::class;
require_once RUN_MAINTENANCE_IF_MAIN;
