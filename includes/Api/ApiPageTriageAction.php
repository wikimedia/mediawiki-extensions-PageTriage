<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use ApiMain;
use Article;
use ChangeTags;
use DeferredUpdates;
use Language;
use ManualLogEntry;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPageTriageAction extends ApiBase {

	private RevisionStore $revStore;
	private Language $contLang;

	/**
	 * @param ApiMain $queryModule
	 * @param string $moduleName
	 * @param RevisionStore $revStore
	 * @param Language $contLang
	 */
	public function __construct(
		ApiMain $queryModule,
		$moduleName,
		RevisionStore $revStore,
		Language $contLang
	) {
		parent::__construct( $queryModule, $moduleName );
		$this->revStore = $revStore;
		$this->contLang = $contLang;
	}

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

		$logEntryTags = [];
		if ( $params['tags'] ) {
			$tagStatus = ChangeTags::canAddTagsAccompanyingChange(
				$params['tags'],
				$this->getUser()
			);
			if ( !$tagStatus->isOK() ) {
				$this->dieStatus( $tagStatus );
			}
			$logEntryTags = $params['tags'];
		}
		$logEntryTags[] = 'pagetriage';

		$note = $params['note'];

		if ( isset( $params['reviewed'] ) ) {
			// T314245 - do not allow someone to mark their own articles as reviewed
			// when not being autopatrolled
			$pageCreator = $this->revStore->getFirstRevision(
				 $article->getPage() )->getUser( RevisionRecord::RAW );
			$isPageCreator = $this->getUser()->equals( $pageCreator );
			$isNotAutopatrolled = !$this->getAuthority()->isAllowed( 'autopatrol' );
			if ( $isPageCreator && $isNotAutopatrolled ) {
				$this->dieWithError( 'markedaspatrollederror-noautopatrol' );
			}

			$result = $this->markAsReviewed(
				$article,
				$params['reviewed'],
				$note,
				$params['skipnotif'],
				$logEntryTags
			);
		} else {
			$result = $this->enqueue( $article, $note, $logEntryTags );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Article $article
	 * @param string $reviewedStatus
	 * @param string $note
	 * @param bool $skipNotif
	 * @param array $tags
	 * @return array Result for API
	 */
	private function markAsReviewed( Article $article, $reviewedStatus, $note, $skipNotif, $tags ) {
		if (
			!ArticleMetadata::validatePageIds(
				[ $article->getPage()->getId() ],
				DB_REPLICA
			)
		) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}

		$pageTriage = new PageTriage( $article->getPage()->getId() );
		$statusChanged = $pageTriage->setTriageStatus( (int)$reviewedStatus, $this->getUser() );

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

			$reviewLogEntryType = 'reviewed';

			if ( !$reviewedStatus ) {
				$reviewLogEntryType = 'unreviewed';
			}

			if ( $article->getTitle()->isRedirect() ) {
				$reviewLogEntryType .= '-redirect';
			} else {
				$reviewLogEntryType .= '-article';
			}

			// The following messages will be used by this log entry
			// * logentry-pagetriage-curation-reviewed-redirect
			// * logentry-pagetriage-curation-reviewed-article
			// * logentry-pagetriage-curation-unreviewed-redirect
			// * logentry-pagetriage-curation-unreviewed-article
			$this->logAction(
				$article,
				$reviewLogEntryType,
				$note,
				$tags
			);
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
	 * @param array $tags
	 * @return array Result for API
	 */
	private function enqueue( Article $article, $note, $tags ) {
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

		// The following messages will be used by this log entry
		// * logentry-pagetriage-curation-reviewed-redirect
		// * logentry-pagetriage-curation-reviewed-article
		$reviewLogEntryType = 'unreviewed-' . ( $title->isRedirect() ? 'redirect' : 'article' );

		$this->logAction( $article, 'enqueue', $note, $tags );
		$this->logAction( $article, $reviewLogEntryType, $note, $tags );

		return [ 'result' => 'success' ];
	}

	/**
	 * Logs triage action
	 *
	 * @param Article $article
	 * @param string $subtype
	 * @param string $note
	 * @param array $tags
	 */
	private function logAction( Article $article, $subtype, $note, $tags ) {
		$logEntry = new ManualLogEntry(
			'pagetriage-curation',
			$subtype
		);
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $article->getTitle() );
		if ( $note ) {
			$note = $this->contLang->truncateForDatabase( $note, 150 );
			$logEntry->setComment( $note );
		}
		$logEntry->addTags( $tags );
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
			'pageid' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'reviewed' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => [
					// reviewed
					'1',
					// unreviewed
					'0',
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
			],
			'tags' => [
				ParamValidator::PARAM_TYPE => 'tags',
				ParamValidator::PARAM_ISMULTI => true,
			],
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}
}
