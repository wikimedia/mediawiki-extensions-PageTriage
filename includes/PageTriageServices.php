<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\MediaWikiServices;

/**
 * A simple wrapper for MediaWikiServices, to support type safety when accessing
 * services defined by this extension.
 */
class PageTriageServices {

	public function __construct( private readonly MediaWikiServices $coreServices ) {
	}

	/**
	 * Static version of the constructor, for nicer syntax.
	 * @param MediaWikiServices $coreServices
	 * @return static
	 */
	public static function wrap( MediaWikiServices $coreServices ): PageTriageServices {
		return new static( $coreServices );
	}

	/**
	 * @return QueueManager
	 */
	public function getQueueManager(): QueueManager {
		return $this->coreServices->get( 'PageTriageQueueManager' );
	}

	/**
	 * @return QueueLookup
	 */
	public function getQueueLookup(): QueueLookup {
		return $this->coreServices->get( 'PageTriageQueueLookup' );
	}
}
