<?php

class ApiPageTriageGetMetadata extends ApiBase {

	public function execute() {
		global $wgPageTriagePagesPerRequest;

		$params = $this->extractRequestParams();

		if ( count( $params['page_id'] ) > $wgPageTriagePagesPerRequest ) {
			$this->dieUsage( 'Too many pages in the request', 'exceed-page-limit' );
		}

		$articleMetadata = new ArticleMetadata( $params['page_id'] );
		$metaData = $articleMetadata->getMetadata();

		$result = array( 'result' => 'success', 'page' => $metaData );
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
			'page_id' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			),
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiPageTriageGetMetadata.php $';
	}

	public function getParamDescription() {
		return array(
			'page_id' => 'The list of articles for which metadata is obtained',
		);
	}

	public function getDescription() {
		return 'Get metadata for a list of articles';
	}
}