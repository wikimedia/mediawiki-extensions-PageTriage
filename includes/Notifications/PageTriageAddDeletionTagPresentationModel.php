<?php

class PageTriageAddDeletionTagPresentationModel extends PageTriagePresentationModel {
	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'trash';
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$links = [ $this->getAgentLink() ];
		$discussionLink = $this->getDiscussionLink();
		if ( $discussionLink ) {
			$links[] = $discussionLink;
		}
		return $links;
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// parent adds agent & gender, this adds title as 3rd param, tag list as
		// 4th & amount of tags as 5th (for PLURAL)
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		$msg->params( $this->getTagsForOutput() );

		return $msg;
	}

	private function getDiscussionLink() {
		if ( !in_array( 'afd', $this->getTags() ) ) {
			return false;
		}

		$pageName = $this->event->getTitle()->getText();
		$discussionPage = Title::newFromText( "Wikipedia:Articles for deletion/$pageName" );
		$user = $this->getViewingUserForGender();
		$labelMsg = $this->msg( 'pagetriage-discuss-link' )->params( $user );
		$descMsg = $this->msg( 'pagetriage-discuss-link-title' )->params( $user );
		return [
			'label' => $labelMsg->text(),
			'url' => $discussionPage->getFullURL(),
			'icon' => 'speechBubbles',
			'description' => $descMsg->text(),
			'prioritized' => true,
		];
	}
}
