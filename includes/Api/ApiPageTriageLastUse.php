<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use MediaWiki\Extension\PageTriage\PageTriageUtil;

class ApiPageTriageLastUse extends ApiBase {

	/**
	 * @throws \ApiUsageException
	 */
	public function execute() {
		$this->checkUserRightsAny( 'patrol' );
		PageTriageUtil::setPageTriageLastUseForUser( $this->getUser() );
		$this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => 'success' ] );
	}

	public function mustBePosted() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}
}
