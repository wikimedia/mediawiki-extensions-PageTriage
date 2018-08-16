<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use WikiPage;

/**
 * The abstract class extended in each ArticleCompile, used by ArticleCompileProcessor.
 */
abstract class ArticleCompileInterface {
	protected $mPageId;
	protected $metadata;
	protected $articles;
	protected $linksUpdates;
	protected $db;
	protected $componentDb;

	/**
	 * @param array $pageId
	 * @param int $componentDb
	 * @param array|null $articles
	 * @param array|null $linksUpdates
	 */
	public function __construct(
		array $pageId, $componentDb = DB_MASTER, $articles = null, $linksUpdates = null
	) {
		$this->mPageId = $pageId;
		$this->metadata = array_fill_keys( $pageId, [] );
		if ( is_null( $articles ) ) {
			$articles = [];
		}
		$this->articles = $articles;
		$this->linksUpdates = $linksUpdates;

		$this->db = wfGetDB( $componentDb );

		$this->componentDb = $componentDb;
	}

	abstract public function compile();

	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Provide an estimated count for an item, for example: if $maxNumToProcess is
	 * 100 and the result is greater than 100, then the result should be 100+
	 * @param int $pageId page id
	 * @param array $table table for query
	 * @param array $conds conditions for query
	 * @param int $maxNumToProcess max number to process/display
	 * @param string $indexName the array index name to be saved
	 */
	protected function processEstimatedCount( $pageId, $table, $conds, $maxNumToProcess, $indexName ) {
		$res = $this->db->select(
			$table,
			'1',
			$conds,
			__METHOD__,
			[ 'LIMIT' => $maxNumToProcess + 1 ]
		);

		$record = $this->db->numRows( $res );
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

	protected function getArticleByPageId( $pageId ) {
		// Try if there is an up-to-date wikipage object from article save
		// else try to create a new one, this is important for replication delay
		if ( isset( $this->articles[$pageId] ) ) {
			$article = $this->articles[$pageId];
		} else {
			if ( $this->componentDb === DB_MASTER ) {
				$from = 'fromdbmaster';
			} else {
				$from = 'fromdb';
			}
			$article = WikiPage::newFromID( $pageId, $from );
		}
		return $article;
	}

	protected function getContentByPageId( $pageId ) {
		// Prefer a preregistered Article, then a preregistered LinksUpdate
		if ( isset( $this->articles[$pageId] ) ) {
			return $this->articles[$pageId]->getContent();
		}
		if ( isset( $this->linksUpdates[$pageId] ) ) {
			$revision = $this->linksUpdates[$pageId]->getRevision();
			if ( $revision ) {
				return $revision->getContent();
			}
		}
		// Fall back on creating a new Article object and fetching from the DB
		$article = $this->getArticleByPageId( $pageId );
		return $article ? $article->getContent() : null;
	}

	protected function getParserOutputByPageId( $pageId ) {
		// Prefer a preregistered LinksUpdate
		if ( isset( $this->linksUpdates[$pageId] ) ) {
			return $this->linksUpdates[$pageId]->getParserOutput();
		}
		// Fall back on Article
		$article = $this->getArticleByPageId( $pageId );
		if ( !$article ) {
			return null;
		}
		$content = $article->getContent();
		if ( !$content ) {
			return null;
		}
		return $content->getParserOutput( $article->getTitle() );
	}
}
