<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use JobQueueGroup;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\CompileArticleMetadataJob;
use MediaWiki\Extension\PageTriage\PageTriage;
use DeferredUpdates;
use LinksUpdate;
use MediaWiki\Logger\LoggerFactory;
use RequestContext;
use Title;
use WikiPage;

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
	/** @var WikiPage[] */
	protected $articles;
	/** @var LinksUpdate[] */
	protected $linksUpdates;

	const SAVE_IMMEDIATE = 0;
	const SAVE_DEFERRED = 1;
	const SAVE_JOB = 2;

	/**
	 * Array of configuration options to pass to self::configComponentDb() for metadata compilation.
	 *
	 * BasicData accesses the `pagetriage_page` table and this may not necessarily be up to
	 * date in a replica, so it is excluded from this list.
	 *
	 * @return array
	 */
	public static function getSafeComponentDbConfigForCompilation() {
		return [
			'LinkCount' => DB_REPLICA,
			'CategoryCount' => DB_REPLICA,
			'Snippet' => DB_REPLICA,
			'UserData' => DB_REPLICA,
			'DeletionTag' => DB_REPLICA,
			'AfcTag' => DB_REPLICA,
		];
	}

	/**
	 * @param array $pageId list of page id
	 */
	private function __construct( $pageId ) {
		$this->mPageId = $pageId;

		$this->component = [
			'BasicData' => 'off',
			'LinkCount' => 'off',
			'CategoryCount' => 'off',
			'Snippet' => 'off',
			'UserData' => 'off',
			'DeletionTag' => 'off',
			'AfcTag' => 'off',
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
	 * @param array $pageId
	 * @param bool $validated whether page ids are validated
	 * @param int $validateDb const DB_MASTER/DB_REPLICA
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
	 * @param WikiPage $article
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
	 * @param string $component
	 */
	public function registerComponent( $component ) {
		if ( isset( $this->component[$component] ) ) {
			$this->component[$component] = 'on';
			$this->defaultMode = false;
		}
	}

	/**
	 * Config what db to use for each component
	 * @param array $config
	 *      example: array( 'BasicData' => DB_REPLICA, 'UserData' => DB_MASTER )
	 */
	public function configComponentDb( $config ) {
		$dbMode = [ DB_MASTER, DB_REPLICA ];
		foreach ( $this->componentDb as $key => $value ) {
			if ( isset( $config[$key] ) && in_array( $config[$key], $dbMode ) ) {
				$this->componentDb[$key] = $config[$key];
			}
		}
	}

	/**
	 * Get the timestamp of the last edit to a page
	 * @param int $pageId Page ID
	 * @return string Timestamp of last update, or current timestamp if not found
	 */
	protected function getLastEditTimestamp( $pageId ) {
		if ( isset( $this->linksUpdates[$pageId] ) ) {
			return $this->linksUpdates[$pageId]->getRevision()->getTimestamp();
		}
		if ( isset( $this->articles[$pageId] ) ) {
			return $this->articles[$pageId]->getTimestamp();
		}
		// TODO deduplicate with ArticleCompileInterface::getArticleByPageId(), maybe move to this class
		$fromdb = $this->componentDb === DB_MASTER ? 'fromdbmaster' : 'fromdb';
		$page = WikiPage::newFromID( $pageId, $fromdb );
		if ( $page ) {
			return $page->getTimestamp();
		}
		// Give up and return the current time
		return wfTimestampNow();
	}

	/**
	 * Wrapper function for compiling metadata.
	 *
	 * @param int $mode Class SAVE_* constant
	 *  - SAVE_IMMEDIATE = Unless overridden with self::configComponentDb(), uses
	 *    master DB for reads and writes. The caller should use self::configComponentDB()
	 *    to use the replica for as many compilation components as possible.
	 *  - SAVE_DEFERRED = The replica is used for reads. The metadata will be written
	 *    to the database at the end of the request in a deferred update.
	 *  - SAVE_JOB = The replica is used for reads. The metadata will be written to
	 *    the database via the job queue. Usage of this mode should be the exception, not
	 *    the norm – it exists as a safeguard to ensure metadata is compiled for any cases
	 *    where the hook implementations missed generating the data.
	 * @return array
	 *   The compiled metadata.
	 */
	public function compileMetadata( $mode = self::SAVE_IMMEDIATE ) {
		// For deferred / job saves, use the replica for reading data.
		if ( in_array( $mode, [ self::SAVE_DEFERRED, self::SAVE_JOB ] ) ) {
			foreach ( $this->component as $key => $value ) {
				$this->componentDb[$key] = DB_REPLICA;
			}
		}

		// Set up which components of metadata to compile.
		$this->prepare();

		// Instantiate the dedicated class for each component, compile the metadata associated
		// with the class, then store the metadata in $this->metadata for use below.
		$this->process();

		switch ( $mode ) {
			case self::SAVE_JOB:
				// This flag is used in ArticleMetadata::getMetadata() when article metadata
				// is missing and the request context is a GET.
				// We will return the already compiled metadata, which was generated by querying
				// a replica, but we will not save the results to the database in this request,
				// instead it will get added to the job queue for later processing.
				// Additionally, the metadata will be cached in memcache for 24 hours.
				// The logging statement below can alert us to errors in our hook implementation.
				// Queue a job for each page that doesn't have metadata.
				foreach ( $this->mPageId as $pageId ) {
					$job = new CompileArticleMetadataJob(
						Title::newMainPage(),
						[ 'pageId' => (int)$pageId ]
					);
					JobQueueGroup::singleton()->push( $job );
				}
				LoggerFactory::getInstance( 'PageTriage' )->warning(
					'Article metadata not found in DB, will attempt to save to DB via the job queue.',
					[
						'trace' => ( new \RuntimeException() )->getTraceAsString(),
						'articles_without_metadata' => implode( ',', $this->mPageId ),
						'raw_query_string' => RequestContext::getMain()->getRequest()
							->getRawQueryString(),
					]
				);
				break;
			case self::SAVE_DEFERRED:
				DeferredUpdates::addCallableUpdate( function () {
					// T152847
					$this->save();
				} );
				break;
			case self::SAVE_IMMEDIATE:
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
				$compClass = 'MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompile' . $key;
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
		$dbr = wfGetDB( DB_REPLICA );

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
			if ( isset( $newData[$row->ptrpt_page_id][$row->ptrt_tag_name] )
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

			$updateReviewedTimestamp = false;

			// Check for the update_reviewed_timestamp flag, which means we should update the
			// ptrp_reviewed_updated field after processing (e.g. submission date of AfC drafts).
			if ( array_key_exists( 'update_reviewed_timestamp', $data ) ) {
				unset( $data['update_reviewed_timestamp'] );
				$updateReviewedTimestamp = true;
			}

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

			if ( $updateReviewedTimestamp ) {
				$row['ptrp_reviewed_updated'] = $dbw->timestamp( $this->getLastEditTimestamp( $pageId ) );
			}

			if ( isset( $data['deleted'] ) ) {
				$row['ptrp_deleted'] = $data['deleted'] ? '1' : '0';
			}
			$pt->update( $row );
			$dbw->endAtomic( __METHOD__ );
		}
	}

}
