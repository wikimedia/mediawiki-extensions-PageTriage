<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use ManualLogEntry;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\RevisionRecord;
use Title;

class ApiPageTriageTagCopyvio extends ApiBase {

	public function execute() {
		$this->checkUserRightsAny( 'pagetriage-copyvio' );
		$params = $this->extractRequestParams();

		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $params['revid'] );
		if ( !$revision ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $params['revid'] ] );
		}
		if ( !ArticleMetadata::validatePageId( [ $revision->getPageId() ] ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$tags = ArticleMetadata::getValidTags();
		if ( !$tags || !$tags['copyvio'] ) {
			$this->dieWithError( 'apierror-pagetriage-missingtag' );
		}
		$row = [
			'ptrpt_page_id' => $revision->getPageId(),
			'ptrpt_tag_id' => $tags['copyvio'],
			'ptrpt_value' => $revision->getId()
		];
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_MASTER );
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		// If the revision ID hasn't been tagged with copyvio yet, then insert and log.
		if ( $dbr->selectField( 'pagetriage_page_tags', 'ptrpt_page_id', $row ) === false ) {
			$dbw->replace( 'pagetriage_page_tags', [ 'ptrpt_page_id', 'ptrpt_tag_id' ], $row );
			$this->logActivity( $revision );
		}

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Log insertion activity for this API endpoint.
	 *
	 * @param RevisionRecord $revision
	 * @throws \MWException
	 */
	protected function logActivity( RevisionRecord $revision ) {
		$logEntry = new ManualLogEntry( 'pagetriage-copyvio', 'insert' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::newFromId( $revision->getPageId() ) );
		$logEntry->setParameters( [
			'4::revId' => $revision->getId(),
		] );
		$logEntry->insert();
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getAllowedParams() {
		return [
			'revid' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
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
