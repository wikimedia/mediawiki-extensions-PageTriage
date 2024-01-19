<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiTestCase;
use ApiUsageException;
use ExtensionRegistry;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\User\UserIdentity;
use MockHttpTrait;

/**
 * @group PageTriage
 * @group extensions
 */
abstract class PageTriageTestCase extends ApiTestCase {

	use MockHttpTrait;

	/** @var int */
	protected $draftNsId = 150;

	protected function setUp(): void {
		parent::setUp();

		// Define a Draft NS unless there already is one.
		$draftNsId = $this->getServiceContainer()->getNamespaceInfo()->
			getCanonicalIndex( 'draft' );
		if ( $draftNsId === null ) {
			$this->setMwGlobals( [
				'wgExtraNamespaces' => [
					$this->draftNsId => 'Draft',
					$this->draftNsId + 1 => 'Draft_talk'
				],
				'wgPageTriageDraftNamespaceId' => $this->draftNsId
			] );
			$this->overrideMwServices();
		} else {
			$this->draftNsId = $draftNsId;
		}

		$this->setMainCache( CACHE_NONE );
		// @TODO figure out why this is only run for the first test method when its in addDbData().
		$this->db->insert(
			'pagetriage_tags',
			[
				[ 'ptrt_tag_name' => 'linkcount', 'ptrt_tag_desc' => 'Number of inbound links' ],
				[ 'ptrt_tag_name' => 'category_count', 'ptrt_tag_desc' => 'Category mapping count' ],
				[ 'ptrt_tag_name' => 'csd_status', 'ptrt_tag_desc' => 'CSD status' ],
				[ 'ptrt_tag_name' => 'prod_status', 'ptrt_tag_desc' => 'PROD status' ],
				[ 'ptrt_tag_name' => 'blp_prod_status', 'ptrt_tag_desc' => 'BLP PROD status' ],
				[ 'ptrt_tag_name' => 'afd_status', 'ptrt_tag_desc' => 'AFD status' ],
				[ 'ptrt_tag_name' => 'rev_count', 'ptrt_tag_desc' => 'Number of edits to the article' ],
				[ 'ptrt_tag_name' => 'page_len', 'ptrt_tag_desc' => 'Number of bytes of article' ],
				[ 'ptrt_tag_name' => 'snippet', 'ptrt_tag_desc' => 'Beginning of article snippet' ],
				[ 'ptrt_tag_name' => 'user_name', 'ptrt_tag_desc' => 'User name' ],
				[ 'ptrt_tag_name' => 'user_editcount', 'ptrt_tag_desc' => 'User total edit' ],
				[ 'ptrt_tag_name' => 'user_creation_date', 'ptrt_tag_desc' => 'User registration date' ],
				[ 'ptrt_tag_name' => 'user_autoconfirmed', 'ptrt_tag_desc' => 'Check if user is autoconfirmed' ],
				[ 'ptrt_tag_name' => 'user_experience',
					'ptrt_tag_desc' => 'Experience level: newcomer, learner, experienced or anonymous' ],
				[ 'ptrt_tag_name' => 'user_bot', 'ptrt_tag_desc' => 'Check if user is in bot group' ],
				[ 'ptrt_tag_name' => 'user_block_status', 'ptrt_tag_desc' => 'User block status' ],
				[ 'ptrt_tag_name' => 'user_id', 'ptrt_tag_desc' => 'User id' ],
				[ 'ptrt_tag_name' => 'reference', 'ptrt_tag_desc' => 'Check if page has references' ],
				// 1.32
				[ 'ptrt_tag_name' => 'afc_state', 'ptrt_tag_desc' => 'The submission state of drafts' ],
				[ 'ptrt_tag_name' => 'copyvio', 'ptrt_tag_desc' =>
					'Latest revision ID that has been tagged as a likely copyright violation, if any' ],
				// 1.34
				[ 'ptrt_tag_name' => 'recreated', 'ptrt_tag_desc' => 'Check if the page has been previously deleted.' ],
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
	 * Helper method for mocking ORES scores data normally fetched from
	 * https://ores.wikimedia.org/v3/scores/$wgOresWikiId/?format=json
	 * @return string
	 */
	protected function getFakeOresScores() {
		global $wgOresWikiId;
		$scores = file_get_contents( __DIR__ . '/data/ores/scores.json' );
		return "{\n\"$wgOresWikiId\": $scores\n}";
	}

	protected function setUpForOresCopyvioTests() {
		ArticleMetadata::clearStaticCache();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ORES' ) ) {
			$this->clearHook( 'ORESCheckModels' );
		}
	}

	/**
	 * Helper method to query the PageTriageList API. Defaults to unreviewed draft pages.
	 * @param array $params
	 * @return array
	 * @throws ApiUsageException
	 */
	protected function getPageTriageList( array $params = [] ) {
		$this->installMockHttp( $this->makeFakeHttpRequest( $this->getFakeOresScores() ) );
		$list = $this->doApiRequest( array_merge( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		], $params ) );

		return $list[0]['pagetriagelist']['pages'];
	}

	/**
	 * Similar to MediaWikiIntegrationTestCase->insertPage(), but writes article metatdata (data in the
	 * pagetriage_page_tags SQL table) synchronously. Normally it is done with an asynchronous
	 * DeferredUpdate, but that is hard to test.
	 *
	 * @param string $title
	 * @param bool $draftQualityClass
	 * @param bool $copyvio
	 * @param ?UserIdentity $user If null, will create a random user for you.
	 * @param string $text Wikitext of the draft page.
	 * @return int page_id
	 */
	protected function makeDraft(
		$title,
		$draftQualityClass = false,
		$copyvio = false,
		?UserIdentity $user = null,
		?string $text = 'some content'
	) {
		if ( !$user ) {
			$user = static::getTestUser()->getUser();
		}
		$pageAndTitle = $this->insertPage( $title, $text, $this->draftNsId, $user );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $pageAndTitle[ 'title' ] );
		$revId = $page->getLatest();
		if ( $draftQualityClass ) {
			$this->setDraftQuality( $revId, $draftQualityClass );
		}
		if ( $copyvio ) {
			$this->setCopyvio( $pageAndTitle[ 'id' ], $revId );
		}
		$acp = ArticleCompileProcessor::newFromPageId( [
			$page->getId()
		] );
		$acp->compileMetadata();
		return $pageAndTitle[ 'id' ];
	}

	/**
	 * @param string[] $expectedPages
	 * @param array[] $response
	 * @param string $msg
	 */
	protected function assertPages( $expectedPages, $response, $msg = '' ) {
		$pagesFromResponse = array_map( static function ( $item ) {
			$title = $item[ 'title' ];
			return strpos( $title, ':' ) !== false ?
				explode( ':', $title )[1] :
				$title;
		}, $response );

		$this->assertArrayEquals( $expectedPages, $pagesFromResponse, false, false, $msg );
	}

	public function setDraftQuality( $revId, $classId ) {
		foreach ( [ 0, 1, 2, 3 ] as $id ) {
			$predicted = $classId === $id;
			$this->db->insert( 'ores_classification', [
				'oresc_model' => $this->ensureOresModel( 'draftquality' ),
				'oresc_class' => $id,
				'oresc_probability' => $predicted ? 0.7 : 0.1,
				'oresc_is_predicted' => $predicted ? 1 : 0,
				'oresc_rev' => $revId,
			] );
		}
	}

	public function ensureCopyvioTag() {
		$this->db->newInsertQueryBuilder()
			->insertInto( 'pagetriage_tags' )
			->row( [ 'ptrt_tag_name' => 'copyvio', 'ptrt_tag_desc' => 'copyvio' ] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'ptrt_tag_name' ] )
			->set( [ 'ptrt_tag_desc' => 'copyvio' ] )
			->caller( __METHOD__ )
			->execute();
	}

	public function setCopyvio( $pageId, $revId ) {
		$tagId = $this->db->newSelectQueryBuilder()
			->select( 'ptrt_tag_id' )
			->from( 'pagetriage_tags' )
			->where( [ 'ptrt_tag_name' => 'copyvio' ] )
			->fetchField();

		$this->db->insert(
			'pagetriage_page_tags',
			[
				'ptrpt_page_id' => $pageId,
				'ptrpt_tag_id' => $tagId,
				'ptrpt_value' => (string)$revId,
			]
		);
	}

	public function ensureOresModel( $name ) {
		$modelInfo = [
			'oresm_name' => $name,
			'oresm_version' => '0.0.1',
			'oresm_is_current' => 1
		];
		$model = $this->db->newSelectQueryBuilder()
			->select( 'oresm_id' )
			->from( 'ores_model' )
			->where( $modelInfo )
			->fetchField();
		if ( $model ) {
			return $model;
		}
		$this->db->insert( 'ores_model', $modelInfo );
		return $this->db->insertId();
	}

}
