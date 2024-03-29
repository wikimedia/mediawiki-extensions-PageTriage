<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../includes/Maintenance/UpdateUserMetadata.php";

$maintClass = \MediaWiki\Extension\PageTriage\Maintenance\UpdateUserMetadata::class;
require_once RUN_MAINTENANCE_IF_MAIN;
