<?php

/**
 * Utility class for PageTriage
 */
class PageTriageUtil {

	/**
	 * Get whether or not a page needs triaging
	 *
	 * @param $article WikiPage object
	 *
	 * @throws MWException
	 * @return Mixed null if the page is not in the triage system,
	 * otherwise whether or not the page is unreviewed.
	 * Return convention is this way so that null and false are equivalent
	 * with a straight boolean test.
	 */
	public static function doesPageNeedTriage( $article ) {
		if ( ! $article ) {
			throw new MWException( "Invalid argument to " . __METHOD__ );
		}

		if ( ! $article->getId() ) {
			// article doesn't exist.  this happens a lot.
			return null;
		}

		$dbr = wfGetDB( DB_SLAVE );

		$row = $dbr->selectRow( 'pagetriage_page', 'ptrp_reviewed',
			array( 'ptrp_page_id' => $article->getID() )
		);

		if ( ! $row ) {
			return null;
		}

		return !(boolean)$row->ptrp_reviewed;
	}

	/**
	 * Validate page namespace
	 */
	private static function validatePageNamespace( $namespace ) {
		global $wgPageTriageNamespaces;

		if ( !in_array( $namespace, $wgPageTriageNamespaces ) ) {
			$namespace = NS_MAIN;
		}

		return $namespace;
	}

	/**
	 * Get a list of stat for unreviewed articles
	 * @param $namespace int
	 * @return array
	 *
	 * @Todo - Limit the number of records by a timestamp filter, maybe 30 days etc,
	 *         depends on the time the triage queue should look back for listview
	 */
	public static function getUnreviewedArticleStat( $namespace = '' ) {
		global $wgMemc;

		$namespace = self::validatePageNamespace( $namespace );

		$key = wfMemcKey( 'pagetriage', 'unreviewed-article-' . $namespace, 'stat', self::getCacheVersion() );

		$data = $wgMemc->get( $key );
		if ( $data !== false ) {
			return $data;
		}

		$dbr = wfGetDB( DB_SLAVE );

		$table = array( 'pagetriage_page', 'page' );
		$conds = array(
			'ptrp_reviewed' => 0,
			'page_id = ptrp_page_id',
			'page_is_redirect' => 0, // remove redirect from the unreviewd number per bug40540
			'page_namespace' => $namespace
		);

		$res = $dbr->selectRow(
			$table,
			array( 'COUNT(ptrp_page_id) AS total', 'MIN(ptrp_created) AS oldest' ),
			$conds
		);

		$data = array( 'count' => 0, 'oldest' => '' );

		if ( $res ) {
			$data['count'] = intval( $res->total );
			$data['oldest'] = $res->oldest;
		}

		// make it expire in 10 minutes
		$wgMemc->set( $key, $data, 600 );
		return $data;
	}

	/**
	 * Get the number of pages based on the selected filter, it's fine to do this since we have
	 * a cron to remove page more than 30 days old from the queue, the queue volume is not big,
	 * we will change this accordingly if potential blocking problems are spotted
	 */
	public static function getArticleFilterStat( $filter, $namespace = '' ) {
		global $wgMemc;

		if ( !isset( $filter['showreviewed'] ) && !isset( $filter['showunreviewed'] ) ) {
			$filter['showunreviewed'] = 'showunreviewed';
		}

		$namespace = self::validatePageNamespace( $namespace );

		$key = wfMemcKey( 'pagetriage', 'filter-article-' . implode( '-', $filter ) . '-' . $namespace, 'stat', self::getCacheVersion() );

		$data = $wgMemc->get( $key );
		if ( $data !== false ) {
			return $data;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$table = array( 'pagetriage_page', 'page' );
		$conds = array(
			'page_id = ptrp_page_id',
			'page_namespace' => $namespace
		);

		$ops = '';
		if ( isset( $filter['showreviewed'] ) ) {
			$ops .= '>';
		}
		if ( isset( $filter['showunreviewed'] ) ) {
			$ops .= '=';
		}

		$conds[] = 'ptrp_reviewed ' . $ops . ' 0';

		if ( !isset( $filter['showdeleted'] ) ) {
			$conds['ptrp_deleted'] = 0;
		}
		if ( !isset( $filter['showredirs'] ) ) {
			$conds['page_is_redirect'] = 0;
		}

		$res = $dbr->selectRow(
			$table,
			array( 'COUNT(ptrp_page_id) AS total' ),
			$conds
		);

		$total = 0;
		if ( $res ) {
			$total = intval( $res->total );
		}

		// make it expire in 10 minutes
		$wgMemc->set( $key, $total, 600 );
		return $total;
	}

	public static function getReviewedArticleStat( $namespace = '' ) {
		global $wgMemc;

		$namespace = self::validatePageNamespace( $namespace );

		$key = wfMemcKey( 'pagetriage', 'reviewed-article-' . $namespace, 'stat', self::getCacheVersion() );

		$data = $wgMemc->get( $key );
		if ( $data !== false ) {
			return $data;
		}

		$time = wfTimestamp( TS_UNIX ) - 7 * 24 * 60 * 60;

		$dbr = wfGetDB( DB_SLAVE );

		$table = array( 'pagetriage_page', 'page' );
		$conds = array(
			'ptrp_reviewed' => 1,
			'page_id = ptrp_page_id',
			'page_namespace' => $namespace,
			'ptrp_reviewed_updated > ' . $dbr->addQuotes( $dbr->timestamp( $time ) )
		);

		$res = $dbr->selectRow(
			$table,
			array( 'COUNT(ptrp_page_id) AS reviewed_count' ),
			$conds
		);

		$data = array( 'reviewed_count' => 0 );

		if ( $res ) {
			$data['reviewed_count'] = intval( $res->reviewed_count );
		}

		// make it expire in 10 minutes
		$wgMemc->set( $key, $data, 600 );
		return $data;
	}

	/**
	 * Get top page triagers in various time frame
	 * @param $time string - time to look back for top triagers, possible values include
	 *                       last-day, last-week
	 * @return array
	 */
	public static function getTopTriager( $time = 'last-week' ) {
		global $wgMemc;

		$now = wfTimestamp( TS_UNIX );

		// times to look back for top trigers and expiration time in cache
		$timeFrame = array(
				'last-day' => array( 'ts' => $now - 24 * 60 * 60, 'expire' => 60 * 60 ),
				'last-week' => array( 'ts' => $now - 7 * 24 * 60 * 60, 'expire' =>  24 * 60 * 60 )
		);

		if ( !isset( $timeFrame[$time] ) ) {
			$time = 'last-day';
		}

		$dbr = wfGetDB( DB_SLAVE );
		$key = wfMemcKey( 'pagetriage', 'top-triager', $time, self::getCacheVersion() );

		$topTriager = $wgMemc->get( $key );
		if ( $topTriager === false ) {
			$res = $dbr->select(
				array( 'pagetriage_log', 'user' ),
				array( 'user_name', 'user_id', 'COUNT(ptrl_id) AS num' ),
				array(
					'user_id = ptrl_user_id',
					'ptrl_reviewed' => 1, // only reviewed status
					'ptrl_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $timeFrame[$time]['ts'] ) )
				),
				__METHOD__,
				array( 'GROUP BY' => 'user_id', 'ORDER BY' => 'num DESC', 'LIMIT' => 50 )
			);

			$topTriager = iterator_to_array( $res );

			$wgMemc->set( $key, $topTriager, $timeFrame[$time]['expire'] );
		}

		return $topTriager;
	}

	/**
	 * returns the cahce key for user status
	 * @param $userName string
	 * @return string
	 */
	public static function userStatusKey( $userName ) {
		return wfMemcKey( 'pagetriage', 'user-page-status', $userName, self::getCacheVersion() );
	}

	/**
	 * Check the existance of user page and talk page for a list of users
	 * @param $users array - contains user_name db keys
	 * @return array
	 */
	public static function pageStatusForUser( $users ) {
		global $wgMemc;

		$return = array();
		$title  = array();

		foreach ( $users as $user ) {
			$user = (array) $user;
			$searchKey = array( 'user_name', 'reviewer' );

			foreach ( $searchKey as $val ) {
				if ( !isset( $user[$val] ) || !$user[$val] ) {
					continue;
				}
				$data = $wgMemc->get( self::userStatusKey( $user[$val] ) );
				// data is in memcache
				if ( $data !== false ) {
					foreach ( $data as $pageKey => $status ) {
						if ( $status === 1 ) {
							$return[$pageKey] = $status;
						}
					}
				// data is not in memcache and will be checked against database
				} else {
					$u = Title::newFromText( $user[$val], NS_USER );
					if ( $u ) {
						if ( isset( $title[$u->getDBkey()] ) ) {
							continue;
						}
						$t = Title::makeTitle( NS_USER_TALK, $u->getDBkey() );
						// store the data in $title, 'u' is for user page, 't' is for talk page
						$title[$u->getDBkey()] = array( 'user_name' => $user[$val], 'u' => $u, 't' => $t );
					}
				}
			}
		}

		if ( $title ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				array( 'page' ),
				array( 'page_namespace', 'page_title' ),
				array( 'page_title' => array_keys( $title ), 'page_namespace' => array( NS_USER, NS_USER_TALK ) ),
				__METHOD__
			);

			$dataToCache = array();
			// if there is result from the database, that means the page exists, set it to the cache array with value 1
			foreach ( $res as $row ) {
				$user = $title[$row->page_title];
				if ( $row->page_namespace == NS_USER ) {
					$dataToCache[$user['user_name']][$user['u']->getPrefixedDBkey()] = 1;
				} else {
					$dataToCache[$user['user_name']][$user['t']->getPrefixedDBkey()] = 1;
				}
			}
			// Loop through the original $title array, set the data not in db result with value 0
			// then save the cache value to memcache for next time use
			foreach ( $title as $key => $value ) {
				if ( !isset( $dataToCache[$value['user_name']][$value['u']->getPrefixedDBkey()] ) ) {
					$dataToCache[$value['user_name']][$value['u']->getPrefixedDBkey()] = 0;
				} else {
					$return[$value['u']->getPrefixedDBkey()] = 1;
				}
				if ( !isset( $dataToCache[$value['user_name']][$value['t']->getPrefixedDBkey()] ) ) {
					$dataToCache[$value['user_name']][$value['t']->getPrefixedDBkey()] = 0;
				} else {
					$return[$value['t']->getPrefixedDBkey()] = 1;
				}
				$wgMemc->set( self::userStatusKey( $value['user_name'] ), $dataToCache[$value['user_name']], 3600 );
			}
		}

		return $return;
	}

	/**
	 * Update user metadata when a user's block status is updated
	 * @param $block Block - Block object
	 * @param $status int 1/0
	 */
	public static function updateMetadataOnBlockChange( $block, $status = 1 ) {
		// do instant update if the number of page to be updated is less or equal to
		// the number below, otherwise, delay this to the cron
		$maxNumToProcess = 500;

		$tags = ArticleMetadata::getValidTags();
		if ( !$tags ) {
			return;
		}

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			array( 'pagetriage_page_tags' ),
			array( 'ptrpt_page_id' ),
			array( 'ptrpt_tag_id' => $tags['user_name'], 'ptrpt_value' => (string)$block->getTarget() ),
			__METHOD__,
			array( 'LIMIT' => $maxNumToProcess + 1 )
		);

		if ( $dbr->numRows( $res ) > $maxNumToProcess ) {
			return;
		}

		$pageIds = array();
		foreach ( $res as $row ) {
			$pageIds[] = $row->ptrpt_page_id;
		}

		if ( !$pageIds ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->update(
			'pagetriage_page_tags',
			array( 'ptrpt_value' => $status ),
			array( 'ptrpt_page_id' => $pageIds, 'ptrpt_tag_id' => $tags['user_block_status'] )
		);
		PageTriage::bulkSetTagsUpdated( $pageIds );
		$dbw->commit();

		$metadata = new ArticleMetadata( $pageIds );
		$metadata->updateMetadataInCache( array( 'user_block_status' => $status ) );
	}

	private function getCacheVersion() {
		global $wgPageTriageCacheVersion;
		return $wgPageTriageCacheVersion;
	}

	/**
	 * Attempt to create an Echo notification event for
	 * 1. 'Mark as Reviewed' curation flyout
	 * 2. 'Mark as Patrolled' from Special:NewPages
	 * 3. 'Add maintenance tag' curation flyout
	 * 4. 'Add deletion tag' curation flyout
	 *
	 * @param $article Article
	 * @param $user User
	 * @param $type string notification type
	 * @param $extra array/null
	 */
	public static function createNotificationEvent( $article, $user, $type, $extra = null ) {
		if ( !class_exists( 'EchoEvent' ) ) {
			return;
		}

		$params = array(
			'type' => $type,
			'title' => $article->getTitle(),
			'agent' => $user,
		);

		if ( $extra ) {
			$params['extra'] = $extra;
		}

		EchoEvent::create( $params );
	}

}

class MWPageTriageUtilInvalidNumberException extends MWException {}
