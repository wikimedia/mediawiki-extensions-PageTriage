<?php

namespace MediaWiki\Extension\PageTriage;

use Html;
use SpecialPage;
use TemplateParser;

/**
 * This file defines the SpecialNewPagesFeed class which handles the functionality for the
 * New Pages Feed (Special:NewPagesFeed).
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */
class SpecialNewPagesFeed extends SpecialPage {
	// Holds the various options for viewing the list
	protected $opts;

	/**
	 * Initialize the special page.
	 *
	 * @throws \Exception
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
		global	$wgPageTriageInfiniteScrolling,
				$wgPageTriageStickyControlNav, $wgPageTriageStickyStatsNav,
				$wgPageTriageLearnMoreUrl, $wgPageTriageFeedbackUrl, $wgPageTriageEnableOresFilters,
				$wgPageTriageEnableCopyvio;

		$request = $this->getRequest();
		$showOresFilters = PageTriageUtil::oresIsAvailable() &&
			( $wgPageTriageEnableOresFilters || $request->getBool( 'ores' ) );
		$showCopyvio = $showOresFilters &&
			( $wgPageTriageEnableCopyvio || $request->getBool( 'copyvio' ) );
		$this->setHeaders();
		$out = $this->getOutput();
		$user = $this->getUser();

		// Output the title of the page
		$out->setPageTitle( $this->msg( 'newpagesfeed' ) );

		// Make sure global vars are strings rather than booleans (for passing to mw.config)
		$wgPageTriageInfiniteScrolling = $this->booleanToString( $wgPageTriageInfiniteScrolling );
		$wgPageTriageStickyControlNav = $this->booleanToString( $wgPageTriageStickyControlNav );
		$wgPageTriageStickyStatsNav = $this->booleanToString( $wgPageTriageStickyStatsNav );

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
			'wgPageTriageStickyControlNav' => $wgPageTriageStickyControlNav,
			'wgPageTriageStickyStatsNav' => $wgPageTriageStickyStatsNav,
			'wgPageTriageEnableReviewButton' => $user->isLoggedIn() && $user->isAllowed( 'patrol' ),
			'wgShowOresFilters' => $showOresFilters,
			'wgShowCopyvio' => $showCopyvio,
		];
		$out->addJsConfigVars( $globalVars );

		// Load the JS
		$out->addModules( [
			'ext.pageTriage.external',
			'ext.pageTriage.util',
			'ext.pageTriage.views.list'
		] );

		$warnings = '';
		$warnings .= '<div id="mwe-pt-list-warnings" style="display: none;">';
		$parsedWelcomeMessage = $this->msg(
			'pagetriage-welcome',
			$wgPageTriageLearnMoreUrl,
			$wgPageTriageFeedbackUrl
		)->parse();
		$warnings .= Html::rawElement( 'div', [ 'class' => 'plainlinks' ], $parsedWelcomeMessage );
		$warnings .= '</div>';
		$out->addHTML( $warnings );
		$out->addInlineStyle(
			'.client-nojs #mwe-pt-list-view, .client-js #mwe-pt-list-view-no-js { display: none; }'
		);
		// Output the HTML for the triage interface
		$out->addHTML( $this->getListViewHtml() );
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
	 * Helper function to convert booleans to strings (for passing to mw.config)
	 * @param bool $value The value to convert into a string
	 * @return bool|string
	 */
	private function booleanToString( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		} else {
			// Convert to string
			return $value ? 'true' : 'false';
		}
	}

	protected function getGroupName() {
		return 'changes';
	}

}
