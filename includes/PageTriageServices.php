<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\MediaWikiServices;

/**
 * A simple wrapper for MediaWikiServices, to support type safety when accessing
 * services defined by this extension.
 */
class PageTriageServices {

	/** @var MediaWikiServices */
	private MediaWikiServices $coreServices;

	/**
	 * @param MediaWikiServices $coreServices
	 */
	public function __construct( MediaWikiServices $coreServices ) {
		$this->coreServices = $coreServices;
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
