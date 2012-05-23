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

		// Check tagging text position
		switch ( $params['position'] ) {
			case 'bottom':
				$action = 'appendtext';
				$text = "\n\n" . $params['taggingtext'];
				break;

			case 'top':
			default:
				$action = 'prependtext';
				$text = $params['taggingtext'] . "\n\n";
				break;
		}

		// Perform the text insertion
		$api = new ApiMain(
				new DerivativeRequest(
					$wgRequest,
					array(
						'action' => 'edit',
						'title'  => $title->getFullText(),
						'token'  => $params['token'],
						$action  => $text
					),
					true
				),
				true
			);

		$api->execute();

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
			'taggingtext' => array(
				ApiBase::PARAM_REQUIRED => true,
			),
			'position' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array(
					'top',
					'bottom',
				),
			),
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
			'taggingtext' => 'The tagging text to be added to the article',
			'position' => 'The position where the tagging text is inserted to'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': version 1.0';
	}

	public function getDescription() {
		return 'Add tags to an article';
	}
}
