<?php

namespace MediaWiki\Extension\PageTriage\Notifications;

use ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;

class PageTriageAddMaintenanceTagPresentationModel extends PageTriagePresentationModel {
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
		$links = [ $this->getAgentLink() ];
		$thankLink = $this->getThankLink();
		if ( $thankLink ) {
			$links[] = $thankLink;
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

	/**
	 * @return array|false
	 */
	private function getThankLink() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Thanks' ) ) {
			return false;
		}

		$revId = $this->event->getExtraParam( 'revId' );
		if ( !$revId ) {
			return false;
		}

		$thankingUser = $this->getViewingUserForGender();
		[ , $thankedUser ] = $this->getAgentForOutput();
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
