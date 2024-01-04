<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use Content;
use LinksUpdate;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\Rdbms\IDatabase;
use WikiPage;

/**
 * The abstract class extended in each ArticleCompile, used by ArticleCompileProcessor.
 */
abstract class ArticleCompile {
	/** @var int[] List of page IDs */
	protected $mPageId;

	/** @var array */
	protected $metadata;

	/** @var WikiPage[] */
	protected $articles;

	/** @var LinksUpdate[] */
	protected $linksUpdates;

	/** @var IDatabase */
	protected $db;

	/** @var int Either DB_PRIMARY or DB_REPLICA */
	protected $componentDb;

	/**
	 * @param int[] $pageId
	 * @param int $componentDb
	 * @param WikiPage[] $articles
	 * @param LinksUpdate[] $linksUpdates
	 */
	public function __construct(
		array $pageId, $componentDb, array $articles, array $linksUpdates
	) {
		$this->mPageId = $pageId;
		$this->metadata = array_fill_keys( $pageId, [] );
		$this->articles = $articles;
		$this->linksUpdates = $linksUpdates;

		if ( $componentDb == DB_PRIMARY ) {
			$this->db = PageTriageUtil::getPrimaryConnection();
		} else {
			$this->db = PageTriageUtil::getReplicaConnection();
		}

		$this->componentDb = $componentDb;
	}

	abstract public function compile();

	/**
	 * @return array
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Provide an estimated count for an item, for example: if $maxNumToProcess is
	 * 100 and the result is greater than 100, then the result should be 100+
	 * @param int $pageId page id
	 * @param int $record Number of rows in query
	 * @param int $maxNumToProcess max number to process/display
	 * @param string $indexName the array index name to be saved
	 */
	protected function processEstimatedCount( int $pageId, int $record, int $maxNumToProcess, string $indexName ) {
		if ( $record > $maxNumToProcess ) {
			$this->metadata[$pageId][$indexName] = $maxNumToProcess . '+';
		} else {
			$this->metadata[$pageId][$indexName] = $record;
		}
	}

	/**
	 * Fill in zero for page with no estimated count
	 * @param string $indexName the array index name for the count
	 */
	protected function fillInZeroCount( $indexName ) {
		foreach ( $this->mPageId as $pageId ) {
			if ( !isset( $this->metadata[$pageId][$indexName] ) ) {
				$this->metadata[$pageId][$indexName] = '0';
			}
		}
	}

	/**
	 * @param int $pageId
	 *
	 * @return WikiPage|null
	 */
	protected function getWikiPageByPageId( $pageId ): ?WikiPage {
		// Try if there is an up-to-date wikipage object from article save
		// else try to create a new one, this is important for replication delay
		if ( isset( $this->articles[$pageId] ) ) {
			$article = $this->articles[$pageId];
		} else {
			if ( $this->componentDb === DB_PRIMARY ) {
				$from = WikiPage::READ_LATEST;
			} else {
				$from = WikiPage::READ_NORMAL;
			}
			$article = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $pageId, $from );
		}

		return $article;
	}

	/**
	 * @param int $pageId
	 *
	 * @return Content|null
	 */
	protected function getContentByPageId( $pageId ) {
		// Prefer a preregistered Article, then a preregistered LinksUpdate
		if ( isset( $this->articles[$pageId] ) ) {
			return $this->articles[$pageId]->getContent();
		}
		if ( isset( $this->linksUpdates[$pageId] ) ) {
			$revRecord = $this->linksUpdates[$pageId]->getRevisionRecord();
			if ( $revRecord ) {
				return $revRecord->getContent( SlotRecord::MAIN );
			}
		}
		// Fall back on creating a new WikiPage object and fetching from the DB
		$wikiPage = $this->getWikiPageByPageId( $pageId );

		return $wikiPage ? $wikiPage->getContent() : null;
	}

	/**
	 * @param int $pageId
	 * @return bool|ParserOutput|null
	 */
	protected function getParserOutputByPageId( $pageId ) {
		// Prefer a preregistered LinksUpdate
		if ( isset( $this->linksUpdates[$pageId] ) ) {
			return $this->linksUpdates[$pageId]->getParserOutput();
		}
		// Fall back on WikiPage
		$wikiPage = $this->getWikiPageByPageId( $pageId );
		if ( !$wikiPage ) {
			return null;
		}

		return $wikiPage->getParserOutput(
			$wikiPage->makeParserOptions( 'canonical' )
		);
	}
}
