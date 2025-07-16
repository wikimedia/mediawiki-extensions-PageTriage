<?php

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\PageTriage\Maintenance\PurgeOldPages;
use MediaWiki\Maintenance\Maintenance;

/**
 * @deprecated since 1.45. Use maintenance/PurgeOldPages.php instead.
 */
class UpdatePageTriageQueue extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Remove reviewed pages from pagetriage queue if they"
			. " are older then 30 days" );
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		$maintenanceScript = $this->createChild( PurgeOldPages::class );
		$maintenanceScript->setBatchSize( $this->getBatchSize() );
		$maintenanceScript->execute();
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdatePageTriageQueue::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
