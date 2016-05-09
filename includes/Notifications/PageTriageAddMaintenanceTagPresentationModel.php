<?php

class PageTriageAddMaintenanceTagPresentationModel extends PageTriagePresentationModel {
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
		$links = [ $this->getAgentLink() ];
		$thankLink = $this->getThankLink();
		if ( $thankLink ) {
			$links[] = $thankLink;
		}
		return $links;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		// parent adds agent & gender, this adds title as 3rd param, tag list as
		// 4th & amount of tags as 5th (for PLURAL)
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		$msg->params( $this->getTagsForOutput() );

		return $msg;
	}

	private function getThankLink() {
		if ( !class_exists( 'ThanksHooks' ) ) {
			return false;
		}

		$revId = $this->event->getExtraParam( 'revId' );
		if ( !$revId ) {
			return false;
		}

		$thankingUser = $this->getViewingUserForGender();
		list( , $thankedUser ) = $this->getAgentForOutput();
		$labelMsg = $this->msg( 'pagetriage-thank-link' );
		$labelMsg->params( $thankingUser, $thankedUser );
		$descMsg = $this->msg( 'pagetriage-thank-link-title' );
		$descMsg->params( $thankingUser, $thankedUser );
		return [
			'label' => $labelMsg->text(),
			'url' => SpecialPage::getTitleFor( 'Thanks', $revId )->getFullURL(),
			'icon' => 'thanks',
			'description' => $descMsg->text(),
			'prioritized' => true,
		];
	}
}
