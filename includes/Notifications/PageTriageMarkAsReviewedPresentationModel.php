<?php

class PageTriageMarkAsReviewedPresentationModel extends PageTriagePresentationModel {

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'reviewed';
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [ $this->getAgentLink() ];
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// parent adds agent & gender, this adds title as 3rd param
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );

		return $msg;
	}
}
