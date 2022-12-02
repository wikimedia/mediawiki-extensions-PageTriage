<?php

use MediaWiki\Extension\PageTriage\QueueLookup;
use MediaWiki\Extension\PageTriage\QueueManager;
use MediaWiki\MediaWikiServices;

return [
	'PageTriageQueueManager' => static function ( MediaWikiServices $services ): QueueManager {
		return new QueueManager( $services->getDBLoadBalancer()->getConnection( DB_PRIMARY ) );
	},
	'PageTriageQueueLookup' => static function ( MediaWikiServices $services ): QueueLookup {
		return new QueueLookup( $services->getDBLoadBalancer()->getConnection( DB_REPLICA ) );
	},
];
