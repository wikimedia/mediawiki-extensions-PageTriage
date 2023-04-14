<?php

use MediaWiki\Extension\PageTriage\QueueLookup;
use MediaWiki\Extension\PageTriage\QueueManager;
use MediaWiki\MediaWikiServices;

return [
	'PageTriageQueueManager' => static function ( MediaWikiServices $services ): QueueManager {
		return new QueueManager( $services->getDBLoadBalancerFactory()->getPrimaryDatabase() );
	},
	'PageTriageQueueLookup' => static function ( MediaWikiServices $services ): QueueLookup {
		return new QueueLookup( $services->getDBLoadBalancerFactory()->getReplicaDatabase() );
	},
];
