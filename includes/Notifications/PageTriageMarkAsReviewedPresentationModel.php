<?php

class PageTriageMarkAsReviewedPresentationModel extends EchoEventPresentationModel {
	/**
	 * {@inheritdoc}
	 */
	public function canRender() {
		return $this->event->getTitle() instanceof Title;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getIconType() {
		return 'reviewed';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPrimaryLink() {
		return array(
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-page' )->text(),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSecondaryLinks() {
		return array( $this->getAgentLink() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// parent adds agent & gender, this adds title as 3rd param
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );

		return $msg;
	}
}
