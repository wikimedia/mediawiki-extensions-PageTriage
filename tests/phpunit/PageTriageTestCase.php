<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiTestCase;
use ApiUsageException;
use ExtensionRegistry;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\MediaWikiServices;
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
		$draftNsId = MediaWikiServices::getInstance()->getNamespaceInfo()->
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
		// Insert minimal required data (subset of what's done in PageTriage/sql/PageTriageTags.sql)
		// @TODO figure out why this is only run for the first test method when its in addDbData().
		$this->db->insert(
			'pagetriage_tags',
			[
				[ 'ptrt_tag_name' => 'afc_state', 'ptrt_tag_desc' => 'For testing' ],
				[ 'ptrt_tag_name' => 'user_name', 'ptrt_tag_desc' => 'For testing' ],
				[ 'ptrt_tag_name' => 'recreated', 'ptrt_tag_desc' => 'For testing' ]
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
	 * @param string $title
	 * @param bool $draftQualityClass
	 * @param bool $copyvio
	 * @return int page_id
	 */
	protected function makePage( $title, $draftQualityClass = false, $copyvio = false ) {
		$user = static::getTestUser()->getUser();
		$pageAndTitle = $this->insertPage( $title, 'some content', $this->draftNsId, $user );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $pageAndTitle[ 'title' ] );
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
		$pageTriage = new PageTriage( $page->getId() );
		$pageTriage->addToPageTriageQueue();
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
		$this->db->upsert(
			'pagetriage_tags',
			[ 'ptrt_tag_name' => 'copyvio', 'ptrt_tag_desc' => 'copyvio' ],
			[ 'ptrt_tag_name' ],
			[ 'ptrt_tag_desc' => 'copyvio' ]
		);
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
				'ptrpt_value' => $revId,
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
