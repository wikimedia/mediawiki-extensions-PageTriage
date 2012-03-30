<?php

class ApiPageTriageStats extends ApiBase {

	public function execute() {
		$data = array( 
				'untriagedarticle' => PageTriageUtil::getUntriagedArticleStat(), 
				'toptriager' => PageTriageUtil::getTopTriager() 
			);

		$result = array( 'result' => 'success', 'stats' => $data );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return array();
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	public function getDescription() {
		return 'Get the stats for page triage';
	}

}
