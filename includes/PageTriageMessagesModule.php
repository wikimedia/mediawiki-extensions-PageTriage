<?php

namespace MediaWiki\Extension\PageTriage;

use FormatJson;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderModule;

/**
 * Resource loader module providing customized messages from the server to PageTriage.
 * The current use case is to provide messages in content language (rather than interface
 * language).
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2016 PageTriage Team and others; see AUTHORS.txt
 * @license MIT
 */

class PageTriageMessagesModule extends ResourceLoaderModule {

	/* Protected Members */

	/**
	 * @var array $contentLanguageMessageKeys
	 */
	protected $contentLanguageMessageKeys;

	public function __construct(
		$options = []
	) {
		$contentLanguageMessageKeys = array_values(
			array_unique( (array)$options['contentLanguageMessages'] )
		);
		sort( $contentLanguageMessageKeys );
		$this->contentLanguageMessageKeys = $contentLanguageMessageKeys;
	}

	/* Methods */

	public function getScript( ResourceLoaderContext $context ) {
		$contentLanguageMessages = [];

		foreach ( $this->contentLanguageMessageKeys as $msgKey ) {
			$contentLanguageMessages[ $msgKey ] = $context->msg( $msgKey )->inContentLanguage()->plain();
		}

		return 'mw.pageTriage.contentLanguageMessages.set(' . FormatJson::encode(
			$contentLanguageMessages,
			ResourceLoader::inDebugMode()
		) . ');';
	}

	public function enableModuleContentVersion() {
		return true;
	}

	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [
			'ext.pageTriage.util',
		];
	}
}
