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
		if ( ! $article || ! $article->getId() ) {
			throw new MWException( "Invalid argument to " . __METHOD__ );
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
	public static function getUntriageArticleStat() {
		global $wgMemc;
		
		$key = wfMemcKey( 'pagetriage', 'untriaged-article', 'stat' );
	
		$data = $wgMemc->get( $key );
		if ( $data !== false ) {
			return $data;
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$res = $dbr->selectRow( 
			array( 'pagetriage_page' ),
			array( 'COUNT(ptrp_id) AS total' ),
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
	 * Get top page triagers in the past week
	 * @param $num int - number of records to retrieve
	 * @return array
	 */
	public static function getTopTriager( $num = 5 ) {
		global $wgMemc;

		$dbr = wfGetDB( DB_SLAVE );
		$key = wfMemcKey( 'pagetriage', 'top-triager', 'past-week' );

		$topTriager = $wgMemc->get( $key );
		if ( $topTriager === false ) {
			$timestamp = wfTimestamp( TS_UNIX ) - 7 * 24 * 60 * 60; // 1 week ago

			$res = $dbr->select(
				array( 'pagetriage_log', 'user' ),
				array( 'user_name', 'COUNT(ptrl_id) AS num' ),
				array( 'user_id = ptrl_user_id', 'ptrl_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $timestamp ) ) ),
				__METHOD__,
				array( 'GROUP BY' => 'user_id', 'ORDER BY' => 'num DESC', 'LIMIT' => $num )
			);
			
			$topTriager = iterator_to_array( $res );

			// make it expire in 2 hours
			$wgMemc->set( $key, $topTriager, 7200 );
		}

		return $topTriager;
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
