<?php

class ApiPageTriageStats extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$filter = array();
		foreach ( $this->getAllowedParams() as $key => $value ) {
			if ( $key !== 'namespace' && $params[$key] ) {
				$filter[$key] = $key;
			}
		}

		$data = array(
			'unreviewedarticle' => PageTriageUtil::getUnreviewedArticleStat( $params['namespace'] ),
			'reviewedarticle' => PageTriageUtil::getReviewedArticleStat( $params['namespace'] ),
			'filteredarticle' => PageTriageUtil::getArticleFilterStat( $filter, $params['namespace'] )
		);

		$result = array( 'result' => 'success', 'stats' => $data );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return array(
			'namespace' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'showredirs' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showreviewed'=> array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showunreviewed'=> array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showdeleted' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'namespace' => 'What namespace to pull stats from',
			// default is not to show redirects
			'showredirs' => 'Whether to include redirects or not',
			// default is not to show reviewed
			'showreviewed' => 'Whether to include reviewed or not',
			// default is not to show unreviewed
			'showunreviewed' => 'Whether to include unreviewed or not',
			// default is not to show deleted
			'showdeleted' => 'Whether to include "proposed for deleted" or not',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Get the stats for page triage';
	}

}
