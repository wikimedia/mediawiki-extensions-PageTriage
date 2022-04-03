<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use Article;
use DeferredUpdates;
use ManualLogEntry;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

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
			$result = $this->markAsReviewed( $article, $params['reviewed'], $note, $params['skipnotif'] );
		} else {
			$result = $this->enqueue( $article, $note );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Article $article
	 * @param string $reviewedStatus
	 * @param string $note
	 * @param bool $skipNotif
	 * @return array Result for API
	 */
	private function markAsReviewed( Article $article, $reviewedStatus, $note, $skipNotif ) {
		if (
			!ArticleMetadata::validatePageIds(
				[ $article->getPage()->getId() ],
				DB_REPLICA
			)
		) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$pageTriage = new PageTriage( $article->getPage()->getId() );
		$statusChanged = $pageTriage->setTriageStatus( $reviewedStatus, $this->getUser() );

		// no notification or log entry if page status didn't change
		if ( $statusChanged ) {
			// notification on mark as reviewed
			if ( !$skipNotif && $reviewedStatus ) {
				PageTriageUtil::createNotificationEvent(
					$article->getTitle(),
					$this->getUser(),
					'pagetriage-mark-as-reviewed',
					[
						'note' => $note,
					]
				);
			}

			$this->logAction( $article, $reviewedStatus ? 'reviewed' : 'unreviewed', $note );
			return [ 'result' => 'success' ];
		} else {
			return [
				'result' => 'done',
				'pagetriage_unchanged_status' => $article->getPage()->getId(),
			];
		}
	}

	/**
	 * @param Article $article
	 * @param string $note
	 * @return array Result for API
	 */
	private function enqueue( Article $article, $note ) {
		$title = $article->getTitle();
		if ( $title->isMainPage() ) {
			$this->dieWithError( 'apierror-bad-pagetriage-enqueue-mainpage' );
		}
		if ( !in_array( $title->getNamespace(), PageTriageUtil::getNamespaces() ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-enqueue-invalidnamespace' );
		}

		$articleId = $article->getPage()->getId();
		if ( ArticleMetadata::validatePageIds( [ $articleId ], DB_REPLICA ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-enqueue-alreadyqueued' );
		}

		$pt = new PageTriage( $articleId );
		$pt->addToPageTriageQueue();

		DeferredUpdates::addCallableUpdate( static function () use ( $articleId ) {
			// Validate the page ID from DB_PRIMARY, compile metadata from DB_PRIMARY and return.
			$acp = ArticleCompileProcessor::newFromPageId(
				[ $articleId ],
				false,
				DB_PRIMARY
			);
			if ( $acp ) {
				$acp->compileMetadata();
			}
		} );

		$this->logAction( $article, 'enqueue', $note );
		$this->logAction( $article, 'unreviewed', $note );

		return [ 'result' => 'success' ];
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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'reviewed' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => [
					'1', // reviewed
					'0', // unreviewed
				],
			],
			'enqueue' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'token' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'note' => null,
			'skipnotif' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'boolean'
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
