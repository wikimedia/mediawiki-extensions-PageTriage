<?php

class ApiPageTriageAction extends ApiBase {

	public function execute() {
		global $wgUser;

		$params = $this->extractRequestParams();

		$article = Article::newFromID( $params['pageid'] );
		if ( $article ) {
			if ( !$article->getTitle()->userCan( 'patrol' ) ) {
				$this->permissionError();
			}
		} else {
			$this->pageError();
		}

		if ( $wgUser->pingLimiter( 'pagetriage-mark-action' ) ) {
			$this->dieUsageMsg( array( 'actionthrottledtext' ) );
		}

		$pageTriage = new PageTriage( $params['pageid'] );
		$pageTriage->setTriageStatus( $params['reviewed'], $wgUser );

		$result = array( 'result' => 'success' );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	private function permissionError() {
		$this->dieUsage( "You don't have permission to do that", 'permission-denied' );
	}

	private function pageError() {
		$this->dieUsage( "The page specified does not exist", 'bad-page' );
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
			'reviewed' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array(
					'1', // reviewed
					'0', // unreviewed
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
			'pageid' => 'The article for which to be marked as reviewed or unreviewed',
			'reviewed' => 'whether the article is reviewed or not',
			'token' => 'edit token'
		);
	}

	public function getDescription() {
		return 'Mark an article as reviewed or unreviewed';
	}
}
