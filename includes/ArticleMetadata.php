<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Logger\LoggerFactory;
use ObjectCache;
use RequestContext;
use Title;

/**
 * Handles article metadata retrieval and saving to cache
 */
class ArticleMetadata {
	/** @var int[] List of page IDs */
	protected $mPageId;

	/**
	 * @var array Page IDs that are known to exist in the queue
	 */
	private static $cache = [];

	/**
	 * @param array $pageId list of page id
	 * @param bool $validated whether the page ids have been validated
	 * @param int $validateDb const DB_MASTER/DB_REPLICA
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
	 * @return bool
	 */
	public function deleteMetadata() {
		if ( $this->mPageId ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'pagetriage_page_tags',
				[ 'ptrpt_page_id' => $this->mPageId ],
				__METHOD__
			);
			// also remove it from the cache
			$this->flushMetadataFromCache();
		}

		return true;
	}

	/**
	 * Flush the metadata in cache
	 * @param int|null $pageId page id to be flushed, if null is provided, all
	 *                    page id in $this->mPageId will be flushed
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
	 * @param int $pageId page id
	 * @param mixed $singleData data to be saved
	 */
	public function setMetadataToCache( $pageId, $singleData ) {
		$cache = ObjectCache::getMainWANInstance();
		$this->flushMetadataFromCache( $pageId );
		$cache->set( $this->memcKeyPrefix() . '-' . $pageId, $singleData, 86400 ); // 24 hours
	}

	/**
	 * Get the metadata from cache
	 * @param int|null $pageId the page id to get the cache data for, if null is provided
	 *                    all page id in $this->mPageId will be obtained
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
	 * Get metadata from the replica for an array of article IDs.
	 *
	 * @param array $articles
	 * @return array
	 *   An array of metadata keyed on article ID, or an empty array if no results are found.
	 */
	public static function getMetadataForArticles( array $articles ) {
		$dbr = wfGetDB( DB_REPLICA );

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
		return $pageData;
	}

	/**
	 * Get the metadata for a single or list of articles.
	 *
	 * First attempt to load metadata from the cache (memcached backend). If not found, then
	 * attempt to load compiled metadata from the replica. If that fails, recompile the metadata
	 * and either save to DB at end of request (if in a POST context) or add a job to the queue
	 * to save to the DB at a later point in time.
	 *
	 * @return array $metadata: key (page Ids) => value (metadata) pairs
	 */
	public function getMetadata() {
		$articles = $this->mPageId;
		$metaData = $this->getMetadataFromCache();
		$articles = self::getPagesWithoutMetadata( $articles, $metaData );

		// Grab metadata from database after cache attempt
		if ( $articles ) {
			$pageData = self::getMetadataForArticles( $articles );
			$articles = self::getPagesWithoutMetadata( $articles, $pageData );
			// Compile and save the metadata if it is still not available.
			// If in a POST request, use a deferred update to save to the DB, otherwise add
			// a job to the job queue for later processing.
			if ( $articles ) {
				$acp = ArticleCompileProcessor::newFromPageId( $articles, false, DB_REPLICA );
				$mode = RequestContext::getMain()->getRequest()->wasPosted() ?
					ArticleCompileProcessor::SAVE_DEFERRED : ArticleCompileProcessor::SAVE_JOB;
				if ( $acp ) {
					$pageData += $acp->compileMetadata( $mode );
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

			$dbr = wfGetDB( DB_REPLICA );
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
	 * Used to clear the cache between tests.
	 */
	public static function clearStaticCache() {
		self::$cache = [];
	}

	/**
	 * Typecast the value in page id array to int and verify that it's
	 * in page triage queue
	 * @param array $pageIds
	 * @param int $validateDb const DB_MASTER/DB_REPLICA
	 * @return array
	 */
	public static function validatePageId( array $pageIds, $validateDb = DB_MASTER ) {
		$cleanUp = [];
		foreach ( $pageIds as $key => $val ) {
			$casted = (int)$val;
			if ( $casted ) {
				if ( isset( self::$cache[$casted] ) ) {
					if ( self::$cache[$casted] ) {
						$cleanUp[] = $casted;
					}
					unset( $pageIds[$key] );
				} else {
					$pageIds[$key] = $casted;
					self::$cache[$casted] = false;
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
				self::$cache[$row->ptrp_page_id] = true;
			}
		}

		return $cleanUp;
	}

	/**
	 * Check if required metadata generated by ArticleMetadata#getMetadata is set.
	 *
	 * This is intended to help prevent the UI from breaking if metadata compilation fails.
	 *
	 * @param array $metadata
	 * @return bool
	 */
	public static function isValidMetadata( array $metadata ) {
		$required_populated_fields = [ 'user_name', 'title' ];
		foreach ( $required_populated_fields as $field ) {
			if ( !isset( $metadata[$field] ) || $metadata[$field] === '' ) {
				LoggerFactory::getInstance( 'PageTriage' )->warning( 'Incomplete metadata for page.',
					[ 'metadata' => json_encode( $metadata ) ] );
				return false;
			}
		}
		return true;
	}

}
