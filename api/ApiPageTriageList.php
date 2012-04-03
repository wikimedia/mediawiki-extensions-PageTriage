<?php
/**
 * API module to generate a list of pages to triage
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiPageTriageList extends ApiBase {

	public function execute() {
		// Get the API parameters and store them
		$opts = $this->extractRequestParams();

		// Retrieve the list of page IDs
		$pages = $this->getPageIds( $opts );

		$metaDataSend = array();

		if ( $pages ) {
			// fetch metadata for those pages
			$articleMetadata = new ArticleMetadata( $pages );
			$metaData = $articleMetadata->getMetadata();

			// convert this to a slightly different format that's more Backbone-friendly
			foreach( $metaData as $pageId => $attrs ) {
				$metaDataSend[] = $attrs + array( 'pageid' => $pageId );
			}
		}

		// Output the results
		$result = array( 'result' => 'success', 'pages' => $metaDataSend );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Return all the page ids in PageTraige matching the specified filters
	 * @param $opts array of filtering options
	 * @return an array of ids
	 *
	 * @Todo - enforce a range of timestamp to reduce tag record scan
	 */
	public static function getPageIds( $opts = array() ) {
		// Initialize required variables
		$pages = $options = array();

		// Get the expected limit as defined in getAllowedParams
		$options = array( 'LIMIT' => $opts['limit'] );

		// Start building the massive filter which includes meta data
		$tagConds = self::buildTagQuery( $opts );
		$tables	  = array( 'pagetriage_page', 'page' );
		$conds    = array( 'ptrp_page_id = page_id' );

		// Show triaged
		if ( $opts['showtriaged'] ) {
			$conds['ptrp_triaged'] = array( 0, 1 );
		} else {
			$conds['ptrp_triaged'] = 0;
		}
		// Show redirect
		if ( $opts['showredirs'] ) {
			$conds['page_is_redirect'] = 1;
		}
		// Show by namespace
		if ( array_key_exists( 'namespace', $opts ) ) {
			$conds['page_namespace'] = $opts['namespace'];
		}

		if ( $tagConds ) {
			$conds[] = '(' . implode( ' OR ', $tagConds ) . ') AND ptrpt_page_id = ptrp_page_id';
			$options['GROUP BY'] = 'ptrpt_page_id';
			$options['HAVING'] = 'COUNT(ptrpt_tag_id) = ' . count( $tagConds );
			$tables[] = 'pagetriage_page_tags';
		}

		// Database setup
		$dbr = wfGetDB( DB_SLAVE );

		// Pull page IDs from database
		$res = $dbr->select(
			$tables,
			'ptrp_page_id',
			$conds,
			__METHOD__,
			$options
		);

		// Loop through result set and return ids
		foreach ( $res as $row ) {
			$pages[] = $row->ptrp_page_id;
		}

		return $pages;
	}
	
	private static function buildTagQuery( $opts ) {
		$tagConds = array();

		$searchableTags = array( 
					// no categories assigned
					'no_category' => array( 'name' => 'category_count', 'op' => '=', 'val' => '0' ),
					// no inbound links
					'no_inbound_links' => array( 'name' => 'linkcount', 'op' => '=', 'val' => '0' ),
					// non auto confirmed users
					'non_auto_confirmed_users' => array( 'name' => 'user_autoconfirmed', 'op' => '=', 'val' => '0' ),
					// blocked users
					'blocked_users' => array( 'name' => 'user_block_status', 'op' => '=', 'val' => '1' ),
					// show bots
					'showbots' => array( 'name' => 'user_bot', 'op' => '=', 'val' => '1' )
				);

		$tags = ArticleMetadata::getValidTags();

		foreach ( $searchableTags as $key => $val ) {
			if ( $opts[$key] ) {
				$tagConds[] = " ( ptrpt_tag_id = '" . $tags[$val['name']] . "' AND ptrpt_value " . $val['op'] . " " . $val['val'] . " ) ";
			}
		}

		return $tagConds;
	}
	
	public function getAllowedParams() {
		return array(
			'showbots' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showredirs' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showtriaged'=> array(
				ApiBase::PARAM_TYPE => 'boolean',	
			),
			'limit' => array(
				ApiBase::PARAM_MAX => '50',
				ApiBase::PARAM_DFLT => '20',
				ApiBase::PARAM_MIN => '10',
				ApiBase::PARAM_TYPE => 'integer',
			),
			'namespace' => array(
				ApiBase::PARAM_DFLT => '0',
				ApiBase::PARAM_TYPE => 'integer',
			),
			'no_category' => array(
				ApiBase::PARAM_TYPE => 'boolean',	
			),
			'no_inbound_links' => array(
				ApiBase::PARAM_TYPE => 'boolean',	
			),
			'non_auto_confirmed_users' => array(
				ApiBase::PARAM_TYPE => 'boolean',	
			),
			'blocked_users' => array(
				ApiBase::PARAM_TYPE => 'boolean',	
			),
			
		);
	}

	public function getParamDescription() {
		return array(
			'showbots' => 'Whether to include bot edits or not', // default is not to show bot
			'showredirs' => 'Whether to include redirects or not', // default is not to show redirects
			'showtriaged' => 'Whether to include triaged or not', // default is not to show triaged
			'limit' => 'The maximum number of results to return',
			'namespace' => 'What namespace to pull pages from',
			'no_category' => 'Whether to show only pages with no category',
			'no_inbound_links' => 'Whether to show only pages with no inbound links',
			'non_auto_confirmed_users' => 'Whether to show only pages created by non auto confirmed users',
			'blocked_users' => 'Whether to show only pages created by blocked users'
		);
	}

	public function getDescription() {
		return 'Get a list of page IDs for building a PageTriage queue.';
	}

	public function getExamples() {
		return array(
			'api.php?action=pagetriagelist&limit=1000&namespace=0',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiPageTriageList.php $';
	}
}
