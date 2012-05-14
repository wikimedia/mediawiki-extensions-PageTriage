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
	public function deleteMetadata() {
		if ( $this->mPageId ) {
			$dbw  = wfGetDB( DB_MASTER );
			$dbw->delete(
				'pagetriage_page_tags',
				array( 'ptrpt_page_id' => $this->mPageId ),
				__METHOD__,
				array()
			);
			// also remove it from the cache
			$this->flushMetadataFromCache();
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
		$articles = self::getPageWithoutMetadata( $articles, $metaData );

		// Grab metadata from database after cache attempt
		if ( $articles ) {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
					array(
						'pagetriage_page_tags',
						'pagetriage_tags',
						'pagetriage_page',
						'page'
					),
					array(
						'ptrpt_page_id',
						'ptrt_tag_name',
						'ptrpt_value',
						'ptrp_reviewed',
						'ptrp_created',
						'page_title',
						'page_namespace',
						'page_is_redirect'
					),
					array(
						'ptrpt_page_id' => $articles,
						'ptrpt_tag_id = ptrt_tag_id',
						'ptrpt_page_id = ptrp_page_id',
						'page_id = ptrp_page_id'
					),
					__METHOD__
			);

			$pageData = array();
			foreach ( $res as $row ) {
				$pageData[$row->ptrpt_page_id][$row->ptrt_tag_name] = $row->ptrpt_value;
				if ( !isset( $pageData[$row->ptrpt_page_id]['creation_date'] ) ) {
					$pageData[$row->ptrpt_page_id]['creation_date'] = $row->ptrp_created;
					$pageData[$row->ptrpt_page_id]['patrol_status'] = $row->ptrp_reviewed;
					$pageData[$row->ptrpt_page_id]['is_redirect'] = $row->page_is_redirect;
					$title = Title::makeTitle( $row->page_namespace, $row->page_title );
					if ( $title ) {
						$pageData[$row->ptrpt_page_id]['title'] = $title->getPrefixedText();
					}
				}
			}

			$articles = self::getPageWithoutMetadata( $articles, $pageData );
			// Compile the data if it is not available
			if ( $articles ) {
				$acp = ArticleCompileProcessor::newFromPageId( $articles );
				if ( $acp ) {
					$pageData += $acp->compileMetadata();
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
	private static function getPageWithoutMetadata( $articles, $data ) {
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
					$cache[$casted] = false;
				}
			} else {
				unset( $pageIds[$key] );
			}
		}

		if ( $pageIds ) {
			// this has to read from the master, since page ids that fail to validate
			// don't get metadata compiled
			$dbw = wfGetDB( DB_MASTER );

			$res = $dbw->select(
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
	protected $articles;

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
		$this->articles = array();
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
	 * Cache an up-to-date WikiPage object for later use
	 * @param $article - Article
	 */
	public function registerArticle( WikiPage $article ) {
		if ( in_array( $article->getId(), $this->mPageId ) ) {
			$this->articles[$article->getId()] = $article;
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
				$comp = new $compClass( $this->mPageId, $this->articles );
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
					$dbw->replace( 'pagetriage_page_tags', array( 'ptrpt_page_id', 'ptrpt_tag_id' ), $row, __METHOD__ );
				}
			}
			$pt = new PageTriage( $pageId );
			$row = array( 'ptrp_tags_updated' => $dbw->timestamp( wfTimestampNow() ) );
			if ( isset( $data['deleted'] ) ) {
				$row['ptrp_deleted'] = $data['deleted'] ? '1' : '0';
			}
			$pt->update( $row );
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
	protected $articles;
	protected $dbw;

	/**
	 * @param $pageId array
	 */
	public function __construct( array $pageId, $articles = null ) {
		$this->mPageId = $pageId;
		$this->metadata = array_fill_keys( $pageId, array() );
		$this->articles = $articles;
		$this->dbw = wfGetDB( DB_MASTER );
	}

	public abstract function compile();

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
		$res = $this->dbw->select( $table, '1', $conds, __METHOD__, array( 'LIMIT' => $maxNumToProcess + 1 ) );

		$record = $this->dbw->numRows( $res );
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
}

/**
 * Article page length, creation date, number of edit, title, article triage status
 */
class ArticleCompileBasicData extends ArticleCompileInterface {

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
	}

	public function compile() {
		$count = 0;
		//Process page individually because MIN() GROUP BY is slow
		foreach ( $this->mPageId as $pageId ) {
			$table = array ( 'revision', 'page' );
			$conds = array ( 'rev_page' => $pageId, 'page_id = rev_page' );

			$row = $this->dbw->selectRow( $table, array ( 'MIN(rev_timestamp) AS creation_date' ),
						$conds, __METHOD__ );
			if ( $row ) {
				$this->metadata[$pageId]['creation_date'] = $row->creation_date;
				$this->processEstimatedCount( $pageId, $table, $conds, $maxNumToProcess = 100, 'rev_count' );
				$count++;
			}
		}

		// no record in page table
		if ( $count == 0 ) {
			return false;
		}

		$res = $this->dbw->select(
				array ( 'page', 'pagetriage_page' ),
				array (
					'page_id', 'page_namespace', 'page_title', 'page_len',
					'ptrp_reviewed', 'page_is_redirect'
				),
				array ( 'page_id' => $this->mPageId, 'page_id = ptrp_page_id'),
				__METHOD__
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

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount( 
					$pageId, 
					array( 'page', 'pagelinks' ), 
					array(
						'page_id' => $pageId,
						'page_namespace = pl_namespace',
						'page_title = pl_title'
					), 
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

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount( 
					$pageId, 
					array( 'page', 'categorylinks' ), 
					array( 'page_id' => $pageId, 'page_id = cl_from' ),
					$maxNumToProcess = 50, 
					'category_count'
			);
		}
		$this->fillInZeroCount( 'category_count' );
		return true;
	}

}

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompileInterface {

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			// Article snippet, try if there is an up-to-date wikipage object from article save
			// else try to create a new one, this is important for replication deley
			if ( isset( $this->articles[$pageId] ) ) {
				$article = $this->articles[$pageId];
			} else {
				$article = WikiPage::newFromID( $pageId ); 
			}
			if ( $article ) {
				$content = $article->getText();
				if ( $content ) {
					$this->metadata[$pageId]['snippet'] = self::generateArticleSnippet( $content );
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

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
	}

	public function compile() {
		// Process page individually because MIN() GROUP BY is slow
		$revId = array();
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->dbw->selectRow(
				array( 'revision' ),
				array( 'MIN(rev_id) AS rev_id' ),
				array( 'rev_page' => $pageId ),
				__METHOD__
			);
			if ( $res ) {
				$revId[] = $res->rev_id;
			}
		}

		if ( count( $revId ) == 0 ) {
			return true;
		}

		$res = $this->dbw->select(
				array( 'revision', 'user', 'ipblocks' ),
				array(
					'rev_page AS page_id', 'user_id', 'user_name',
					'user_real_name', 'user_registration', 'user_editcount',
					'ipb_id', 'rev_user_text'
				),
				array( 'rev_id' => $revId ),
				__METHOD__,
				array(),
				array(
					'user' => array( 'LEFT JOIN', 'rev_user = user_id' ),
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

	public function __construct( $pageId, $articles = null ) {
		parent::__construct( $pageId, $articles );
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
		$deletionTags = self::getDeletionTags();
		$res = $this->dbw->select(
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
