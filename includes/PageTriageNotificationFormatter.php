<?php

class PageTriageNotificationFormatter extends EchoEditFormatter {

	/**
	 * @param $event EchoEvent
	 * @param $param
	 * @param $message Message
	 * @param $user User
	 */
	protected function processParam( $event, $param, $message, $user ) {
		if ( $param === 'tag' ) {
			global $wgContLang;

			$eventData = $event->getExtra();
			if ( !is_array( $eventData ) ) {
				$message->params( '' );
				return;
			}

			$message->params( $wgContLang->listToText( $eventData ) );
			$message->params( count( $eventData ) );
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}

}
