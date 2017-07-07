<?php

/**
 * Handles article metadata retrieval and saving to cache
 */
class ArticleMetadata {
	/** @var int[] List of page IDs */
	protected $mPageId;

	/**
	 * @param $pageId array - list of page id
	 * @param $validated bool - whether the page ids have been validated
	 * @param $validateDb const - DB_MASTER/DB_SLAVE
	 */
	public function __construct( array $pageId, $validated = true, $validateDb = DB_MASTER ) {
		if ( $validated ) {
			$this->mPageId = $pageId;
		} else {
			$this->mPageId = self::validatePageId( $pageId, $validateDb );
		}
	}

	/**
	 * Delete all the metadata for an article
	 *
	 * @param $pageId - the page id to be deleted
	 */
	public function deleteMetadata() {
		if ( $this->mPageId ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'pagetriage_page_tags',
				[ 'ptrpt_page_id' => $this->mPageId ],
				__METHOD__,
				[]
			);
			// also remove it from the cache
			$this->flushMetadataFromCache();
		}

		return true;
	}

	/**
	 * Flush the metadata in cache
	 * @param $pageId - page id to be flushed, if null is provided, all
	 *                  page id in $this->mPageId will be flushed
	 */
	public function flushMetadataFromCache( $pageId = null ) {
		$cache = ObjectCache::getMainWANInstance();

		$keyPrefix = $this->memcKeyPrefix();
		if ( is_null( $pageId ) ) {
			foreach ( $this->mPageId as $pageId ) {
				$cache->delete( $keyPrefix . '-' . $pageId );
			}
		} else {
			$cache->delete( $keyPrefix . '-' . $pageId );
		}
	}

	/**
	 * Set the metadata to cache
	 * @param $pageId int - page id
	 * @param $singleData mixed - data to be saved
	 */
	public function setMetadataToCache( $pageId, $singleData ) {
		$cache = ObjectCache::getMainWANInstance();
		$this->flushMetadataFromCache( $pageId );
		$cache->set( $this->memcKeyPrefix() . '-' . $pageId, $singleData, 86400 ); // 24 hours
	}

	/**
	 * Get the metadata from cache
	 * @param $pageId - the page id to get the cache data for, if null is provided
	 *                  all page id in $this->mPageId will be obtained
	 * @return array
	 */
	public function getMetadataFromCache( $pageId = null ) {
		$cache = ObjectCache::getMainWANInstance();

		$keyPrefix = $this->memcKeyPrefix();

		if ( is_null( $pageId ) ) {
			$metaData = [];
			foreach ( $this->mPageId as $pageId ) {
				$metaDataCache = $cache->get( $keyPrefix . '-' . $pageId );
				if ( $metaDataCache !== false ) {
					$metaData[$pageId] = $metaDataCache;
				}
			}
			return $metaData;
		} else {
			return $cache->get( $keyPrefix . '-' . $pageId );
		}
	}

	/**
	 * Return the prefix of memcache key for article metadata
	 * @return string
	 */
	protected function memcKeyPrefix() {
		global $wgPageTriageCacheVersion;
		return wfMemcKey( 'pagetriage', 'article', 'metadata', $wgPageTriageCacheVersion );
	}

	/**
	 * Get the metadata for a single or list of articles
	 * @return array $metadata: key (page Ids) => value (metadata) pairs
	 */
	public function getMetadata() {
		$articles = $this->mPageId;
		$metaData = $this->getMetadataFromCache();
		$articles = self::getPagesWithoutMetadata( $articles, $metaData );

		// Grab metadata from database after cache attempt
		if ( $articles ) {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
					[
						'pagetriage_page_tags',
						'pagetriage_tags',
						'page',
						'pagetriage_page',
						'user'
					],
					[
						'ptrpt_page_id',
						'ptrt_tag_name',
						'ptrpt_value',
						'ptrp_reviewed',
						'ptrp_created',
						'page_title',
						'page_namespace',
						'page_is_redirect',
						'ptrp_last_reviewed_by',
						'ptrp_reviewed_updated',
						'reviewer' => 'user_name'
					],
					[
						'ptrpt_page_id' => $articles,
						'ptrpt_tag_id = ptrt_tag_id',
						'ptrpt_page_id = ptrp_page_id',
						'page_id = ptrp_page_id'
					],
					__METHOD__,
					[],
					[ 'user' => [ 'LEFT JOIN', 'user_id = ptrp_last_reviewed_by' ] ]
			);

			$pageData = [];
			foreach ( $res as $row ) {
				$pageData[$row->ptrpt_page_id][$row->ptrt_tag_name] = $row->ptrpt_value;
				if ( !isset( $pageData[$row->ptrpt_page_id]['creation_date'] ) ) {
					$pageData[$row->ptrpt_page_id]['creation_date'] = wfTimestamp( TS_MW, $row->ptrp_created );
					// The patrol_status has 4 possible values:
					// 0 = unreviewed, 1 = reviewed, 2 = patrolled, 3 = autopatrolled
					$pageData[$row->ptrpt_page_id]['patrol_status'] = $row->ptrp_reviewed;
					$pageData[$row->ptrpt_page_id]['is_redirect'] = $row->page_is_redirect;
					$pageData[$row->ptrpt_page_id]['ptrp_last_reviewed_by'] = $row->ptrp_last_reviewed_by;
					$pageData[$row->ptrpt_page_id]['ptrp_reviewed_updated'] = wfTimestamp(
						TS_MW,
						$row->ptrp_reviewed_updated
					);
					$pageData[$row->ptrpt_page_id]['reviewer'] = $row->reviewer;
					$title = Title::makeTitle( $row->page_namespace, $row->page_title );
					if ( $title ) {
						$pageData[$row->ptrpt_page_id]['title'] = $title->getPrefixedText();
					}
				}
			}

			$articles = self::getPagesWithoutMetadata( $articles, $pageData );
			// Compile the data if it is not available, this is a very rare case unless
			// the metadata gets deleted manually
			if ( $articles ) {
				$acp = ArticleCompileProcessor::newFromPageId( $articles, false, DB_SLAVE );
				if ( $acp ) {
					$pageData += $acp->compileMetadata( $acp::SAVE_DEFERRED );
				}
			}

			$defaultVal = array_fill_keys( array_keys( self::getValidTags() ), '' );
			foreach ( $pageData as $pageId => &$val ) {
				$val += $defaultVal;
				$this->setMetadataToCache( $pageId, $val );
			}

			$metaData += $pageData;
		}

		return $metaData;
	}

	/**
	 * Get the pages without metadata yet
	 */
	private static function getPagesWithoutMetadata( $articles, $data ) {
		foreach ( $articles as $key => $pageId ) {
			if ( isset( $data[$pageId] ) ) {
				unset( $articles[$key] );
			}
		}
		return $articles;
	}

	/**
	 * Return a list of valid metadata
	 * @return array
	 */
	public static function getValidTags() {
		global $wgPageTriageCacheVersion, $wgMemc;

		$key = wfMemcKey( 'pagetriage', 'valid', 'tags', $wgPageTriageCacheVersion );
		$tags = $wgMemc->get( $key );
		if ( $tags === false ) {
			$tags = [];

			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
					[ 'pagetriage_tags' ],
					[ 'ptrt_tag_id', 'ptrt_tag_name' ],
					[],
					__METHOD__
			);

			foreach ( $res as $row ) {
				$tags[$row->ptrt_tag_name] = $row->ptrt_tag_id;
			}
			// only set to cache if the result from db is not empty
			if ( $tags ) {
				$wgMemc->set( $key, $tags, 60 * 60 * 24 * 2 );
			}
		}

		return $tags;
	}

	/**
	 * Typecast the value in page id array to int and verify that it's
	 * in page triage queue
	 * @param $pageIds array
	 * @param $validateDb const DB_MASTER/DB_SLAVE
	 * @return array
	 */
	public static function validatePageId( array $pageIds, $validateDb = DB_MASTER ) {
		static $cache = [];

		$cleanUp = [];
		foreach ( $pageIds as $key => $val ) {
			$casted = (int)$val;
			if ( $casted ) {
				if ( isset( $cache[$casted] ) ) {
					if ( $cache[$casted] ) {
						$cleanUp[] = $casted;
					}
					unset( $pageIds[$key] );
				} else {
					$pageIds[$key] = $casted;
					$cache[$casted] = false;
				}
			} else {
				unset( $pageIds[$key] );
			}
		}

		if ( $pageIds ) {
			$db = wfGetDB( $validateDb );

			$res = $db->select(
					[ 'pagetriage_page' ],
					[ 'ptrp_page_id' ],
					[ 'ptrp_page_id' => $pageIds ],
					__METHOD__
			);

			foreach ( $res as $row ) {
				$cleanUp[] = $row->ptrp_page_id;
				$cache[$row->ptrp_page_id] = true;
			}
		}

		return $cleanUp;
	}

}

/**
 * Compiling metadata for articles
 */
class ArticleCompileProcessor {
	protected $component;
	protected $componentDb;
	/** @var int[] List of page IDs */
	protected $mPageId;
	protected $metadata;
	protected $defaultMode;
	protected $articles;
	protected $linksUpdates;

	const SAVE_IMMEDIATE = 0;
	const SAVE_DEFERRED = 1;

	/**
	 * @param $pageId array - list of page id
	 */
	private function __construct( $pageId ) {
		$this->mPageId = $pageId;

		$this->component = [
			'BasicData' => 'off',
			'LinkCount' => 'off',
			'CategoryCount' => 'off',
			'Snippet' => 'off',
			'UserData' => 'off',
			'DeletionTag' => 'off'
		];
		// default to use master database for data compilation
		foreach ( $this->component as $key => $value ) {
			$this->componentDb[$key] = DB_MASTER;
		}

		$this->metadata = array_fill_keys( $this->mPageId, [] );
		$this->defaultMode = true;
		$this->articles = [];
	}

	/**
	 * Factory for creating an instance
	 * @param $pageId array
	 * @param $validated bool - whether page ids are validated
	 * @param $validateDb const - DB_MASTER/DB_SLAVE
	 * @return ArticleCompileProcessor|false
	 */
	public static function newFromPageId( array $pageId, $validated = true, $validateDb = DB_MASTER ) {
		if ( !$validated ) {
			$pageId = ArticleMetadata::validatePageId( $pageId, $validateDb );
		}
		if ( $pageId ) {
			return new ArticleCompileProcessor( $pageId );
		} else {
			return false;
		}
	}

	/**
	 * Cache an up-to-date WikiPage object for later use
	 * @param $article - Article
	 */
	public function registerArticle( WikiPage $article ) {
		if ( in_array( $article->getId(), $this->mPageId ) ) {
			$this->articles[$article->getId()] = $article;
		}
	}

	public function registerLinksUpdate( LinksUpdate $linksUpdate ) {
		$id = $linksUpdate->getTitle()->getArticleId();
		if ( in_array( $id, $this->mPageId ) ) {
			$this->linksUpdates[$id] = $linksUpdate;
		}
	}

	/**
	 * Register a component to the processor for compiling
	 * @param $component string
	 */
	public function registerComponent( $component ) {
		if ( isset( $this->component[$component] ) ) {
			$this->component[$component] = 'on';
			$this->defaultMode = false;
		}
	}

	/**
	 * Config what db to use for each component
	 * @param $config array
	 * 		example: array( 'BasicData' => DB_SLAVE, 'UserData' => DB_MASTER )
	 */
	public function configComponentDb( $config ) {
		$dbMode = [ DB_MASTER, DB_SLAVE ];
		foreach ( $this->componentDb as $key => $value ) {
			if ( isset ( $config[$key] ) && in_array( $config[$key], $dbMode ) ) {
				$this->componentDb[$key] = $config[$key];
			}
		}
	}

	/**
	 * Wrapper function for compiling the data
	 * @param integer $mode Class SAVE_* constant
	 * @return array
	 */
	public function compileMetadata( $mode = self::SAVE_IMMEDIATE ) {
		if ( $mode === self::SAVE_DEFERRED ) {
			foreach ( $this->component as $key => $value ) {
				$this->componentDb[$key] = DB_SLAVE;
			}
		}

		$this->prepare();
		$this->process();

		if ( $mode === self::SAVE_DEFERRED ) {
			DeferredUpdates::addCallableUpdate( function () {
				$this->save(); // T152847
			} );
		} else {
			$this->save();
		}

		return $this->metadata;
	}

	/**
	 * Set up the data before compiling
	 */
	protected function prepare() {
		if ( $this->defaultMode ) {
			foreach ( $this->component as $key => $val ) {
				$this->component[$key] = 'on';
			}
		} else {
			// These two set of data are related
			if ( $this->component['CategoryCount'] == 'on' || $this->component['DeletionTag'] == 'on' ) {
				$this->component['CategoryCount'] = 'on';
				$this->component['DeletionTag'] = 'on';
			}
		}
	}

	/**
	 * Compile all of the registered components in order
	 */
	protected function process() {
		$completed = [];

		foreach ( $this->component as $key => $val ) {
			if ( $val === 'on' ) {
				$compClass = 'ArticleCompile' . $key;
				$comp = new $compClass( $this->mPageId, $this->componentDb[$key], $this->articles );
				if ( !$comp->compile() ) {
					break;
				}
				foreach ( $comp->getMetadata() as $pageId => $row ) {
					$this->metadata[$pageId] += $row;
				}
				$completed[] = $key;
			}
		}

		// Subtract deletion tags from category count
		if ( in_array( 'CategoryCount', $completed ) ) {
			$deletionTags = ArticleCompileDeletionTag::getDeletionTags();
			foreach ( $this->metadata as $pageId => $row ) {
				foreach ( $deletionTags as $val ) {
					if ( $this->metadata[$pageId][$val] ) {
						$this->metadata[$pageId]['category_count'] -= 1;
					}
				}

				if ( $this->metadata[$pageId]['category_count'] < 0 ) {
					$this->metadata[$pageId]['category_count'] = '0';
				}
			}
		}
	}

	/**
	 * Save the compiling result to database as well as cache
	 */
	protected function save() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_SLAVE );

		if ( !$this->mPageId ) {
			return;
		}

		$tags = ArticleMetadata::getValidTags();

		// Grab existing old metadata
		$res = $dbr->select(
			[ 'pagetriage_page_tags', 'pagetriage_tags' ],
			[ 'ptrpt_page_id', 'ptrt_tag_name', 'ptrpt_value' ],
			[ 'ptrpt_page_id' => $this->mPageId, 'ptrpt_tag_id = ptrt_tag_id' ],
			__METHOD__
		);
		// data in $newData is used for update, initialize it with new metadata
		$newData = $this->metadata;
		// Loop through old metadata value and compare them with the new one,
		// if they are the same, remove them from $newData
		foreach ( $res as $row ) {
			if ( isset ( $newData[$row->ptrpt_page_id][$row->ptrt_tag_name] )
				&& $newData[$row->ptrpt_page_id][$row->ptrt_tag_name] == $row->ptrpt_value
			) {
				unset( $newData[$row->ptrpt_page_id][$row->ptrt_tag_name] );
			}
		}

		foreach ( $newData as $pageId => $data ) {
			// Flush cache so a new copy of cache will be generated, it's safe to
			// refresh in case some data other than metadata gets updated
			$articleMetadata = new ArticleMetadata( [ $pageId ] );
			$articleMetadata->flushMetadataFromCache();
			// Make sure either all or none metadata for a single page_id
			$dbw->startAtomic( __METHOD__ );
			foreach ( $data as $key => $val ) {
				if ( isset( $tags[$key] ) ) {
					$row = [
						'ptrpt_page_id' => $pageId,
						'ptrpt_tag_id' => $tags[$key],
						'ptrpt_value' => $val
					];
					$dbw->replace(
						'pagetriage_page_tags',
						[ 'ptrpt_page_id', 'ptrpt_tag_id' ],
						$row,
						__METHOD__
					);
				}
			}
			$pt = new PageTriage( $pageId );
			$row = [ 'ptrp_tags_updated' => $dbw->timestamp( wfTimestampNow() ) ];
			if ( isset( $data['deleted'] ) ) {
				$row['ptrp_deleted'] = $data['deleted'] ? '1' : '0';
			}
			$pt->update( $row );
			$dbw->endAtomic( __METHOD__ );
		}
	}

}

/**
 * The following are private classes used by ArticleCompileProcessor
 */

abstract class ArticleCompileInterface {
	protected $mPageId;
	protected $metadata;
	protected $articles;
	protected $linksUpdates;
	protected $db;
	protected $componentDb;

	/**
	 * @param $pageId array
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
	 * Provide an edtimated count for an item, for example: if $maxNumToProcess is
	 * 100 and the result is greater than 100, then the result should be 100+
	 * @param $pageId int - page id
	 * @param $table array - table for query
	 * @param $conds array - conditions for query
	 * @param $maxNumProcess int - max number to process/display
	 * @param $indexName string - the array index name to be saved
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
	 * @param $indexName string - the array index name for the count
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
		// else try to create a new one, this is important for replication deley
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
		if ( isset ( $this->linksUpdates[$pageId] ) ) {
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

/**
 * Article page length, creation date, number of edit, title, article triage status
 */
class ArticleCompileBasicData extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		$count = 0;
		// Process page individually because MIN() GROUP BY is slow
		foreach ( $this->mPageId as $pageId ) {
			$table = [ 'revision', 'page' ];
			$conds = [ 'rev_page' => $pageId, 'page_id = rev_page' ];

			$row = $this->db->selectRow( $table, [ 'MIN(rev_timestamp) AS creation_date' ],
						$conds, __METHOD__ );
			if ( $row ) {
				$this->metadata[$pageId]['creation_date'] = wfTimestamp( TS_MW, $row->creation_date );
				$this->processEstimatedCount( $pageId, $table, $conds, $maxNumToProcess = 100, 'rev_count' );
				$count++;
			}
		}

		// no record in page table
		if ( $count == 0 ) {
			return false;
		}

		$res = $this->db->select(
				[ 'page', 'pagetriage_page', 'user' ],
				[
					'page_id', 'page_namespace', 'page_title', 'page_len',
					'ptrp_reviewed', 'page_is_redirect', 'ptrp_last_reviewed_by',
					'ptrp_reviewed_updated', 'user_name AS reviewer'
				],
				[ 'page_id' => $this->mPageId, 'page_id = ptrp_page_id' ],
				__METHOD__,
				[],
				[ 'user' => [ 'LEFT JOIN', 'user_id = ptrp_last_reviewed_by' ] ]
		);
		foreach ( $res as $row ) {
			if ( isset( $this->articles[$row->page_id] ) ) {
				$title = $this->articles[$row->page_id]->getTitle();
			} else {
				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			}
			$this->metadata[$row->page_id]['page_len'] = $row->page_len;
			// The following data won't be saved into metadata since they are not metadata tags
			// just for saving into cache later
			$this->metadata[$row->page_id]['patrol_status'] = $row->ptrp_reviewed;
			$this->metadata[$row->page_id]['is_redirect'] = $row->page_is_redirect;
			$this->metadata[$row->page_id]['ptrp_last_reviewed_by'] = $row->ptrp_last_reviewed_by;
			$this->metadata[$row->page_id]['ptrp_reviewed_updated'] = wfTimestamp(
				TS_MW,
				$row->ptrp_reviewed_updated
			);
			$this->metadata[$row->page_id]['reviewer'] = $row->reviewer;
			if ( $title ) {
				$this->metadata[$row->page_id]['title'] = $title->getPrefixedText();
			}
		}

		return true;
	}

}

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount(
					$pageId,
					[ 'page', 'pagelinks' ],
					[
						'page_id' => $pageId,
						'page_namespace = pl_namespace',
						'page_title = pl_title'
					],
					$maxNumToProcess = 50,
					'linkcount'
			);
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}

/**
 * Article category count
 */
class ArticleCompileCategoryCount extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( $parserOutput ) {
				$this->metadata[$pageId]['category_count'] = count( $parserOutput->getCategories() );
			}
		}
		$this->fillInZeroCount( 'category_count' );
		return true;
	}

}

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$content = $this->getContentByPageId( $pageId );
			if ( $content ) {
				$text = ContentHandler::getContentText( $content );
				if ( $text !== null ) {
					$this->metadata[$pageId]['snippet'] = self::generateArticleSnippet( $text );
					$this->metadata[$pageId]['reference'] = self::checkReferenceTag( $text );
				}
			}
		}
		return true;
	}

	/**
	 * Generate article snippet for listview from article text
	 * @param $text string - page text
	 * @return string
	 */
	public static function generateArticleSnippet( $text ) {
		global $wgLang;

		$text = strip_tags( $text );
		$attempt = 0;

		// 10 attempts at most, the logic here is to find the first }} and
		// find the matching {{ for that }}
		while ( $attempt < 10 ) {
			$closeCurPos = strpos( $text, '}}' );

			if ( $closeCurPos === false ) {
				break;
			}
			$tempStr = substr( $text, 0, $closeCurPos + 2 );

			$openCurPos = strrpos( $tempStr,  '{{' );
			if ( $openCurPos === false ) {
				$text = substr_replace( $text, '', $closeCurPos, 2 );
			} else {
				$text = substr_replace( $text, '', $openCurPos, $closeCurPos - $openCurPos + 2 );
			}
			$attempt++;
		}

		$text = trim( Sanitizer::stripAllTags(
			MessageCache::singleton()->parse( $text )->getText()
		) );
		// strip out non-useful data for snippet
		$text = str_replace( [ '{', '}', '[edit]' ], '', $text );

		return $wgLang->truncate( $text, 150 );
	}

	/**
	 * Check if a page has reference, this just checks <ref> and </ref> tags
	 * this is sufficient since we just want to get an estimate
	 */
	public static function checkReferenceTag( $text ) {
		$closeTag = strpos( $text, '</ref>' );

		if ( $closeTag !== false ) {
			$openTag = strpos( $text, '<ref ' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return '1';
			}
			$openTag = strpos( $text, '<ref>' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return '1';
			}
		}

		return '0';
	}

}

/**
 * Article User data
 */
class ArticleCompileUserData extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		// Grab the earliest revision based on rev_timestamp and rev_id
		$revId = [];
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->db->selectRow(
				[ 'revision' ],
				[ 'rev_id' ],
				[ 'rev_page' => $pageId ],
				__METHOD__,
				[ 'LIMIT' => 1, 'ORDER BY' => 'rev_timestamp, rev_id' ]
			);

			if ( $res ) {
				$revId[] = $res->rev_id;
			}
		}

		if ( count( $revId ) == 0 ) {
			return true;
		}

		$now = $this->db->addQuotes( $this->db->timestamp() );

		$res = $this->db->select(
				[ 'revision', 'user', 'ipblocks' ],
				[
					'rev_page AS page_id', 'user_id', 'user_name',
					'user_real_name', 'user_registration', 'user_editcount',
					'ipb_id', 'rev_user_text'
				],
				[ 'rev_id' => $revId ],
				__METHOD__,
				[],
				[
					'user' => [ 'LEFT JOIN', 'rev_user = user_id' ],
					'ipblocks' => [
						'LEFT JOIN',
						'rev_user = ipb_user AND rev_user_text = ipb_address AND ipb_expiry > ' . $now
					]
				]
		);

		foreach ( $res as $row ) {
			// User exists
			if ( $row->user_id ) {
				$user = User::newFromRow( $row );
				$this->metadata[$row->page_id]['user_id'] = $row->user_id;
				$this->metadata[$row->page_id]['user_name'] = $user->getName();
				$this->metadata[$row->page_id]['user_editcount'] = $user->getEditCount();
				$this->metadata[$row->page_id]['user_creation_date'] = wfTimestamp(
					TS_MW,
					$user->getRegistration()
				);
				$this->metadata[$row->page_id]['user_autoconfirmed'] =
					$user->isAllowed( 'autoconfirmed' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_bot'] = $user->isAllowed( 'bot' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';
			// User doesn't exist, etc IP
			} else {
				$this->metadata[$row->page_id]['user_id'] = 0;
				$this->metadata[$row->page_id]['user_name'] = $row->rev_user_text;
				$this->metadata[$row->page_id]['user_editcount'] = 0;
				$this->metadata[$row->page_id]['user_creation_date'] = '';
				$this->metadata[$row->page_id]['user_autoconfirmed'] = '0';
				$this->metadata[$row->page_id]['user_bot'] = '0';
				$this->metadata[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';
			}
		}

		return true;
	}

}

/**
 * Article Deletion Tag
 */
class ArticleCompileDeletionTag extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public static function getDeletionTags() {
		return [
			'All_articles_proposed_for_deletion' => 'prod_status',
			'BLP_articles_proposed_for_deletion' => 'blp_prod_status',
			'Candidates_for_speedy_deletion' => 'csd_status',
			'Articles_for_deletion' => 'afd_status'
		];
	}

	public function compile() {
		$deletionTags = self::getDeletionTags();
		foreach ( $this->mPageId as $pageId ) {
			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( $parserOutput ) {
				$categories = $parserOutput->getCategories();
				foreach ( $deletionTags as $category => $tag ) {
					$this->metadata[$pageId][$tag] = isset( $categories[$category] ) ? '1' : '0';
				}
			}
		}
		return true;
	}

}
