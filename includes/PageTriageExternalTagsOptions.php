<?php
/**
 * Custom ResourceLoader module that loads a custom PageTriageTagsOptions.js per-wiki.
 */
class PageTriageExternalTagsOptions extends ResourceLoaderWikiModule {

	/**
	 * @param $context ResourceLoaderContext
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return [
			'MediaWiki:PageTriageExternalTagsOptions.js' => [ 'type' => 'script' ],
		];
	}

	/**
	 * @param $context ResourceLoaderContext
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
