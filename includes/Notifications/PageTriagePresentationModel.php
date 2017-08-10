<?php

abstract class PageTriagePresentationModel extends EchoEventPresentationModel {
	/**
	 * @inheritDoc
	 */
	public function canRender() {
		return $this->event->getTitle() instanceof Title;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-page' )->text(),
		];
	}

	protected function getTags() {
		// BC: the extra params array used to be the tags directly, now the tags are under the key 'tags'
		return $this->event->getExtraParam( 'tags', $this->event->getExtra() );
	}

	/**
	 * Returns an array of [tag list, amount of tags], to be used as msg params.
	 *
	 * @return array [(string) tag list, (int) amount of tags]
	 */
	protected function getTagsForOutput() {
		$tags = $this->getTags();

		if ( !is_array( $tags ) ) {
			return [ '', 0 ];
		}

		return [ $this->language->commaList( $tags ), count( $tags ) ];
	}

	function getBodyMessage() {
		$note = $this->event->getExtraParam( 'note' );
		return $note ? $this->msg( 'notification-body-page-triage-note' )->params( $note ) : false;
	}
}
