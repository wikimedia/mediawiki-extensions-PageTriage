<?php

namespace MediaWiki\Extension\PageTriage;

use ResourceLoaderContext;
use ResourceLoaderWikiModule;

/**
 * Custom ResourceLoader module that loads a custom PageTriageTagsOptions.js per-wiki.
 */
class PageTriageExternalTagsOptions extends ResourceLoaderWikiModule {

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return [
			'MediaWiki:PageTriageExternalTagsOptions.js' => [ 'type' => 'script' ],
			'MediaWiki:PageTriageExternalDeletionTagsOptions.js' => [ 'type' => 'script' ],
		];
	}

	/**
	 * @param ResourceLoaderContext|null $context
	 * @return array
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [
			'ext.pageTriage.defaultTagsOptions',
			'ext.pageTriage.defaultDeletionTagsOptions',
		];
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
