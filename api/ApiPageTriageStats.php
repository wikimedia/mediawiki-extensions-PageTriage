<?php

class ApiPageTriageStats extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$topTriager = PageTriageUtil::getTopTriager();
		// Grab at most top 5 from cache
		if ( count( $topTriager ) > 5 ) {
			$topTriager = array_slice( PageTriageUtil::getTopTriager(), 0 , 5 );
		}

		if ( isset( $params['namespace'] ) ) {
			$ns = $params['namespace'];
		} else {
			$ns = '';
		}
		$data = array(
				'unreviewedarticle' => PageTriageUtil::getUnreviewedArticleStat( $ns ),
				'toptriager' => array(
					'total' => count( $topTriager ),
					'data' => $topTriager
				),
				'userpagestatus' => PageTriageUtil::pageStatusForUser( $topTriager )
			);

		$result = array( 'result' => 'success', 'stats' => $data );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return array(
			'namespace' => array(
				ApiBase::PARAM_TYPE => 'integer',
			)
		);
	}

	public function getParamDescription() {
		return array(
			'namespace' => 'What namespace to pull stats from',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	public function getDescription() {
		return 'Get the stats for page triage';
	}

}