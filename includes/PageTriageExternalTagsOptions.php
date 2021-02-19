<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\ResourceLoader as RL;

/**
 * Custom ResourceLoader module that loads a custom PageTriageTagsOptions.js per-wiki.
 */
class PageTriageExternalTagsOptions extends RL\WikiModule {

	/**
	 * @param RL\Context $context
	 * @return array
	 */
	protected function getPages( RL\Context $context ) {
		return [
			'MediaWiki:PageTriageExternalTagsOptions.js' => [ 'type' => 'script' ],
			'MediaWiki:PageTriageExternalDeletionTagsOptions.js' => [ 'type' => 'script' ],
		];
	}

	/**
	 * @param RL\Context|null $context
	 * @return array
	 */
	public function getDependencies( RL\Context $context = null ) {
		return [
			'ext.pageTriage.defaultTagsOptions'
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
