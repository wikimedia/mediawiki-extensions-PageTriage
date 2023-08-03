<?php

namespace MediaWiki\Extension\PageTriage;

use Exception;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use SpecialPage;

/**
 * This file defines the SpecialNewPagesFeed class which handles the functionality for the
 * New Pages Feed (Special:NewPagesFeed).
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */
class SpecialNewPagesFeed extends SpecialPage {

	/**
	 * Initialize the special page.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		parent::__construct( 'NewPagesFeed' );
	}

	/**
	 * Define what happens when the special page is loaded by the user.
	 * @param string $sub The subpage, if any
	 * @throws \ConfigException
	 */
	public function execute( $sub ) {
		// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionConfigUsage
		global $wgPageTriageInfiniteScrolling;
		$this->addHelpLink( 'Help:New_pages_feed' );

		$config = $this->getConfig();

		$request = $this->getRequest();
		$showOresFilters = PageTriageUtil::oresIsAvailable() &&
			( $config->get( 'PageTriageEnableOresFilters' ) || $request->getBool( 'ores' ) );
		$showCopyvio = $showOresFilters &&
			( $config->get( 'PageTriageEnableCopyvio' ) || $request->getBool( 'copyvio' ) );
		$this->setHeaders();
		$out = $this->getOutput();
		$user = $this->getUser();

		// Decide which UI to load
		$uiVersion = $request->getInt( 'ui_version', $config->get( 'PageTriageUIVersion' ) );
		$listModule = 'ext.pageTriage.views.list';
		$listHtml = $this->getListViewHtml();
		if ( $uiVersion === 1 ) {
			$listModule = 'ext.pageTriage.list';
			$listHtml = Html::rawElement( 'div', [ 'id' => 'mwe-pt-list' ] );
		}

		// Output the title of the page
		$out->setPageTitle( $this->msg( 'newpagesfeed' ) );

		// Allow infinite scrolling override from query string parameter
		// We don't use getBool() here since the param is optional
		if ( $request->getText( 'infinite' ) === 'true' ) {
			$wgPageTriageInfiniteScrolling = 'true';
		} elseif ( $request->getText( 'infinite' ) === 'false' ) {
			$wgPageTriageInfiniteScrolling = 'false';
		}

		// Set the config flags in JavaScript
		$globalVars = [
			'pageTriageNamespaces' => PageTriageUtil::getNamespaces(),
			'wgPageTriageInfiniteScrolling' => $wgPageTriageInfiniteScrolling,
			'wgPageTriageStickyControlNav' => $config->get( 'PageTriageStickyControlNav' ),
			'wgPageTriageStickyStatsNav' => $config->get( 'PageTriageStickyStatsNav' ),
			'wgPageTriageUIVersion' => $uiVersion,
			'wgPageTriageEnableReviewButton' => $user->isRegistered() && $user->isAllowed( 'patrol' ),
			'wgPageTriageEnableEnglishWikipediaFeatures' => $config->get( 'PageTriageEnableEnglishWikipediaFeatures' ),
			'wgShowOresFilters' => $showOresFilters,
			'wgShowCopyvio' => $showCopyvio,
		];
		$out->addJsConfigVars( $globalVars );
		// Load the JS
		$out->addModules( [
			'ext.pageTriage.external',
			'ext.pageTriage.util',
			$listModule
		] );

		$warnings = '';
		$warnings .= '<div id="mwe-pt-list-warnings" style="display: none;">';
		$parsedWelcomeMessage = $this->msg(
			'pagetriage-welcome',
			$config->get( 'PageTriageLearnMoreUrl' ),
			$config->get( 'PageTriageFeedbackUrl' )
		)->parse();
		$warnings .= Html::rawElement( 'div', [ 'class' => 'plainlinks' ], $parsedWelcomeMessage );
		$warnings .= '</div>';
		$out->addHTML( $warnings );
		$out->addInlineStyle(
			'.client-nojs #mwe-pt-list-view, .client-js #mwe-pt-list-view-no-js { display: none; }'
		);
		// Output the HTML for the triage interface
		$out->addHTML( $listHtml );
	}

	/**
	 * Get the list control nav HTML.
	 *
	 * @return string
	 */
	private function getListViewHtml() {
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		return $templateParser->processTemplate(
			'ListView',
			[
				'pagetriage-please-wait' => $this->msg( 'pagetriage-please-wait' ),
				'pagetriage-js-required' => $this->msg( 'pagetriage-js-required' ),
				'pagetriage-more' => $this->msg( 'pagetriage-more' ),
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'changes';
	}

}
