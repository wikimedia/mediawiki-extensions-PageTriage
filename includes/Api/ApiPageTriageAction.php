<?php

namespace MediaWiki\Extension\PageTriage\Api;

use DeferredUpdates;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use Article;
use ApiBase;
use ManualLogEntry;

class ApiPageTriageAction extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$this->requireOnlyOneParameter( $params, 'reviewed', 'enqueue' );

		$article = Article::newFromID( $params['pageid'] );
		if ( $article ) {
			$this->checkTitleUserPermissions( $article->getTitle(), 'patrol' );
		} else {
			$this->dieWithError( 'apierror-missingtitle', 'bad-page' );
		}

		if ( $this->getUser()->pingLimiter( 'pagetriage-mark-action' ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}

		$note = $params['note'];

		if ( isset( $params['reviewed'] ) ) {
			$this->markAsReviewed( $article, $params['reviewed'], $note, $params['skipnotif'] );
		} else {
			$this->enqueue( $article, $note );
		}

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Article $article
	 * @param bool $reviewedStatus
	 * @param string $note
	 * @param bool $skipNotif
	 */
	private function markAsReviewed( Article $article, $reviewedStatus, $note, $skipNotif ) {
		if ( !ArticleMetadata::validatePageIds( [ $article->getId() ], DB_REPLICA ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$pageTriage = new PageTriage( $article->getId() );
		$pageTriage->setTriageStatus( $reviewedStatus, $this->getUser() );

		// notification on mark as reviewed
		if ( !$skipNotif && $reviewedStatus ) {
			PageTriageUtil::createNotificationEvent(
				$article,
				$this->getUser(),
				'pagetriage-mark-as-reviewed',
				[
					'note' => $note,
				]
			);
		}

		$this->logAction( $article, $reviewedStatus ? 'reviewed' : 'unreviewed', $note );
	}

	/**
	 * @param Article $article
	 * @param string $note
	 */
	private function enqueue( Article $article, $note ) {
		$title = $article->getTitle();
		if ( $title->isMainPage() ) {
			$this->dieWithError( 'apierror-bad-pagetriage-enqueue-mainpage' );
		}
		if ( !in_array( $title->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-enqueue-invalidnamespace' );
		}

		$pt = new PageTriage( $article->getId() );
		$pt->addToPageTriageQueue();

		DeferredUpdates::addCallableUpdate( function () use ( $article ) {
			// Validate the page ID from DB_MASTER, compile metadata from DB_MASTER and return.
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $article->getId() ],
				false,
				DB_MASTER
			);
			if ( $acp ) {
				$acp->compileMetadata();
			}
		} );

		$this->logAction( $article, 'enqueue', $note );
		$this->logAction( $article, 'unreviewed', $note );
	}

	/**
	 * Logs triage action
	 *
	 * @param Article $article
	 * @param string $subtype
	 * @param string $note
	 */
	private function logAction( Article $article, $subtype, $note ) {
		$logEntry = new ManualLogEntry(
			'pagetriage-curation',
			$subtype
		);
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $article->getTitle() );
		if ( $note ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$note = $contLang->truncateForDatabase( $note, 150 );
			$logEntry->setComment( $note );
		}
		$logEntry->addTags( 'pagetriage' );
		$logEntry->publish( $logEntry->insert() );
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
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => [
					'1', // reviewed
					'0', // unreviewed
				],
			],
			'enqueue' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'boolean',
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
