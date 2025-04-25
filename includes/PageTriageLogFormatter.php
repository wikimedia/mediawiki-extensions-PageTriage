<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\Logging\LogFormatter;

/**
 * Formats the logs for display on Special:Log
 */
class PageTriageLogFormatter extends LogFormatter {

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$lang = $this->context->getLanguage();
		$params = parent::getMessageParameters();
		$parameters = $this->entry->getParameters();

		// backward compatibility
		if ( isset( $parameters['4::tags'] ) ) {
			$params[3] = $lang->listToText( $parameters['4::tags'] );
			$params[4] = count( $parameters['4::tags'] );
		} else {
			$params[3] = $lang->listToText( $parameters['tags'] );
			$params[4] = count( $parameters['tags'] );
		}

		return $params;
	}
}
