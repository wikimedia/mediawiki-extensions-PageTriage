<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use ApiMain;
use ApiUsageException;
use DerivativeRequest;
use ManualLogEntry;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use Title;

class ApiPageTriageTagging extends ApiBase {

	public function execute() {
		global $wgPageTriageProjectLink;

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

		$apiParams = [];
		if ( $params['top'] ) {
			$apiParams['prependtext'] = $params['top'] . "\n\n";
		}
		if ( $params['bottom'] ) {
			$apiParams['appendtext'] = "\n\n" . $params['bottom'];
		}

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

		if ( $apiParams ) {
			$projectLink = '[['
				. $wgPageTriageProjectLink . '|'
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
					$entry = [
						// We want delete tag to have its own log as well as be included under page curation log
						// Todo: Find a way to filter log by action (subtype) so the deletion log can be removed
						'pagetriage-curation' => 'delete',
						'pagetriage-deletion' => 'delete'
					];
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
					$entry = [
						'pagetriage-curation' => 'tag'
					];
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

				foreach ( $entry as $type => $action ) {
					$logEntry = new ManualLogEntry( $type, $action );
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
			}
		}

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
			'token' => [
				ApiBase::PARAM_REQUIRED => true
			],
			'top' => null,
			'bottom' => null,
			'deletion' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'boolean'
			],
			'note' => null,
			'taglist' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => true
			],
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * type helper for strict checks
	 * @param int $pageId
	 * @return Title
	 * @throws ApiUsageException
	 */
	private function getTitleByPageId( $pageId ) : Title {
		$title = Title::newFromID( $pageId );
		if ( $title === null ) {
			$this->dieWithError( 'apierror-missingtitle', 'bad-page' );
			throw new \LogicException( __METHOD__ . ': Impossible case, phpcs helper' );
		}

		return $title;
	}
}
