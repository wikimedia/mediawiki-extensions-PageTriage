<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use ManualLogEntry;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPageTriageTagCopyvio extends ApiBase {

	public function execute() {
		$this->checkUserRightsAny( 'pagetriage-copyvio' );
		$params = $this->extractRequestParams();

		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $params['revid'] );
		if ( !$revision ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $params['revid'] ] );
		}
		if ( !ArticleMetadata::validatePageIds( [ $revision->getPageId() ] ) ) {
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
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		// If the revision ID hasn't been tagged with copyvio yet, then insert and log.
		if ( $dbr->selectField( 'pagetriage_page_tags', 'ptrpt_page_id', $row, __METHOD__ ) === false ) {
			$dbw->replace(
				'pagetriage_page_tags',
				[ [ 'ptrpt_page_id', 'ptrpt_tag_id' ] ],
				$row,
				__METHOD__
			);
			$this->logActivity( $revision );
			$result = [ 'result' => 'success' ];
		} else {
			$result = [
				'result' => 'done',
				'pagetriage_unchanged_status' => $revision->getId(),
			];
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Log insertion activity for this API endpoint.
	 *
	 * @param RevisionRecord $revision
	 */
	protected function logActivity( RevisionRecord $revision ) {
		$logEntry = new ManualLogEntry( 'pagetriage-copyvio', 'insert' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::newFromID( $revision->getPageId() ) );
		$logEntry->setParameters( [
			'4::revId' => $revision->getId(),
		] );
		$logEntry->addTags( 'pagetriage' );
		$logEntry->publish( $logEntry->insert() );
	}

	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'revid' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
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
