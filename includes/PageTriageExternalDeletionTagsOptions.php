<?php
/**
 * Custom ResourceLoader module that loads a custom PageTriageDeletionTagsOptions.js per-wiki.
 */
class PageTriageExternalDeletionTagsOptions extends ResourceLoaderWikiModule {

	/**
	 * @param $context ResourceLoaderContext
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return array(
			'MediaWiki:PageTriageExternalDeletionTagsOptions.js' => array( 'type' => 'script' ),
		);
	}

	/**
	 * @return array
	 */
	public function getDependencies() {
		return array( 'ext.pageTriage.defaultDeletionTagsOptions' );
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		global $wgPageTriageDeletionTagsOptionsMessages;
		return $wgPageTriageDeletionTagsOptionsMessages;
	}
}
