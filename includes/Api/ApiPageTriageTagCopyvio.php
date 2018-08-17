<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\MediaWikiServices;

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
		$dbw->replace( 'pagetriage_page_tags', [ 'ptrpt_page_id', 'ptrpt_tag_id' ], $row );

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
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
