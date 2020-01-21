<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

class ApiPageTriageStats extends ApiBase {
	public function execute() {
		// Remove empty params. This unfortunately means you can't query for User:0 :(
		$params = array_filter( $this->extractRequestParams() );

		// set default namespace
		if ( empty( $params['namespace'] ) ) {
			$params['namespace'] = 0;
		}

		$data = [
			'unreviewedarticle' => PageTriageUtil::getUnreviewedArticleStat( $params['namespace'] ),
			'reviewedarticle' => PageTriageUtil::getReviewedArticleStat( $params['namespace'] ),
			'filteredarticle' => PageTriageUtil::getArticleFilterStat( $params ),
			'namespace' => $params['namespace']
		];

		if ( isset( $params['topreviewers'] ) ) {
			$data['topreviewers'] = PageTriageUtil::getTopTriagers( $params['topreviewers'] );
		}

		$result = [ 'result' => 'success', 'stats' => $data ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return array_merge(
			PageTriageUtil::getOresApiParams(),
			PageTriageUtil::getCopyvioApiParam(),
			PageTriageUtil::getCommonApiParams(),
			[
				'topreviewers' => [
					ApiBase::PARAM_TYPE => 'string',
				],
			]
		);
	}
}
