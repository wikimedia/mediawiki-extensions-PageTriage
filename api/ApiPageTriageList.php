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
		
		// fetch metadata for those pages
		$articleMetadata = new ArticleMetadata( $pages );
		$metaData = $articleMetadata->getMetadata();
		
		// convert this to a slightly different format that's more Backbone-friendly
		$metaDataSend = array();
		foreach( $metaData as $pageId => $attrs ) {
			$metaDataSend[] = $attrs + array( 'pageid' => $pageId );
		}
		
		// Output the results
		$result = array( 'result' => 'success', 'pages' => $metaDataSend );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}
	
	/**
	 * Return all the page ids in PageTraige matching the specified filters
	 * @param $opts array of filtering options
	 * @return an array of ids
	 */
	public static function getPageIds( $opts = array() ) {

		// Initialize required variables
		$pages = array();
		$conds = array();
		$options = array();
		
		// Database setup
		$dbr = wfGetDB( DB_SLAVE );
		
		// If a limit was specified, limit the results to that number
		if ( isset( $opts['limit'] ) && is_numeric( $opts['limit'] ) && $opts['limit'] > 0 ) {
			$options = array( 'LIMIT' => $opts['limit'] );
		}
		
		// TODO: Handle filtering options
		$tables = array( 'pagetriage_page', 'page' );
		$conds[] = 'ptrp_page_id = page_id';

		if ( $opts['namespace'] ) {
			$conds['page_namespace'] = $opts['namespace'];
		}
		if ( $opts['showredirs'] ) {
			$conds['page_is_redirect'] = 1;
		}
		if ( $opts['showbots'] ) {
			$conds[] = 'ptrp_page_id = ptrpt_page_id AND ptrpt_tag_id = ptrt_tag_id';
			$conds['ptrt_tag_name'] = 'user_bot';
			$conds['ptrpt_value'] = '1';
			$tables[] = 'pagetriage_page_tags';
			$tables[] = 'pagetriage_tags';
		}
		
		if ( $opts['showtriaged'] ) {
			$conds['ptrp_triaged'] = array( 0, 1 );
		} else {
			$conds['ptrp_triaged'] = 0;
		}
		
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
				ApiBase::PARAM_DFLT => '5000',
				ApiBase::PARAM_TYPE => 'integer',
			),
			'namespace' => array(
				ApiBase::PARAM_DFLT => '0',
				ApiBase::PARAM_TYPE => 'integer',
			),
		);
	}

	public function getParamDescription() {
		return array(
			'showbots' => 'Whether to include bot edits or not',
			'showredirs' => 'Whether to include redirects or not',
			'showtriaged' => 'Whether to include triaged or not',
			'limit' => 'The maximum number of results to return',
			'namespace' => 'What namespace to pull pages from',
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
