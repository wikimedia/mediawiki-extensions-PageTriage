<?php

class ApiPageTriageStats extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$filter = [];
		foreach ( $this->getAllowedParams() as $key => $value ) {
			if ( $key !== 'namespace' && $params[$key] ) {
				$filter[$key] = $key;
			}
		}

		$data = [
			'unreviewedarticle' => PageTriageUtil::getUnreviewedArticleStat( $params['namespace'] ),
			'reviewedarticle' => PageTriageUtil::getReviewedArticleStat( $params['namespace'] ),
			'filteredarticle' => PageTriageUtil::getArticleFilterStat( $filter, $params['namespace'] )
		];

		$result = [ 'result' => 'success', 'stats' => $data ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return [
			'namespace' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'showredirs' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showreviewed'=> [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showunreviewed'=> [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showdeleted' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
		];
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return [
			'namespace' => 'What namespace to pull stats from',
			// default is not to show redirects
			'showredirs' => 'Whether to include redirects or not',
			// default is not to show reviewed
			'showreviewed' => 'Whether to include reviewed or not',
			// default is not to show unreviewed
			'showunreviewed' => 'Whether to include unreviewed or not',
			// default is not to show deleted
			'showdeleted' => 'Whether to include "proposed for deleted" or not',
		];
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Get the stats for page triage';
	}

}
