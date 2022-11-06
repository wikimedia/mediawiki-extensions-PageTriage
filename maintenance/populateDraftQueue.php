<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../includes/Maintenance/PopulateDraftQueue.php";

$maintClass = \MediaWiki\Extension\PageTriage\Maintenance\PopulateDraftQueue::class;
require_once RUN_MAINTENANCE_IF_MAIN;
