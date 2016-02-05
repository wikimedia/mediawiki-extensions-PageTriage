<?php

class ApiPageTriageAction extends ApiBase {

	public function execute() {
		global $wgContLang;

		$params = $this->extractRequestParams();

		if ( !ArticleMetadata::validatePageId( array( $params['pageid'] ), DB_SLAVE ) ) {
			$this->dieUsage(
				'The page specified does not exist in pagetriage queue',
				'bad-pagetriage-page'
			);
		}

		$article = Article::newFromID( $params['pageid'] );
		if ( $article ) {
			if ( !$article->getTitle()->userCan( 'patrol' ) ) {
				$this->permissionError();
			}
		} else {
			$this->pageError();
		}

		if ( $this->getUser()->pingLimiter( 'pagetriage-mark-action' ) ) {
			$this->dieUsageMsg( array( 'actionthrottledtext' ) );
		}

		$pageTriage = new PageTriage( $params['pageid'] );
		$pageTriage->setTriageStatus( $params['reviewed'], $this->getUser() );

		// notification on mark as reviewed
		if ( !$params['skipnotif'] && $params['reviewed'] ) {
			PageTriageUtil::createNotificationEvent(
				$article,
				$this->getUser(),
				'pagetriage-mark-as-reviewed',
				array(
					'note' => $params['note'],
				)
			);
		}

		// logging
		$logEntry = new ManualLogEntry(
			'pagetriage-curation',
			$params['reviewed'] ? 'reviewed' : 'unreviewed'
		);
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $article->getTitle() );
		$note = $wgContLang->truncate( $params['note'], 150 );
		if ( $note ) {
			$logEntry->setComment( $note );
		}
		$logEntry->publish( $logEntry->insert() );

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
		return 'csrf';
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
			),
			'note' => null,
			'skipnotif' => array(
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'boolean'
			)
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'pageid' => 'The article for which to be marked as reviewed or unreviewed',
			'reviewed' => 'whether the article is reviewed or not',
			'token' => 'edit token',
			'note' => 'personal note to page creators from reviewers',
			'skipnotif' => 'whether to skip notification or not'
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Mark an article as reviewed or unreviewed';
	}
}
