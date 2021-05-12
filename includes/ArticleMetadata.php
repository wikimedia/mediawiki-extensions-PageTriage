<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Title;
use WANObjectCache;
use Wikimedia\Rdbms\Database;

/**
 * Handles article metadata retrieval and saving to cache
 */
class ArticleMetadata {
	/** @var int[] List of page IDs */
	protected $pageIds;

	/**
	 * @var array Page IDs that are known to exist in the queue
	 */
	private static $cache = [];

	/** @var string */
	private const KEY_COLLECTION = 'pagetriage-article-metadata';

	/**
	 * @param int[] $pageIds List of page IDs.
	 * @param bool $validated whether the page ids have been validated
	 * @param int $validateDb const DB_MASTER/DB_REPLICA
	 */
	public function __construct( array $pageIds, $validated = true, $validateDb = DB_PRIMARY ) {
		if ( $validated ) {
			$this->pageIds = $pageIds;
		} else {
			$this->pageIds = self::validatePageIds( $pageIds, $validateDb );
		}
	}

	/**
	 * Delete all the metadata for an article
	 *
	 * @return bool
	 */
	public function deleteMetadata() {
		if ( $this->pageIds ) {
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->delete(
				'pagetriage_page_tags',
				[ 'ptrpt_page_id' => $this->pageIds ],
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
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		$pageIdsPurge = ( $pageId === null ) ? $this->pageIds : [ $pageId ];
		foreach ( $pageIdsPurge as $pageIdPurge ) {
			$cache->delete( $cache->makeKey( self::KEY_COLLECTION, $pageIdPurge ) );
			// For Hooks::isPageNew
			$cache->delete( $cache->makeKey( 'pagetriage-page-created', $pageIdPurge ) );
		}
	}

	/**
	 * Get metadata from the replica for an array of article IDs.
	 *
	 * @param int[] $pageIds
	 * @return array[] Map of (page ID => article metadata)
	 */
	public static function getMetadataForArticles( array $pageIds ) {
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
				'ptrpt_page_id' => $pageIds,
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
		global $wgPageTriageCacheVersion;

		// @TODO: inject this from somewhere
		$wasPosted = RequestContext::getMain()->getRequest()->wasPosted();

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$metadataByKey = $cache->getMultiWithUnionSetCallback(
			$cache->makeMultiKeys(
				$this->pageIds,
				static function ( $pageId ) use ( $cache ) {
					return $cache->makeKey( self::KEY_COLLECTION, $pageId );
				}
			),
			$cache::TTL_DAY,
			function ( array $pageIds, array &$ttls, array &$setOpts ) use ( $wasPosted ) {
				$dbr = wfGetDB( DB_REPLICA );

				$setOpts += Database::getCacheSetOptions( $dbr );

				// Grab metadata from database after cache attempt
				$metadataByPageId = self::getMetadataForArticles( $pageIds );
				$pageIdsCompile = self::getPagesWithoutMetadata( $pageIds, $metadataByPageId );
				// Compile the denormalized metadata for pages that still don't have it
				if ( $pageIdsCompile ) {
					$acp = ArticleCompileProcessor::newFromPageId(
						$pageIdsCompile,
						false, // skip validation
						DB_REPLICA
					);
					if ( $acp ) {
						// Update the DB in a POSTSEND deferred update if the context is that
						// of an HTTP POST request. Otherwise, enqueue a job to update the DB.
						$mode = $wasPosted ? $acp::SAVE_DEFERRED : $acp::SAVE_JOB;
						$metadataByPageId += $acp->compileMetadata( $mode );
					}
				}

				$placeholderMetadata = array_fill_keys( array_keys( self::getValidTags() ), '' );

				foreach ( $metadataByPageId as $pageId => &$metadata ) {
					$metadata += $placeholderMetadata;
				}

				return $metadataByPageId;
			},
			[ 'version' => $wgPageTriageCacheVersion ]
		);
		$metaDataByPageId = $cache->multiRemap( $this->pageIds, $metadataByKey );

		return $metaDataByPageId;
	}

	/**
	 * Get the pages without metadata yet
	 * @param int[] $articles
	 * @param array[] $data
	 * @return array
	 */
	private static function getPagesWithoutMetadata( array $articles, array $data ) {
		foreach ( $articles as $key => $pageId ) {
			if ( isset( $data[$pageId] ) ) {
				unset( $articles[$key] );
			}
		}
		return $articles;
	}

	/**
	 * Return a list of valid metadata
	 * @return string[] Map of tag name to tag ID
	 */
	public static function getValidTags() {
		global $wgPageTriageCacheVersion;

		$fname = __METHOD__;
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->getWithSetCallback(
			$cache->makeKey( 'pagetriage-valid-tags' ),
			2 * $cache::TTL_DAY,
			static function ( $oldValue, &$ttl, &$setOpts ) use ( $fname ) {
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				$dbr = wfGetDB( DB_REPLICA );
				$res = $dbr->select(
					[ 'pagetriage_tags' ],
					[ 'ptrt_tag_id', 'ptrt_tag_name' ],
					[],
					$fname
				);

				$tags = [];
				foreach ( $res as $row ) {
					$tags[$row->ptrt_tag_name] = $row->ptrt_tag_id;
				}

				// Only set to cache if the result from db is not empty
				if ( !$tags ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
				}

				return $tags;
			},
			[ 'version' => $wgPageTriageCacheVersion ]
		);
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
	 * @param int[] $pageIds List of page IDs.
	 * @param int $validateDb const DB_MASTER/DB_REPLICA
	 * @return int[] The valid page IDs.
	 */
	public static function validatePageIds( array $pageIds, $validateDb = DB_PRIMARY ) {
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

		return array_unique( $cleanUp );
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
				LoggerFactory::getInstance( 'PageTriage' )->debug( 'Incomplete metadata for page.',
					[ 'metadata' => json_encode( $metadata ) ] );
				return false;
			}
		}
		return true;
	}

}
