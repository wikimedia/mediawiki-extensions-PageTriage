<?php

class ApiPageTriageTagging extends ApiBase {

	public function execute() {
		global $wgUser, $wgRequest;

		$params = $this->extractRequestParams();

		$article = Article::newFromID( $params['pageid'] );

		if ( !$article ) {
			$this->dieUsage( "The page specified does not exist", 'bad-page' );
		}

		$title = $article->getTitle();

		if ( !$title->userCan( 'create' ) || !$title->userCan( 'edit' )
			|| !$title->userCan( 'patrol' ) ) {
			$this->dieUsage( "You don't have permission to do that", 'permission-denied' );
		}

		if ( $wgUser->pingLimiter( 'pagetriage-tagging-action' ) ) {
			$this->dieUsageMsg( array( 'actionthrottledtext' ) );
		}

		$apiParams = array();
		if ( $params['top'] ) {
			$apiParams['prependtext'] = $params['top'] . "\n\n";
		}
		if ( $params['bottom'] ) {
			$apiParams['appendtext'] = "\n\n" . $params['bottom'];
		}

		if ( $apiParams ) {
			// Perform the text insertion
			$api = new ApiMain(
					new DerivativeRequest(
						$wgRequest,
						$apiParams + array(
							'action' => 'edit',
							'title'  => $title->getFullText(),
							'token'  => $params['token'],
						),
						true
					),
					true
				);
	
			$api->execute();
		}

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
			'token' => array(
				ApiBase::PARAM_REQUIRED => true,
			),
			'top' => null,
			'bottom' => null,
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getParamDescription() {
		return array(
			'pageid' => 'The article for which to be tagged',
			'token' => 'Edit token',
			'top' => 'The tagging text to be added to the top of an article',
			'bottom' => 'The tagging text to be added to the bottom of an article'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': version 1.0';
	}

	public function getDescription() {
		return 'Add tags to an article';
	}
}
