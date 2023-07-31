<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Page\WikiPageFactory;

/**
 * Changes the Action API to support ?action=query&prop=isreviewed, which returns
 * true if a page is marked as reviewed, and false if it isn't.
 */
class ApiIsReviewed extends ApiQueryBase {

	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct( ApiQuery $queryModule, $moduleName, WikiPageFactory $wikiPageFactory ) {
		parent::__construct( $queryModule, $moduleName );
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function execute() {
		$titlesAndPageIds = $this->getPageSet()->getAllTitlesByNamespace();

		$apiRequestHasNoPages = !array_key_exists( 0, $titlesAndPageIds );
		if ( $apiRequestHasNoPages ) {
			return;
		}

		foreach ( $titlesAndPageIds[0] as $pageId ) {
			$wikipage = $this->wikiPageFactory->newFromID( $pageId );

			$wikipageDoesNotExist = $wikipage === null;
			if ( $wikipageDoesNotExist ) {
				continue;
			}

			$isReviewed = !PageTriageUtil::isPageUnreviewed( $wikipage );

			$result = $this->getResult();
			$result->addValue(
				[ 'query', 'pages', $pageId ],
				'isreviewed',
				$isReviewed
			);
		}
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=isreviewed&titles=Main%20Page'
				=> 'apihelp-query+isreviewed-example-1',
		];
	}
}
