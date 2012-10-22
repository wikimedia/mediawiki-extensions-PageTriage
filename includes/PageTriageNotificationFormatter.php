<?php

class PageTriageNotificationFormatter extends EchoBasicFormatter {

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
		} elseif ( $param === 'title-link' ) {
			if ( !$event->getTitle() ) {
				$message->params( '' );
			} else {
				$message->params( $event->getTitle()->getCanonicalUrl() );
			}
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}

}
