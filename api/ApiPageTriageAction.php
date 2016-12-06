<?php

class ApiPageTriageAction extends ApiBase {

	public function execute() {
		global $wgContLang;

		$params = $this->extractRequestParams();

		if ( !ArticleMetadata::validatePageId( [ $params['pageid'] ], DB_SLAVE ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$article = Article::newFromID( $params['pageid'] );
		if ( $article ) {
			$this->checkTitleUserPermissions( $article->getTitle(), 'patrol' );
		} else {
			$this->dieWithError( 'apierror-missingtitle', 'bad-page' );
		}

		if ( $this->getUser()->pingLimiter( 'pagetriage-mark-action' ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}

		$pageTriage = new PageTriage( $params['pageid'] );
		$pageTriage->setTriageStatus( $params['reviewed'], $this->getUser() );

		// notification on mark as reviewed
		if ( !$params['skipnotif'] && $params['reviewed'] ) {
			PageTriageUtil::createNotificationEvent(
				$article,
				$this->getUser(),
				'pagetriage-mark-as-reviewed',
				[
					'note' => $params['note'],
				]
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

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getAllowedParams() {
		return [
			'pageid' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'reviewed' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [
					'1', // reviewed
					'0', // unreviewed
				],
			],
			'token' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'note' => null,
			'skipnotif' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'boolean'
			]
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}
}
