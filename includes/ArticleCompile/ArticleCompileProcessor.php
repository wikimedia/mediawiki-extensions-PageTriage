<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\PageTriage;
use DeferredUpdates;
use LinksUpdate;
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
	protected $articles;
	protected $linksUpdates;

	const SAVE_IMMEDIATE = 0;
	const SAVE_DEFERRED = 1;

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
	 * Wrapper function for compiling the data
	 * @param int $mode Class SAVE_* constant
	 * @return array
	 */
	public function compileMetadata( $mode = self::SAVE_IMMEDIATE ) {
		if ( $mode === self::SAVE_DEFERRED ) {
			foreach ( $this->component as $key => $value ) {
				$this->componentDb[$key] = DB_REPLICA;
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
