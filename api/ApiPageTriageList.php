<?php
/**
 * API module to generate a list of pages to triage
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiPageTriageList extends ApiBase {

	// Holds the various options for filtering the list
	protected $opts;

	public function execute() {
		
		// Get the API parameters and store them
		$this->opts = $this->extractRequestParams();
		
		// Retrieve the list of page IDs
		$pages = $this->getPageIds();
		$pages = implode( ', ', $pages );
		
		// Output the results
		$result = array( 'result' => 'success', 'pages' => $pages );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}
	
	/**
	 * Return all the page ids in PageTraige matching the specified filters
	 * @return an array of ids
	 */
	protected function getPageIds() {
	
		// Initialize required variables
		$pages = array();
		$conds = array();
		$options = array();
		
		// Database setup
		$dbr = wfGetDB( DB_SLAVE );
		
		// If a limit was specified, limit the results to that number
		if ( $this->opts['limit'] ) {
			$options = array( 'LIMIT' => $this->opts['limit'] );
		}
		
		// TODO: Handle filtering options
		
		// Pull page IDs from database
		$res = $dbr->select(
			'pagetriage_page',
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
