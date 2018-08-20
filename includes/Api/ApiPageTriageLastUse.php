<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;

/**
 * API module to save when a user last clicked the "Curate this article" link.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiPageTriageLastUse extends ApiBase {

	/**
	 * If the user is logged in, log their last use of PageTriage in their session.
	 */
	public function execute() {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			return;
		}
		$this->getRequest()->getSession()->set( 'pagetriage-lastuse', wfTimestampNow() );
		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function needsToken() {
		return 'csrf';
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}
}
