<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use DeferredUpdates;
use IBufferingStatsdDataFactory;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\CompileArticleMetadataJob;
use MediaWiki\Extension\PageTriage\PageTriage;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use RequestContext;
use RuntimeException;
use WikiPage;

/**
 * Compiling metadata for articles
 */
class ArticleCompileProcessor {
	/** @var string[] */
	protected $component;

	/** @var int[] Either DB_PRIMARY or DB_REPLICA */
	protected $componentDb;

	/** @var int[] List of page IDs */
	protected $pageIds;

	/** @var array */
	protected $metadata;

	/** @var bool */
	protected $defaultMode;

	/** @var WikiPage[] */
	protected $articles = [];

	/** @var LinksUpdate[] */
	protected $linksUpdates = [];

	/** @var IBufferingStatsdDataFactory */
	private IBufferingStatsdDataFactory $statsdDataFactory;

	public const SAVE_IMMEDIATE = 0;
	public const SAVE_DEFERRED = 1;
	public const SAVE_JOB = 2;

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
			'Recreated' => DB_REPLICA,
		];
	}

	/**
	 * @param int[] $pageIds List of page IDs.
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	private function __construct( $pageIds, IBufferingStatsdDataFactory $statsdDataFactory ) {
		$this->pageIds = $pageIds;

		$this->component = [
			'BasicData' => 'off',
			'LinkCount' => 'off',
			'CategoryCount' => 'off',
			'Snippet' => 'off',
			'UserData' => 'off',
			'DeletionTag' => 'off',
			'AfcTag' => 'off',
			'Recreated' => 'off',
		];
		// default to use master database for data compilation
		foreach ( $this->component as $key => $value ) {
			$this->componentDb[$key] = DB_PRIMARY;
		}

		$this->metadata = array_fill_keys( $this->pageIds, [] );
		$this->defaultMode = true;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/**
	 * Factory for creating an instance
	 * @param int[] $pageIds
	 * @param bool $validated whether page ids are validated
	 * @param int $validateDb const DB_PRIMARY/DB_REPLICA
	 * @return ArticleCompileProcessor|false
	 */
	public static function newFromPageId(
		array $pageIds, $validated = true, $validateDb = DB_PRIMARY
	) {
		if ( !$validated ) {
			$pageIds = ArticleMetadata::validatePageIds( $pageIds, $validateDb );
		}
		if ( $pageIds ) {
			return new ArticleCompileProcessor(
				$pageIds,
				MediaWikiServices::getInstance()->getStatsdDataFactory()
			);
		} else {
			return false;
		}
	}

	/**
	 * Register a linksUpdate to the processor for future compiling
	 * @param LinksUpdate $linksUpdate
	 */
	public function registerLinksUpdate( LinksUpdate $linksUpdate ) {
		$id = $linksUpdate->getTitle()->getArticleID();
		if ( in_array( $id, $this->pageIds ) ) {
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
	 *      example: array( 'BasicData' => DB_REPLICA, 'UserData' => DB_PRIMARY )
	 */
	public function configComponentDb( $config ) {
		$dbMode = [ DB_PRIMARY, DB_REPLICA ];
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
			return $this->linksUpdates[$pageId]->getRevisionRecord()->getTimestamp();
		}
		if ( isset( $this->articles[$pageId] ) ) {
			return $this->articles[$pageId]->getTimestamp();
		}
		// TODO deduplicate with ArticleCompileInterface::getArticleByPageId(), maybe move to this class
		$fromdb = $this->componentDb['BasicData'] === DB_PRIMARY ? WikiPage::READ_LATEST : WikiPage::READ_NORMAL;
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $pageId, $fromdb );
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
	 *    primary DB for reads and writes. The caller should use self::configComponentDB()
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
		$startTime = microtime( true );

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
				$jobs = [];
				foreach ( $this->pageIds as $pageId ) {
					$jobs[] = new CompileArticleMetadataJob(
						Title::newMainPage(),
						[ 'pageId' => (int)$pageId ]
					);
				}
				MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );
				LoggerFactory::getInstance( 'PageTriage' )->debug(
					'Article metadata not found in DB, will attempt to save to DB via the job queue.',
					[
						'exception' => new RuntimeException(),
						'articles_without_metadata' => implode( ',', $this->pageIds ),
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

		if ( $mode === self::SAVE_IMMEDIATE ) {
			$this->statsdDataFactory->timing(
				'timing.pageTriage.articleCompileProcessor.compileMetadata.saveImmediate',
				microtime( true ) - $startTime
			);
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
	 * Compile all the registered components in order
	 */
	protected function process() {
		$completed = [];

		foreach ( $this->component as $key => $val ) {
			if ( $val === 'on' ) {
				$startTime = microtime( true );
				$compClass = 'MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompile' . $key;
				/** @var ArticleCompile $comp */
				$comp = new $compClass( $this->pageIds, $this->componentDb[$key], $this->articles,
					$this->linksUpdates
				);
				if ( !$comp->compile() ) {
					break;
				}
				$this->statsdDataFactory->timing(
					'timing.pageTriage.articleCompileProcessor.process.' . $key,
					microtime( true ) - $startTime
				);
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
						$this->metadata[$pageId]['category_count']--;
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
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();

		if ( !$this->pageIds ) {
			return;
		}

		$tags = ArticleMetadata::getValidTags();

		// Grab existing old metadata
		$res = $dbr->select(
			[ 'pagetriage_page_tags', 'pagetriage_tags' ],
			[ 'ptrpt_page_id', 'ptrt_tag_name', 'ptrpt_value' ],
			[ 'ptrpt_page_id' => $this->pageIds, 'ptrpt_tag_id = ptrt_tag_id' ],
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
						[ [ 'ptrpt_page_id', 'ptrpt_tag_id' ] ],
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
