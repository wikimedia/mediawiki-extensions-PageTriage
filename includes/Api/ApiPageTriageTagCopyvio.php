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
use Wikimedia\Rdbms\IDatabase;

class ApiPageTriageTagCopyvio extends ApiBase {

	public function execute() {
		$this->checkUserRightsAny( 'pagetriage-copyvio' );

		$params = $this->extractRequestParams();
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $params['revid'] );
		if ( !$revision ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $params['revid'] ] );
		}

		$pageId = $revision->getPageId();
		if ( !ArticleMetadata::validatePageIds( [ $pageId ] ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$tags = ArticleMetadata::getValidTags();
		if ( !$tags || !$tags['copyvio'] ) {
			$this->dieWithError( 'apierror-pagetriage-missingtag' );
		}

		// If the page hasn't been tagged with copyvio yet, then insert and log.
		$row = [
			'ptrpt_page_id' => $pageId,
			'ptrpt_tag_id' => $tags['copyvio'],
			'ptrpt_value' => (string)$revision->getId()
		];
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		$ptrptPageId = $dbr->newSelectQueryBuilder()
			->select( 'ptrpt_page_id' )
			->from( 'pagetriage_page_tags' )
			->where( $row )
			->caller( __METHOD__ )
			->fetchField();
		if ( $params['untag'] ) {
			$result = $this->deleteCopyvioTag( $ptrptPageId, $dbw, $row, $revision );
		} else {
			$result = $this->insertCopyvioTag( $ptrptPageId, $dbw, $row, $revision );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Insert a copyvio tag for a particular revision of a particular page
	 *
	 * @param int|false $ptrptPageId
	 * @param IDatabase $dbw
	 * @param array $row SQL condition for use in a WHERE clause
	 * @param revisionRecord $revision
	 * @return array
	 */
	private function insertCopyvioTag( $ptrptPageId, $dbw, $row, $revision ) {
		$pageNotTaggedForCopyvio = $ptrptPageId === false;
		if ( $pageNotTaggedForCopyvio ) {
			$dbw->newReplaceQueryBuilder()
				->replaceInto( 'pagetriage_page_tags' )
				->uniqueIndexFields( [ 'ptrpt_page_id', 'ptrpt_tag_id' ] )
				->row( $row )
				->caller( __METHOD__ )
				->execute();

			$metadata = new ArticleMetadata( [ $revision->getPageId() ] );
			$metadata->flushMetadataFromCache();

			$this->logActivity( $revision );
			$result = [ 'result' => 'success' ];
		} else {
			$result = [
				'result' => 'done',
				'pagetriage_unchanged_status' => $revision->getId(),
			];
		}
		return $result;
	}

	/**
	 * Delete a copyvio tag for a particular revision of a particular page
	 *
	 * @param int|false $ptrptPageId
	 * @param IDatabase $dbw
	 * @param array $row SQL condition for use in a WHERE clause
	 * @param revisionRecord $revision
	 * @return array
	 */
	private function deleteCopyvioTag( $ptrptPageId, $dbw, $row, $revision ) {
		$pageNotTaggedForCopyvio = $ptrptPageId === false;
		if ( $pageNotTaggedForCopyvio ) {
			$result = [
				'result' => 'done',
				'pagetriage_unchanged_status' => $revision->getId(),
			];
		} else {
			$dbw->newDeleteQueryBuilder()
				->delete( 'pagetriage_page_tags' )
				->where( $row )
				->caller( __METHOD__ )
				->execute();
			$this->logActivity( $revision, 'delete' );
			$result = [ 'result' => 'success' ];

			$metadata = new ArticleMetadata( [ $revision->getPageId() ] );
			$metadata->flushMetadataFromCache();
		}
		return $result;
	}

	/**
	 * Log insertion activity for this API endpoint.
	 *
	 * @param RevisionRecord $revision
	 */
	protected function logActivity( RevisionRecord $revision, string $action = 'insert' ) {
		$logEntry = new ManualLogEntry( 'pagetriage-copyvio', $action );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::newFromID( $revision->getPageId() ) );
		$logEntry->setParameters( [
			'4::revId' => $revision->getId(),
		] );
		$logEntry->addTags( 'pagetriage' );
		$logEntry->publish( $logEntry->insert() );
	}

	/** @inheritDoc */
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
			],
			'untag' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'boolean'
			]
		];
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}
}
