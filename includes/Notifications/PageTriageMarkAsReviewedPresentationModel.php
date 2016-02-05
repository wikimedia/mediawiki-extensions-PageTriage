<?php

class PageTriageMarkAsReviewedPresentationModel extends PageTriagePresentationModel {

	/**
	 * {@inheritdoc}
	 */
	public function getIconType() {
		return 'reviewed';
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
