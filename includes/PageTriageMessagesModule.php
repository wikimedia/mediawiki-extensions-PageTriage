<?php

namespace MediaWiki\Extension\PageTriage;

use FormatJson;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderFileModule;

/**
 * File module with extra generated script for PageTriage.
 *
 * This is used for custom interface messages that need to be exported in
 * the site's content language (rather than the user language).
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2016 PageTriage Team and others; see AUTHORS.txt
 * @license MIT
 */

class PageTriageMessagesModule extends ResourceLoaderFileModule {

	/* Methods */

	public function getScript( ResourceLoaderContext $context ) {
		$generatedScript = $this->getGeneratedScript( $context );
		$fileScript = parent::getScript( $context );
		return $generatedScript . $fileScript;
	}

	private function getGeneratedScript( ResourceLoaderContext $context ) {
		$config = $this->getConfig();
		$contentLanguageMessageKeys = array_merge(
			[
				'pagetriage-mark-mark-talk-page-notify-topic-title',
				'pagetriage-mark-unmark-talk-page-notify-topic-title',
				'pagetriage-note-sent-talk-page-notify-topic-title',
				'pagetriage-tags-talk-page-notify-topic-title'
			],
			$config->get( 'PageTriageDeletionTagsOptionsContentLanguageMessages' )
		);

		$contentLanguageMessages = [];
		foreach ( $contentLanguageMessageKeys as $msgKey ) {
			$contentLanguageMessages[ $msgKey ] = $context->msg( $msgKey )->inContentLanguage()->plain();
		}

		return 'mw.pageTriage.contentLanguageMessages.set(' . FormatJson::encode(
			$contentLanguageMessages,
			ResourceLoader::inDebugMode()
		) . ');';
	}

	/**
	 * Helper for computing the module version hash.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'generatedScript' => $this->getGeneratedScript( $context ),
		];
		return $summary;
	}

	public function getDependencies( ResourceLoaderContext $context = null ) {
		$deps = parent::getDependencies( $context );
		$deps[] = 'ext.pageTriage.util';
		return $deps;
	}
}
