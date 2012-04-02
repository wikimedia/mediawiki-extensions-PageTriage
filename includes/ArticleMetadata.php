<?php

class ArticleMetadata {

	protected $mPageId;

	/**
	 * @param $pageId array - list of page id
	 */
	public function __construct( $pageId = array() ) {
		if ( !$pageId ) {
			throw new MWArticleMetadataMissingPageIdException( 'Missing page id' );	
		}

		$this->mPageId = $pageId;
	}

	/**
	 * Compile the metadata for an article, should be triggered on article save
	 * @return array|bool
	 */
	public function compileMetadata( ) {
		$metaData = array();

		//Start the data compilation
		if ( $this->compileArticleBasicData( $metaData ) ) {
			$this->compileUserBasicData( $metaData );
			$this->compileDeletionTagData( $metaData );

			$tags = self::getValidTags();
			$dbw  = wfGetDB( DB_MASTER );
			foreach ( $metaData as $pageId => $data ) {
				$this->setMetadataToCache( $pageId, $data );
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
				$dbw->commit();
			}
		}

		return $metaData;
	}

	/**
	 * Delete all the metadata for an article
	 *
	 * @param $pageId - the page id to be deleted
	 */
	public function deleteMetadata( $pageId = null ) {
		if( is_null($pageId) ) {
			$pageId = $this->mPageId;
		}

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
		
		return true;
	}

	/**
	 * Flush the metadata in cache
	 * @param $pageId - page id to be flushed, if null is provided, all
	 *                  page id in $this->mPageId will be flushed
	 */
	protected function flushMetadataFromCache( $pageId = null ) {
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
	protected function setMetadataToCache( $pageId, $singleData ) {
		global $wgMemc;

		$this->flushMetadataFromCache( $pageId );
		$wgMemc->set(  $this->memcKeyPrefix() . '-' . $pageId, $singleData );
	}

	/**
	 * Get the metadata from cache
	 * @param $pageId - the page id to get the cache data for, if null is provided
	 *                  all page id in $this->mPageId will be obtained
	 */
	protected function getMetadataFromCache( $pageId = null ) {
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
			return $wgMemc->get( $keyPrefix . '-' . $pageId );;	
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

		// Articles with no metadata after cache attempt
		if ( $articles ) {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
					array( 'pagetriage_page_tags', 'pagetriage_tags' ),
					array( 'ptrpt_page_id', 'ptrt_tag_name', 'ptrpt_value' ),
					array( 'ptrpt_page_id' => $articles, 'ptrpt_tag_id = ptrt_tag_id' ),
					__METHOD__
			);

			$pageData = array();
			foreach ( $res as $row ) {
				$pageData[$row->ptrpt_page_id][$row->ptrt_tag_name] = $row->ptrpt_value;
			}

			foreach ( $pageData as $pageId => $val ) {
				$this->setMetadataToCache( $pageId, $val );
			}
			$metaData += $pageData;

			// Double check articles with no metadata yet, maybe we do not want to do this on the fly since 
			// compiling page especially multipla pages at the same request is quite expensive
			// @todo discuss this
			foreach ( $articles as $key => $pageId ) {
				if ( isset( $metaData[$pageId] ) ) {
					unset( $articles[$key] );
				}
			}
			if ( $articles ) {
				$self = new ArticleMetadata( $articles );
				$metaData += $self->compileMetadata( $articles );
			}
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
	 * Compile article basic data like title, number of bytes
	 * @param $metaData array
	 */
	protected function compileArticleBasicData( &$metaData ) {
		$dbr = wfGetDB( DB_SLAVE );

		// Article page length, creation date, number of edit, title, article triage status
		$res = $dbr->select(
				array( 'page', 'revision', 'pagetriage_page' ),
				array( 'page_id', 'page_namespace', 'page_title', 'page_len', 'COUNT(rev_id) AS rev_count', 'MIN(rev_timestamp) AS creation_date', 'ptrp_triaged' ),
				array( 'page_id' => $this->mPageId, 'page_id = rev_page', 'page_id = ptrp_page_id'),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			$metaData[$row->page_id]['page_len'] = $row->page_len;
			$metaData[$row->page_id]['creation_date'] = $row->creation_date;
			$metaData[$row->page_id]['rev_count'] = $row->rev_count;
			$metaData[$row->page_id]['title'] = $title->getPrefixedText();
			$metaData[$row->page_id]['patrol_status'] = $row->ptrp_triaged;
		}
		// Remove any non-existing page_id from $this->mPageId
		foreach ( $this->mPageId as $key => $pageId ) {
			if ( !isset( $metaData[$pageId] ) ) {
				unset($this->mPageId[$key]);
			}
		}
		if ( !$this->mPageId ) {
			return false;
		}

		// Article link count
		$res = $dbr->select(
				array( 'page', 'pagelinks' ),
				array( 'page_id', 'COUNT(pl_from) AS linkcount' ),
				array( 'page_id' => $this->mPageId, 'page_namespace = pl_namespace', 'page_title = pl_title' ),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$metaData[$row->page_id]['linkcount'] = $row->linkcount;
		}
		foreach ( $this->mPageId as $pageId ) {
			if ( !isset( $metaData[$pageId]['linkcount'] ) ) {
				$metaData[$pageId]['linkcount'] = '0';	
			}
		}

		// Article category count
		$res = $dbr->select(
				array( 'page', 'categorylinks' ),
				array( 'page_id', 'COUNT(cl_to) AS category_count' ),
				array( 'page_id' => $this->mPageId, 'page_id = cl_from' ),
				__METHOD__,
				array ( 'GROUP BY' => 'page_id' )
		);
		foreach ( $res as $row ) {
			$metaData[$row->page_id]['category_count'] = $row->category_count;
		}
		foreach ( $this->mPageId as $pageId ) {
			if ( !isset( $metaData[$pageId]['category_count'] ) ) {
				$metaData[$pageId]['category_count'] = '0';
			}
		}

		// Article snippet
		$res = $dbr->select(
				array( 'text', 'revision', 'page' ),
				array( 'page_id', 'old_text' ),
				array( 'page_id' => $this->mPageId, 'page_latest = rev_id', 'rev_text_id = old_id' ),
				__METHOD__
		);
		foreach ( $res as $row ) {
			$metaData[$row->page_id]['snippet'] = self::generateArticleSnippet( $row->old_text ); 
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
  	  	// stip out non-useful data for snippet
  	  	$text = str_replace( array('{', '}', '[edit]' ), '', $text );

		return $wgLang->truncate( $text, 150 );
	}

	/**
	 * Compile user basic data like username for the author
	 * @param $metaData array
	 */
	protected function compileUserBasicData( &$metaData ) {
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
				array( 'rev_page AS page_id', 'user_id', 'user_name', 'user_real_name', 'user_registration', 'user_editcount', 'ipb_id', 'rev_user_text' ),
				array( 'rev_id' => $revId ),
				__METHOD__,
				array(),
				array( 'user' => array( 'LEFT JOIN', 'rev_user = user_id' ), 'ipblocks' => array( 'LEFT JOIN', 'rev_user = ipb_user AND rev_user_text = ipb_address' ) )
		);
		
		foreach ( $res as $row ) {
			if ( $row->user_id ) {
				$user = User::newFromRow( $row );
				$metaData[$row->page_id]['user_id'] = $user->getId();
				$metaData[$row->page_id]['user_name'] = $user->getName();
				$metaData[$row->page_id]['user_editcount'] = $user->getEditCount();
				$metaData[$row->page_id]['user_creation_date'] = wfTimestamp( TS_MW, $user->getRegistration() );
				$metaData[$row->page_id]['user_autoconfirmed'] = $user->isAllowed( 'autoconfirmed' ) ? '1' : '0';
				$metaData[$row->page_id]['user_bot'] = $user->isAllowed( 'bot' ) ? '1' : '0';
				$metaData[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';	
			} else {
				$metaData[$row->page_id]['user_id'] = 0;
				$metaData[$row->page_id]['user_name'] = $row->rev_user_text;
				$metaData[$row->page_id]['user_autoconfirmed'] = '0';
				$metaData[$row->page_id]['user_bot'] = '0';
				$metaData[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';		
			}
		}
	}

	/**
	 * Compile the deletion tag data
	 * @param $metaData array
	 */
	protected function compileDeletionTagData( &$metaData ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$deletionTags = array (
			'All_articles_proposed_for_deletion' => 'prod_status',
			'BLP_articles_proposed_for_deletion' => 'blp_prod_status',
			'Candidates_for_speedy_deletion' => 'csd_status',
			'Articles_for_deletion' => 'afd_status'
		);
		
		$res = $dbr->select(
				array( 'categorylinks' ),
				array( 'cl_from AS page_id', 'cl_to' ),
				array( 'cl_from' => $this->mPageId, 'cl_to' => array_keys( $deletionTags ) ),
				__METHOD__
		);

		foreach ( $res as $row ) {
			$metaData[$row->page_id][$deletionTags[$row->cl_to]] = '1';
		}

		// Fill in 0 for page not tagged with any of these status
		// Subtract from category_count
		foreach ( $this->mPageId as $pageId ) {
			foreach ( $deletionTags as $status ) {
				if ( !isset( $metaData[$pageId][$status] ) ) {
					$metaData[$pageId][$status] = '0';	
				} else {
					$metaData[$pageId]['category_count'] -= 1;	
				}
			}
			
			if ( $metaData[$pageId]['category_count'] < 0 ) {
				$metaData[$pageId]['category_count'] = '0';
			}
		}
	}
}

class MWArticleMetadataMissingPageIdException extends MWException {}
class MWArticleMetadataMetaDataOutofBoundException extends MWException {}
