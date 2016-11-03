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

}
