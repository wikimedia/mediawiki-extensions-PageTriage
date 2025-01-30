<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ManualLogEntry;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPageTriageTagging extends ApiBase {

	public function execute() {
		$config = $this->getConfig();

		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$params = $this->extractRequestParams();

		if ( !ArticleMetadata::validatePageIds( [ $params['pageid'] ], DB_REPLICA ) ) {
			$this->dieWithError( 'apierror-bad-pagetriage-page' );
		}
		$title = $this->getTitleByPageId( $params['pageid'] );

		$this->checkTitleUserPermissions( $title, [ 'create', 'edit', 'patrol' ] );

		if ( $this->getUser()->pingLimiter( 'pagetriage-tagging-action' ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}

		$apiParams = [
			'text' => $params['wikitext']
		];

		// Parse tags into a human readable list for the edit summary
		$tags = $contLang->commaList( $params['taglist'] );

		// Check if the page has been nominated for deletion
		if ( $params['deletion'] ) {
			$articleMetadata = new ArticleMetadata( [ $params['pageid'] ] );
			$metaData = $articleMetadata->getMetadata();
			if ( isset( $metaData[$params['pageid']] ) ) {
				foreach ( [ 'csd_status', 'prod_status', 'blp_prod_status', 'afd_status' ] as $val ) {
					if ( $metaData[$params['pageid']][$val] == '1' ) {
						$this->dieWithError( 'pagetriage-tag-deletion-error' );
					}
				}
			} else {
				$this->dieWithError( 'apierror-bad-pagetriage-page' );
			}
		}

		$projectLink = '[['
			. $config->get( 'PageTriageProjectLink' ) . '|'
			. $this->msg( 'pagetriage-pagecuration' )->inContentLanguage()->plain()
			. ']]';
		if ( $params['deletion'] ) {
			$editSummary = $this->msg(
				'pagetriage-del-edit-summary',
				$projectLink,
				$tags
			)->inContentLanguage()->plain();
		} else {
			$editSummary = $this->msg(
				'pagetriage-tags-edit-summary',
				$projectLink,
				$tags
			)->inContentLanguage()->plain();
		}

		// tagging something for deletion should automatically watchlist it
		if ( $params['deletion'] ) {
			$apiParams['watchlist'] = 'watch';
		}

		// Perform the text insertion
		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams + [
					'action' => 'edit',
					'title' => $title->getFullText(),
					'token' => $params['token'],
					'summary' => $editSummary,
					'tags' => 'pagetriage',
				],
				true
			),
			true
		);

		$api->execute();

		$note = $contLang->truncateForDatabase( $params['note'], 150 );

		// logging to the logging table
		if ( $params['taglist'] ) {
			if ( $params['deletion'] ) {
				$action = 'delete';

				PageTriageUtil::createNotificationEvent(
					$title,
					$this->getUser(),
					'pagetriage-add-deletion-tag',
					[
						'tags' => $params['taglist'],
						'note' => $note,
					]
				);
			} else {
				$action = 'tag';

				PageTriageUtil::createNotificationEvent(
					$title,
					$this->getUser(),
					'pagetriage-add-maintenance-tag',
					[
						'tags' => $params['taglist'],
						'note' => $note,
						'revId' => $api->getResult()->getResultData( [ 'edit', 'newrevid' ] ),
					]
				);
			}

			$logEntry = new ManualLogEntry( 'pagetriage-curation', $action );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $title );
			if ( $note ) {
				$logEntry->setComment( $note );
			}
			$logEntry->setParameters( [
				'tags' => $params['taglist']
			] );
			$logEntry->addTags( 'pagetriage' );
			$logEntry->publish( $logEntry->insert() );
		}

		$result = [ 'result' => 'success' ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
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
			'pageid' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'token' => [
				ParamValidator::PARAM_REQUIRED => true
			],
			'wikitext' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'deletion' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'note' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
			],
			'taglist' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_ISMULTI => true
			],
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

	/**
	 * type helper for strict checks
	 * @param int $pageId
	 * @return Title
	 * @throws ApiUsageException
	 */
	private function getTitleByPageId( $pageId ): Title {
		$title = Title::newFromID( $pageId );
		if ( $title === null ) {
			$this->dieWithError( 'apierror-missingtitle', 'bad-page' );
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnNullable Still T240141
		return $title;
	}
}
