<?php

/**
 * Handles article metadata retrieval and saving to cache
 */
class ArticleMetadata {

	protected $mPageId;

	/**
	 * @param $pageId array - list of page id
	 * @param $validated bool - whether the page ids are validated
	 */
	public function __construct( array $pageId, $validated = true ) {
		if ( $validated ) {
			$this->mPageId = $pageId;	
		} else {
			$this->mPageId = self::validatePageId( $pageId );
		}
	}

	/**
	 * Delete all the metadata for an article
	 *
	 * @param $pageId - the page id to be deleted
	 */
	public function deleteMetadata( $pageId = null ) {
		if ( is_null( $pageId ) ) {
			$pageId = $this->mPageId;
		}

		if ( $pageId ) {
			// $pageId can be an array or a single value.
			$dbw  = wfGetDB( DB_MASTER );
	
			$dbw->begin();
			$dbw->delete(
				'pagetriage_page_tags',
				array( 'ptrpt_page_id' => $pageId ),
				__METHOD__,
				array()
			);
	
			$dbw->delete(
				'pagetriage_page',
				array( 'ptrp_page_id' => $pageId ),
				__METHOD__,
				array()
			);
	
			$dbw->delete(
				'pagetriage_log',
				array( 'ptrl_page_id' => $pageId ),
				__METHOD__,
				array()
			);
	
			// also remove it from the cache
			$this->flushMetadataFromCache( $pageId );
			$dbw->commit();
		}

		return true;
	}
	
	/**
	 * Update the metadata in cache
	 * @param $update array - key => value pair for update
	 * @param $pageId int
	 */
	public function updateMetadataInCache( $update, $pageId = null ) {
		global $wgMemc;

		$keyPrefix = $this->memcKeyPrefix();

		if ( $pageId ) {
			$pageId = array( $pageId );
		} else {
			$pageId = $this->mPageId;
		}
		
		foreach ( $pageId as $val ) {
			$data =  $wgMemc->get( $keyPrefix . '-' . $val );
			if ( $data !== false ) {
				$wgMemc->replace( $keyPrefix . '-' . $val, array_merge( $data, $update ), 86400 );
			}
		}
		
	}

	/**
	 * Flush the metadata in cache
	 * @param $pageId - page id to be flushed, if null is provided, all
	 *                  page id in $this->mPageId will be flushed
	 */
	public function flushMetadataFromCache( $pageId = null ) {
		global $wgMemc;

		$keyPrefix = $this->memcKeyPrefix();
		if ( is_null( $pageId ) ) {
			foreach ( $this->mPageId as $pageId ) {
				$wgMemc->delete(  $keyPrefix . '-' . $pageId );
			}
		} else {
			$wgMemc->delete(  $keyPrefix . '-' . $pageId );
		}
	}

	/**
	 * Set the metadata to cache
	 * @param $pageId int - page id
	 * @param $singleData mixed - data to be saved
	 */
	public function setMetadataToCache( $pageId, $singleData ) {
		global $wgMemc;

		$this->flushMetadataFromCache( $pageId );
		$wgMemc->set(  $this->memcKeyPrefix() . '-' . $pageId, $singleData, 86400 ); // 24 hours
	}

	/**
	 * Get the metadata from cache
	 * @param $pageId - the page id to get the cache data for, if null is provided
	 *                  all page id in $this->mPageId will be obtained
	 * @return array
	 */
	public function getMetadataFromCache( $pageId = null ) {
		global $wgMemc;

		$keyPrefix = $this->memcKeyPrefix();

		if ( is_null( $pageId ) ) {
			$metaData = array();
			foreach ( $this->mPageId as $pageId ) {
				$metaDataCache = $wgMemc->get( $keyPrefix . '-' . $pageId );
				if ( $metaDataCache !== false ) {
					$metaData[$pageId] = $metaDataCache;
				}
			}
			return $metaData;
		} else {
			return $wgMemc->get( $keyPrefix . '-' . $pageId );
		}
	}

	/**
	 * Return the prefix of memcache key for article metadata
	 * @return string
	 */
	protected function memcKeyPrefix() {
		return wfMemcKey( 'article', 'metadata' );
	}

	/**
	 * Get the metadata for a single or list of articles
	 * @return array
	 */
	public function getMetadata() {
		$articles = $this->mPageId;
		$metaData = $this->getMetadataFromCache();

		foreach ( $articles as $key => $pageId ) {
			if ( isset( $metaData[$pageId] ) ) {
				unset( $articles[$key] );
			}
		}

		// Grab metadata from database after cache attempt
		if ( $articles ) {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
					array( 'pagetriage_page_tags', 'pagetriage_tags', 'pagetriage_page' ),
					array( 'ptrpt_page_id', 'ptrt_tag_name', 'ptrpt_value', 'ptrp_reviewed', 'ptrp_timestamp' ),
					array( 'ptrpt_page_id' => $articles, 'ptrpt_tag_id = ptrt_tag_id', 'ptrpt_page_id = ptrp_page_id' ),
					__METHOD__
			);

			$pageData = array();
			foreach ( $res as $row ) {
				$pageData[$row->ptrpt_page_id][$row->ptrt_tag_name] = $row->ptrpt_value;
				if ( !isset( $pageData[$row->ptrpt_page_id]['creation_date'] ) ) {
					$pageData[$row->ptrpt_page_id]['creation_date'] = $row->ptrp_timestamp;
					$pageData[$row->ptrpt_page_id]['patrol_status'] = $row->ptrp_reviewed;
				}
			}

			foreach ( $articles as $key => $pageId ) {
				if ( isset( $pageData[$pageId] ) ) {
					unset( $articles[$key] );
				}
			}
			// Compile the data if it is not available
			if ( $articles ) {
				$acp = ArticleCompileProcessor::newFromPageId( $articles );
				if ( $acp ) {
					$pageData += $acp->compileMetadata();
				}
			}

			foreach ( $pageData as $pageId => $val ) {
				$this->setMetadataToCache( $pageId, $val );
			}

			$metaData += $pageData;
		}

		return $metaData;
	}

	/**
	 * Return a list of valid metadata
	 * @return array
	 */
	public static function getValidTags() {
		static $tags = array();

		if ( count( $tags ) > 0 ) {
			return $tags;
		}

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
				array( 'pagetriage_tags' ),
				array( 'ptrt_tag_id', 'ptrt_tag_name' ),
				array( ),
				__METHOD__
		);

		foreach ( $res as $row ) {
			$tags[$row->ptrt_tag_name] = $row->ptrt_tag_id;
		}

		return $tags;
	}

	/**
	 * Typecast the value in page id array to int and verify that it's
	 * in page triage queue
	 * @param $pageIds array
	 * @return array
	 */
	public static function validatePageId( array $pageIds ) {
		static $cache = array();

		$cleanUp = array();
		foreach ( $pageIds as $key => $val ) {
			$casted = intval( $val );
			if ( $casted ) {
				if ( isset( $cache[$casted] ) ) {
					if ( $cache[$casted] ) {
						$cleanUp[] = $casted;
					}
					unset( $pageIds[$key] );
				} else {
					$pageIds[$key] = $casted;
					$cahce[$casted] = false;
				}
			} else {
				unset( $pageIds[$key] );
			}
		}

		if ( $pageIds ) {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
					array( 'pagetriage_page' ),
					array( 'ptrp_page_id' ),
					array( 'ptrp_page_id' => $pageIds ),
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
	protected $mPageId;
	protected $metadata;
	protected $defaultMode;

	/**
	 * @param $pageId array - list of page id
	 */
	private function __construct( $pageId ) {
		$this->mPageId = $pageId;

		$this->component = array(
			'BasicData' => 'off',
			'LinkCount' => 'off',
			'CategoryCount' => 'off',
			'Snippet' => 'off',
			'UserData' => 'off',
			'DeletionTag' => 'off'
		);
		$this->metadata = array_fill_keys( $this->mPageId, array() );
		$this->defaultMode = true;
	}

	/**
	 * Factory for creating an instance
	 * @param $pageId array
	 * @param $validated bool - whether page ids are validated
	 * @return ArticleCompileProcessor|false
	 */
	public static function newFromPageId( array $pageId, $validated = true ) {
		if ( !$validated ) {
			$pageId = ArticleMetadata::validatePageId( $pageId );
		}
		if ( $pageId ) {
			return new ArticleCompileProcessor( $pageId );
		} else {
			return false;
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
	 * Wrapper function for compiling the data
	 * @return array
	 */
	public function compileMetadata() {
		$this->prepare();
		$this->process();
		$this->save();
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
		$completed = array();

		foreach ( $this->component as $key => $val ) {
			if ( $val === 'on' ) {
				$compClass = 'ArticleCompile' . $key;
				$comp = new $compClass( $this->mPageId );
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
				foreach( $deletionTags as $val ) {
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
		$dbw  = wfGetDB( DB_MASTER );

		$tags = ArticleMetadata::getValidTags();

		foreach ( $this->metadata as $pageId => $data ) {
			//Flush cache so a new copy of cache will be generated
			$ArticleMetadata = new ArticleMetadata( array( $pageId ) );
			$ArticleMetadata->flushMetadataFromCache();
			//Make sure either all or none metadata for a single page_id
			$dbw->begin();
			foreach ( $data as $key => $val) {
				if ( isset( $tags[$key] ) ) {
					$row = array (
						'ptrpt_page_id' => $pageId,
						'ptrpt_tag_id' => $tags[$key],
						'ptrpt_value' => $val
					);
				}
				$dbw->replace( 'pagetriage_page_tags', array( 'ptrpt_page_id', 'ptrpt_tag_id' ), $row, __METHOD__ );
			}
			if ( isset( $data['deleted'] ) ) {
				$pt = new PageTriage( $pageId );
				$pt->setDeleted( $data['deleted'] ? '1' : '0' );
			}
			$dbw->commit();
		}
	}

}

/**
 * The following are private classes used by ArticleCompileProcessor
 */

abstract class ArticleCompileInterface {
	protected $mPageId;
	protected $metadata;
	
	/**
	 * @param $pageId array
	 */
	public function __construct( array $pageId ) {
		$this->mPageId = $pageId;
		$this->metadata = array_fill_keys( $pageId, array() );
	}

	public abstract function compile();

	public function getMetadata() {
		return $this->metadata;
	}
}

/**
 * Article page length, creation date, number of edit, title, article triage status
 */
class ArticleCompileBasicData extends ArticleCompileInterface {

	public function __construct( $pageId ) {
		parent::__construct( $pageId );
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
				array ( 'page', 'revision', 'pagetriage_page' ),
				array (
					'page_id', 'page_namespace', 'page_title', 'page_len',
					'COUNT(rev_id) AS rev_count', 'ptrp_reviewed',
					'MIN(rev_timestamp) AS creation_date'
				),
				array ( 'page_id' => $this->mPageId, 'page_id = rev_page', 'page_id = ptrp_page_id'),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			$this->metadata[$row->page_id]['page_len'] = $row->page_len;
			$this->metadata[$row->page_id]['rev_count'] = $row->rev_count;
			$this->metadata[$row->page_id]['title'] = $title->getPrefixedText();
			// The following data won't be saved into metadata since they are not metadata tags
			// just for saving into cache later
			$this->metadata[$row->page_id]['creation_date'] = $row->creation_date;
			$this->metadata[$row->page_id]['patrol_status'] = $row->ptrp_reviewed;
		}

		if ( count( $this->metadata) == 0 ) {
			return false;
		} else {
			return true;
		}
	}

}

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompileInterface {

	public function __construct( $pageId ) {
		parent::__construct( $pageId );
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
				array( 'page', 'pagelinks' ),
				array( 'page_id', 'COUNT(pl_from) AS linkcount' ),
				array(
					'page_id' => $this->mPageId,
					'page_namespace = pl_namespace',
					'page_title = pl_title'
				),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$this->metadata[$row->page_id]['linkcount'] = $row->linkcount;
		}
		foreach ( $this->mPageId as $pageId ) {
			if ( !isset( $this->metadata[$pageId]['linkcount'] ) ) {
				$this->metadata[$pageId]['linkcount'] = '0';
			}
		}

		return true;
	}

}

/**
 * Article category count
 */
class ArticleCompileCategoryCount extends ArticleCompileInterface {

	public function __construct( $pageId ) {
		parent::__construct( $pageId );
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
				array( 'page', 'categorylinks' ),
				array( 'page_id', 'COUNT(cl_to) AS category_count' ),
				array( 'page_id' => $this->mPageId, 'page_id = cl_from' ),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$this->metadata[$row->page_id]['category_count'] = $row->category_count;
		}
		foreach ( $this->mPageId as $pageId ) {
			if ( !isset( $this->metadata[$pageId]['category_count'] ) ) {
				$this->metadata[$pageId]['category_count'] = '0';
			}
		}

		return true;
	}

}

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompileInterface {

	public function __construct( $pageId ) {
		parent::__construct( $pageId );
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		// Article snippet
		$res = $dbr->select(
				array( 'text', 'revision', 'page' ),
				array( 'page_id', 'old_text' ),
				array( 'page_id' => $this->mPageId, 'page_latest = rev_id', 'rev_text_id = old_id' ),
				__METHOD__
		);
		foreach ( $res as $row ) {
			$this->metadata[$row->page_id]['snippet'] = self::generateArticleSnippet( $row->old_text );
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

		$attempt = 1;
		$openCurPos  = strpos($text, '{{');
		$closeCurPos = strpos($text, '}}');

		while( $openCurPos !== false && $closeCurPos !== false && $openCurPos < $closeCurPos ) {
			// replace all templates with empty string
			$text = preg_replace( '/\{\{[^\{]((?!\{\{).)*?\}\}/is', '', $text );

			$openCurPos  = strpos($text, '{{');
			$closeCurPos = strpos($text, '}}');

			$attempt++;
			// only try 5 nested levels at max
			if ( $attempt > 5 ) {
				break;
			}
		}

		$text = trim( strip_tags( MessageCache::singleton()->parse( $text )->getText() ) );
		// strip out non-useful data for snippet
		$text = str_replace( array('{', '}', '[edit]' ), '', $text );

		return $wgLang->truncate( $text, 150 );
	}

}

/**
 * Article User data
 */
class ArticleCompileUserData extends ArticleCompileInterface {

	public function __construct( $pageId ) {
		parent::__construct( $pageId );
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
				array( 'revision' ),
				array( 'MIN(rev_id) AS rev_id' ),
				array( 'rev_page' => $this->mPageId ),
				__METHOD__,
				array( 'GROUP BY' => 'rev_page' )
		);

		$revId = array();

		foreach ( $res as $row ) {
			$revId[] = $row->rev_id;
		}

		$res = $dbr->select(
				array( 'revision', 'user', 'ipblocks' ),
				array(
					'rev_page AS page_id', 'user_id', 'user_name',
					'user_real_name', 'user_registration', 'user_editcount',
					'ipb_id', 'rev_user_text'
				),
				array( 'rev_id' => $revId ),
				__METHOD__,
				array(),
				array( 'user' => array(
							'LEFT JOIN', 'rev_user = user_id' ),
							'ipblocks' => array( 'LEFT JOIN', 'rev_user = ipb_user AND rev_user_text = ipb_address' )
				)
		);

		foreach ( $res as $row ) {
			if ( $row->user_id ) {
				$user = User::newFromRow( $row );
				$this->metadata[$row->page_id]['user_id'] = $user->getId();
				$this->metadata[$row->page_id]['user_name'] = $user->getName();
				$this->metadata[$row->page_id]['user_editcount'] = $user->getEditCount();
				$this->metadata[$row->page_id]['user_creation_date'] = wfTimestamp( TS_MW, $user->getRegistration() );
				$this->metadata[$row->page_id]['user_autoconfirmed'] = $user->isAllowed( 'autoconfirmed' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_bot'] = $user->isAllowed( 'bot' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';
			} else {
				$this->metadata[$row->page_id]['user_id'] = 0;
				$this->metadata[$row->page_id]['user_name'] = $row->rev_user_text;
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

	public function __construct( $pageId ) {
		$this->mPageId = $pageId;
		$this->metadata = array_fill_keys( $this->mPageId, array( 'deleted' => '0' ) );
	}

	public static function getDeletionTags() {
		return array (
			'All_articles_proposed_for_deletion' => 'prod_status',
			'BLP_articles_proposed_for_deletion' => 'blp_prod_status',
			'Candidates_for_speedy_deletion' => 'csd_status',
			'Articles_for_deletion' => 'afd_status'
		);
	}

	public function compile() {
		$dbr = wfGetDB( DB_SLAVE );

		$deletionTags = self::getDeletionTags();

		$res = $dbr->select(
				array( 'categorylinks' ),
				array( 'cl_from AS page_id', 'cl_to' ),
				array( 'cl_from' => $this->mPageId, 'cl_to' => array_keys( $deletionTags ) ),
				__METHOD__
		);

		foreach ( $res as $row ) {
			$this->metadata[$row->page_id][$deletionTags[$row->cl_to]] = '1';
			// This won't be saved to metadata, only for later reference
			$this->metadata[$row->page_id]['deleted'] = '1';
		}

		// Fill in 0 for page not tagged with any of these status
		foreach ( $this->mPageId as $pageId ) {
			foreach ( $deletionTags as $status ) {
				if ( !isset( $this->metadata[$pageId][$status] ) ) {
					$this->metadata[$pageId][$status] = '0';	
				}
			}
		}

		return true;
	}

}