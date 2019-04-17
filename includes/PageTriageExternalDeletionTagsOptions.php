<?php

namespace MediaWiki\Extension\PageTriage;

use ResourceLoaderContext;
use ResourceLoaderWikiModule;

/**
 * Custom ResourceLoader module that loads a custom PageTriageDeletionTagsOptions.js per-wiki.
 */
class PageTriageExternalDeletionTagsOptions extends ResourceLoaderWikiModule {

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return [
			'MediaWiki:PageTriageExternalDeletionTagsOptions.js' => [ 'type' => 'script' ],
		];
	}

	/**
	 * @param ResourceLoaderContext|null $context
	 * @return array
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [
			'ext.pageTriage.defaultDeletionTagsOptions',
		];
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		global $wgPageTriageDeletionTagsOptionsMessages;
		return $wgPageTriageDeletionTagsOptionsMessages;
	}
}
