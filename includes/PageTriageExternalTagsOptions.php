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
		];
	}

	/**
	 * @param ResourceLoaderContext|null $context
	 * @return array
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [ 'ext.pageTriage.defaultTagsOptions' ];
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		global $wgPageTriageTagsOptionsMessages;
		return $wgPageTriageTagsOptionsMessages;
	}
}
