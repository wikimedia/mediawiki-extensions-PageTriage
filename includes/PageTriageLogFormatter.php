<?php
/**
 * Formats the logs for display on Special:Log
 */
class PageTriageLogFormatter extends LogFormatter {

	protected function getActionMessage() {
		global $wgContLang;
		$parameters = $this->entry->getParameters();

		$params = array(
			Message::rawParam( $this->getPerformerElement() ),
			$this->entry->getPerformer()->getName(),
			Message::rawParam( $this->makePageLink( $this->entry->getTarget() ) )
		);
		// backward compatibility
		if ( isset( $parameters['4::tags'] ) ) {
			$params['4::tags'] = $wgContLang->listToText( $parameters['4::tags'] );
			$params['tagnumber'] = count( $parameters['4::tags'] );
		} else {
			$params['tags'] = $wgContLang->listToText( $parameters['tags'] );
			$params['tagnumber'] = count( $parameters['tags'] );
		}

		return wfMessage( 'logentry-' . $this->entry->getType() . '-' . $this->entry->getSubtype(), $params );
	}

}

