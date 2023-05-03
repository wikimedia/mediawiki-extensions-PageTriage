<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\WikiModule;

/**
 * Custom ResourceLoader module that loads a custom PageTriageTagsOptions.js per-wiki.
 */
class PageTriageExternalTagsOptions extends WikiModule {

	/**
	 * @param Context $context
	 * @return array
	 */
	protected function getPages( Context $context ) {
		return [
			'MediaWiki:PageTriageExternalTagsOptions.js' => [ 'type' => 'script' ],
			'MediaWiki:PageTriageExternalDeletionTagsOptions.js' => [ 'type' => 'script' ],
		];
	}

	/**
	 * @param Context|null $context
	 * @return array
	 */
	public function getDependencies( Context $context = null ) {
		return [
			'ext.pageTriage.defaultTagsOptions'
		];
	}

	/** @inheritDoc */
	public function requiresES6() {
		return true;
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		global $wgPageTriageTagsOptionsMessages, $wgPageTriageDeletionTagsOptionsMessages;
		return array_merge(
			$wgPageTriageTagsOptionsMessages,
			$wgPageTriageDeletionTagsOptionsMessages
		);
	}
}
