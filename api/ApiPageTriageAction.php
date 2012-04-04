<?php

class ApiPageTriageAction extends ApiBase {
	
	public function execute() {
		global $wgUser;

		if ( $wgUser->isAnon() || $wgUser->isBlocked( false )  ) {
			$this->dieUsage( "You don't have permission to do that", 'permission-denied' );
		}
		
		//@Todo: Add more user permission checking

		$params = $this->extractRequestParams();
		
		$pageTriage = new PageTriage( $params['pageid'] );
		$pageTriage->setTriageStatus( $params['triaged'], $wgUser );

		$result = array( 'result' => 'success' );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function getAllowedParams() {
		return array(
			'pageid' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			),
			'triaged' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array(
					'1',
					'0',
				),
			),
			'token' => array(
				ApiBase::PARAM_REQUIRED => true,
			)
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	public function getParamDescription() {
		return array(
			'pageid' => 'The article for which to be marked as triaged or untriaged',
			'triaged' => 'whether the article is triaged or not',
			'token' => 'edit token'
		);
	}

	public function getDescription() {
		return 'Mark an article as triaged or untriaged';
	}
}
