<?php

class ApiPageTriageStats extends ApiBase {
	public function execute() {
		// Remove empty params. This unforunately means you can't query for User:0 :(
		$params = array_filter( $this->extractRequestParams() );

		// set default namespace
		if ( empty( $params['namespace'] ) ) {
			$params['namespace'] = 0;
		}

		$data = [
			'unreviewedarticle' => PageTriageUtil::getUnreviewedArticleStat( $params['namespace'] ),
			'reviewedarticle' => PageTriageUtil::getReviewedArticleStat( $params['namespace'] ),
			'filteredarticle' => PageTriageUtil::getArticleFilterStat( $params ),
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
		return [
			'namespace' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'showredirs' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showreviewed' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showunreviewed' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showdeleted' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showbots' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'no_category' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'no_inbound_links' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'non_autoconfirmed_users' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'blocked_users' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'username' => [
				ApiBase::PARAM_TYPE => 'user',
			],
			'topreviewers' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}
}
