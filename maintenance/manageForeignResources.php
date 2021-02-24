<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use ForeignResourceManager;
use Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class ManageForeignResources extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'action', 'One of "update", "verify", or "make-sri"', true );
	}

	public function execute() {
		$frm = new ForeignResourceManager(
			__DIR__ . '/../modules/external/foreign-resources.yaml',
			__DIR__ . '/../modules/external',
			function ( $text ) {
				$this->output( $text );
			},
			function ( $text ) {
				$this->error( $text );
			}
		);
		$action = $this->getArg( 0 );
		return $frm->run( $action, 'all' );
	}
}

$maintClass = ManageForeignResources::class;

require_once RUN_MAINTENANCE_IF_MAIN;
