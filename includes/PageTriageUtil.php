<?php

/**
 * Utility class for PageTriage
 */
class PageTriageUtil {

	/**
	 * Get whether or not a page needs triaging
	 *
	 * @param $article Article object
	 * 
	 * @return Mixed null if the page is not in the triage system,
	 * otherwise whether or not the page is untriaged.
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

		$row = $dbr->selectRow( 'pagetriage_page', 'ptrp_triaged',
			array( 'ptrp_page_id' => $article->getID() )
		);

		if ( ! $row ) {
			return null;
		}

		return !(boolean)$row->ptrp_triaged;
	}

	/**
	 * Get a list of stat for untriaged articles
	 * @return array
	 *
	 * @Todo - Limit the number of records by a timestamp filter, maybe 30 days etc,
	 *         depends on the time the triage queue should look back for listview
	 */
	public static function getUntriagedArticleStat() {
		global $wgMemc;

		$key = wfMemcKey( 'pagetriage', 'untriaged-article', 'stat' );
	
		$data = $wgMemc->get( $key );
		if ( $data !== false ) {
			return $data;
		}

		$dbr = wfGetDB( DB_SLAVE );
		
		$res = $dbr->selectRow( 
			array( 'pagetriage_page' ),
			array( 'COUNT(ptrp_page_id) AS total' ),
			array( 'ptrp_triaged' => 0 )
		);

		$percentile = array( 25, 50, 75, 90, 100 );

		$data = array( 'count' => 0 );

		foreach ( $percentile as $val ) {
			$data['age-' . $val . 'th-percentile'] = false;
		}

		if ( $res ) {
			$data['count'] = intval( $res->total );

			// show percentile stat only if there is a certain number of untriaged articles
			if ( $data['count'] > 10 ) {
				foreach ( $percentile as $val ) {
					$data['age-' . $val . 'th-percentile'] = self::estimateArticleAgePercentile( $val, $data['count'] );
				}
			}
		}

		// make it expire in an hour
		$wgMemc->set( $key, $data, 3600 );
		return $data;
	}

	/**
	 * Get top page triagers in various time frame
	 * @param $time string - time to look back for top triagers, possible values include
	 *                       last-day, last-week, last-month, last-year
	 * @return array
	 */
	public static function getTopTriager( $time = 'last-day' ) {
		global $wgMemc;

		$now = wfTimestamp( TS_UNIX );

		// times to look back for top trigers and expiration time in cache
		$timeFrame = array( 
				'last-day' => array( 'ts' => $now - 24 * 60 * 60, 'expire' => 60 * 60 ), 
				'last-week' => array( 'ts' => $now - 7 * 24 * 60 * 60, 'expire' =>  24 * 60 * 60 ),
				//Todo: Do we really want to include big timeframe?
				'last-month' => array( 'ts' => $now - 30 * 24 * 60 * 60, 'expire' => 7 * 24 * 60 * 60 ),
				'last-year'=> array( 'ts' => $now - 365 * 24 * 60 * 60, 'expire' => 30 * 24 * 60 * 60 )
		);

		if ( !isset( $timeFrame[$time] ) ) {
			$time = 'last-day';
		}

		$dbr = wfGetDB( DB_SLAVE );
		$key = wfMemcKey( 'pagetriage', 'top-triager', $time );

		$topTriager = $wgMemc->get( $key );
		if ( $topTriager === false ) {
			$res = $dbr->select(
				array( 'pagetriage_log', 'user' ),
				array( 'user_name', 'COUNT(ptrl_id) AS num' ),
				array( 'user_id = ptrl_user_id', 'ptrl_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $timeFrame[$time]['ts'] ) ) ),
				__METHOD__,
				array( 'GROUP BY' => 'user_id', 'ORDER BY' => 'num DESC', 'LIMIT' => 50 )
			);
			
			$topTriager = iterator_to_array( $res );

			$wgMemc->set( $key, $topTriager, $timeFrame[$time]['expire'] );
		}

		return $topTriager;
	}
	
	/**
	 * Get the number of triaged articles in last week
	 * @return int
	 */
	public static function getTriagedArticleNum() {
		global $wgMemc;

		$dbr = wfGetDB( DB_SLAVE );
		$key = wfMemcKey( 'pagetriage', 'triaged-article', 'num' );

		$triagedArticleNum = $wgMemc->get( $key );

		if ( $triagedArticleNum !== false) {
			return $triagedArticleNum;
		}

		$res = $dbr->selectRow(
			array( 'pagetriage_page' ),
			array( 'COUNT(ptrp_page_id) AS num' ),
			array( 'ptrp_triaged = 1', 'ptrp_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( wfTimestamp( TS_UNIX ) - 7 * 24 * 60 * 60 ) ) ),
			__METHOD__
		);

		if ( $res ) {
			$triagedArticleNum = $res->num;
		} else {
			$triagedArticleNum = 0;	
		}

		$wgMemc->set( $key, $triagedArticleNum, 6 * 60 * 60 );

		return $triagedArticleNum;
	}
	
	/**
	 * Calculate the age of untriaged articles by percentile
	 * @param $percentile int
	 * @param $count int
	 * @return int|bool
	 */
	private static function estimateArticleAgePercentile( $percentile, $count ) {

		if ( !is_int( $percentile ) || $percentile < 1 || $percentile > 100) {
			throw new MWPageTriageUtilInvalidNumberException( 'Invalid percentage number' );
		}
		
		if ( !is_int( $count ) || $count < 1 ) {
			throw new MWPageTriageUtilInvalidNumberException ( 'Invalid total count' );
		}

		// starting from oldest timestamp if percent is > 50
		if ( $percentile > 50 ) {
			$percentile = 100 - $percentile;
			$order = 'ASC';
		} else {
			$order = 'DESC';
		}
		
		$start = floor( ( $percentile / 100 ) * $count ) - 1;
		
		if ( $start < 0 ) {
			$start = 0;
		}

		$dbr = wfGetDB( DB_SLAVE );
		
		$res = $dbr->selectRow(
			array( 'pagetriage_page' ),
			array( 'ptrp_timestamp' ),
			array( 'ptrp_triaged' => 0 ),
			__METHOD__,
			array( 'ORDER BY' => "ptrp_timestamp $order", 'LIMIT' => '1', 'OFFSET' => $start )
		);

		if ( $res ) {
			return $res->ptrp_timestamp;
		} else {
			return false;
		}
	}

}

class MWPageTriageUtilInvalidNumberException extends MWException {}
