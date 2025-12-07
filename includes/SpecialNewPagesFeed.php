<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\Config\ConfigException;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * This file defines the SpecialNewPagesFeed class which handles the functionality for the
 * New Pages Feed (Special:NewPagesFeed).
 *
 * @ingroup Extensions
 * @author Ryan Kaldari
 */
class SpecialNewPagesFeed extends SpecialPage {
	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		parent::__construct( 'NewPagesFeed' );
	}

	/**
	 * Define what happens when the special page is loaded by the user.
	 * @param string $sub The subpage, if any
	 * @throws ConfigException
	 */
	public function execute( $sub ) {
		$this->addHelpLink( 'Help:New_pages_feed' );

		$config = $this->getConfig();

		$request = $this->getRequest();
		$showOresFilters = PageTriageUtil::oresIsAvailable() &&
			( $config->get( 'PageTriageEnableOresFilters' ) || $request->getBool( 'ores' ) );
		$showCopyvio = $showOresFilters &&
			( $config->get( 'PageTriageEnableCopyvio' ) || $request->getBool( 'copyvio' ) );
		$this->setHeaders();
		$out = $this->getOutput();

		// Output the title of the page
		$out->setPageTitleMsg( $this->msg( 'newpagesfeed' ) );

		// Load common interface css
		$out->addModuleStyles( [ 'mediawiki.interface.helpers.styles' ] );

		// Set the config flags in JavaScript
		$globalVars = [
			'pageTriageNamespaces' => PageTriageUtil::getNamespaces(),
			'wgPageTriageEnableExtendedFeatures' => $config->get( 'PageTriageEnableExtendedFeatures' ),
			'wgShowOresFilters' => $showOresFilters,
			'wgShowCopyvio' => $showCopyvio,
		];
		$out->addJsConfigVars( $globalVars );
		// Load the JS
		$out->addModules( [
			'ext.pageTriage.external',
			'ext.pageTriage.util',
			'ext.pageTriage.newPagesFeed'
		] );

		$header = '';
		$header .= '<div id="mwe-pt-list-warnings" style="display: none;">';
		$parsedWelcomeMessage = $this->msg(
			'pagetriage-welcome',
			$config->get( 'PageTriageLearnMoreUrl' ),
			$config->get( 'PageTriageFeedbackUrl' )
		)->parse();
		$header .= Html::rawElement( 'div', [ 'class' => 'plainlinks' ], $parsedWelcomeMessage );
		$header .= '</div>';
		$out->addHTML( $header );
		$out->addInlineStyle(
			'.client-nojs #mwe-pt-list-view, .client-js #mwe-pt-list-view-no-js { display: none; }'
		);
		// Output the HTML for the triage interface
		$out->addHTML( $this->getListViewHtml() );
		$this->maybeAddShowIpModule( $out );
	}

	/**
	 * Get the list control nav HTML.
	 *
	 * @return string
	 */
	private function getListViewHtml() {
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		// HTML for this is located in includes/templates/ListView.mustache
		return $templateParser->processTemplate(
			'ListView',
			[
				'pagetriage-please-wait' => $this->msg( 'pagetriage-please-wait' ),
				'pagetriage-js-required' => $this->msg( 'pagetriage-js-required' ),
			]
		);
	}

	/**
	 * @param OutputPage $out
	 * @return void
	 */
	private function maybeAddShowIpModule( OutputPage $out ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			return;
		}
		if ( !MediaWikiServices::getInstance()->getTempUserConfig()->isKnown() ) {
			return;
		} else {
			$authority = $out->getAuthority();
			// If the user isn't authorized to view temporary account IP data without having to accept the
			// agreement, ensure they have relevant rights and have accepted the agreement.
			if ( !$authority->isAllowed( 'checkuser-temporary-account-no-preference' ) ) {
				if ( !$authority->isAllowed( 'checkuser-temporary-account' ) ) {
					return;
				}
				if ( !$this->userOptionsLookup->getOption( $authority->getUser(),
					'checkuser-temporary-account-enable' ) ) {
					return;
				}
			}
			$block = $authority->getBlock();
			if ( $block !== null && $block->isSitewide() ) {
				return;
			}
		}
		$out->addModules( [ 'ext.pageTriage.showIp' ] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'changes';
	}

}
