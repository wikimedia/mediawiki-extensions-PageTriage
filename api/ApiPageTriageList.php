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
		$pages = null;

		if( $opts['page_id'] ) {
			// page id was specified
			$pages = array( $opts['page_id'] );
			$pageIdValidated = false;
		} else {
			// Retrieve the list of page IDs
			$pages = self::getPageIds( $opts );
			$pageIdValidated = true;
		}
		$pageIdValidateDb = DB_SLAVE;

		$sortedMetaData = array();

		if ( $pages ) {
			// fetch metadata for those pages
			$articleMetadata = new ArticleMetadata( $pages, $pageIdValidated, $pageIdValidateDb );
			$metaData = $articleMetadata->getMetadata();

			$userPageStatus = PageTriageUtil::pageStatusForUser( $metaData );

			// Sort data according to page order returned by our query. Also convert it to a
			// slightly different format that's more Backbone-friendly.
			foreach ( $pages as $page ) {
				if ( isset( $metaData[$page] ) ) {
					$metaData[$page]['creation_date_utc'] = $metaData[$page]['creation_date'];
					$metaData[$page]['creation_date'] = $this->getContext()->getLanguage()->userAdjust( $metaData[$page]['creation_date'] );

					// Page creator
					$metaData[$page] += $this->createUserInfo(
						$metaData[$page]['user_name'],
						$userPageStatus,
						'creator'
					);

					// Page reviewer
					if ( $metaData[$page]['reviewer'] ) {
						$metaData[$page] += $this->createUserInfo(
							$metaData[$page]['reviewer'],
							$userPageStatus,
							'reviewer'
						);
					}

					$metaData[$page][ApiResult::META_BC_BOOLS] = array(
						'creator_user_page_exist', 'creator_user_talk_page_exist',
						'reviewer_user_page_exist', 'reviewer_user_talk_page_exist',
					);

					$sortedMetaData[] = array( 'pageid' => $page ) + $metaData[$page];
				}
			}
		}

		// Output the results
		$result = array( 'result' => 'success', 'pages' => $sortedMetaData );
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * Create user info like user page, user talk page, user contribution page
	 * @param $userName string a valid username
	 * @param $userPageStatus array an array of user page, user talk page existing status
	 * @param $prefix string array key prefix
	 * @return array
	 */
	private function createUserInfo( $userName, $userPageStatus, $prefix ) {
		$userPage = Title::makeTitle( NS_USER, $userName );
		$userTalkPage = Title::makeTitle( NS_USER_TALK, $userName );
		$userContribsPage = SpecialPage::getTitleFor( 'Contributions', $userName );

		return array (
			$prefix . '_user_page' => $userPage->getPrefixedText(),
			$prefix . '_user_page_url' => $userPage->getFullURL(),
			$prefix . '_user_page_exist' => isset( $userPageStatus[$userPage->getPrefixedDBkey()] ),
			$prefix . '_user_talk_page' => $userTalkPage->getPrefixedText(),
			$prefix . '_user_talk_page_url' => $userTalkPage->getFullURL(),
			$prefix . '_user_talk_page_exist' => isset( $userPageStatus[$userTalkPage->getPrefixedDBkey()] ),
			$prefix . '_contribution_page' => $userContribsPage->getPrefixedText(),
			$prefix . '_contribution_page_url' => $userContribsPage->getFullURL(),
		);
	}

	/**
	 * Return all the page ids in PageTraige matching the specified filters
	 * @param $opts array of filtering options
	 * @return array an array of ids
	 *
	 * @Todo - enforce a range of timestamp to reduce tag record scan
	 */
	public static function getPageIds( $opts = array() ) {
		// Initialize required variables
		$pages = $options = array();

		// Get the expected limit as defined in getAllowedParams
		$options['LIMIT'] = $opts['limit'] + 1;

		if ( strtolower( $opts['dir'] ) === 'oldestfirst' ) {
			$options['ORDER BY'] = 'ptrp_created ASC, ptrp_page_id ASC';
			$offsetOperator = ' > ';
		} else {
			$options['ORDER BY'] = 'ptrp_created DESC, ptrp_page_id DESC';
			$offsetOperator = ' < ';
		}

		// Start building the massive filter which includes meta data
		$tables	  = array( 'pagetriage_page', 'page' );
		$conds    = array( 'ptrp_page_id = page_id' );

		// Helpful hint: In the ptrp_reviewed column...
		// 0 = unreviewed
		// 1 = reviewed
		// 2 = patrolled
		// 3 = autopatrolled
		$reviewOpr = '';
		if ( $opts['showreviewed'] ) {
			$reviewOpr .= '>';
		}
		if ( $opts['showunreviewed'] ) {
			$reviewOpr .= '=';
		}
		if ( !$reviewOpr ) {
			return $pages;
		}
		if ( $reviewOpr !== '>=' ) {
			$conds[] = 'ptrp_reviewed ' . $reviewOpr . ' 0';
		}

		// Include redirect
		if ( !$opts['showredirs'] ) {
			$conds['page_is_redirect'] = 0;
		}
		// Include marked for deletion
		if ( !$opts['showdeleted'] ) {
			$conds['ptrp_deleted'] = 0;
		}

		global $wgPageTriageNamespaces;
		// Show by namespace
		if ( isset( $opts['namespace'] ) && in_array( $opts['namespace'], $wgPageTriageNamespaces ) ) {
			$conds['page_namespace'] = $opts['namespace'];
		} else {
			// default to main namespace
			$conds['page_namespace'] = NS_MAIN;
		}

		// Database setup
		$dbr = wfGetDB( DB_SLAVE );

		// Offset the list by timestamp
		if ( array_key_exists( 'offset', $opts ) && is_numeric( $opts['offset'] ) && $opts['offset'] > 0 ) {
			$opts['offset'] = $dbr->addQuotes( $dbr->timestamp( $opts['offset'] ) );
			// Offset the list by page ID as well (in case multiple pages have the same timestamp)
			if ( array_key_exists( 'pageoffset', $opts ) && is_numeric( $opts['pageoffset'] ) && $opts['pageoffset'] > 0 ) {
				$conds[] = '( ptrp_created' . $offsetOperator . $opts['offset'] . ') OR ' .
					'( ptrp_created = ' . $opts['offset'] .' AND ' .
					'ptrp_page_id ' . $offsetOperator . $opts['pageoffset'] . ')';
			} else {
				$conds[] = 'ptrp_created' . $offsetOperator . $opts['offset'];
			}
		}

		$tagConds = self::buildTagQuery( $opts );
		if ( $tagConds ) {
			$conds[] = $tagConds;
			$tables[] = 'pagetriage_page_tags';
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

	/**
	 * @param $opts array
	 * @return string
	 */
	private static function buildTagQuery( $opts ) {
		$dbr = wfGetDB( DB_SLAVE );
		$tagConds = '';

		$searchableTags = array(
					// no categories assigned
					'no_category' => array( 'name' => 'category_count', 'op' => '=', 'val' => '0' ),
					// no inbound links
					'no_inbound_links' => array( 'name' => 'linkcount', 'op' => '=', 'val' => '0' ),
					// non auto confirmed users
					'non_autoconfirmed_users' => array( 'name' => 'user_autoconfirmed', 'op' => '=', 'val' => '0' ),
					// blocked users
					'blocked_users' => array( 'name' => 'user_block_status', 'op' => '=', 'val' => '1' ),
					// bots
					'showbots' => array( 'name' => 'user_bot', 'op' => '=', 'val' => '1' ),
					// user name
					'username' => array( 'name' => 'user_name', 'op' => '=', 'val' => false ) // false means use the actual value
				);

		$tags = ArticleMetadata::getValidTags();

		// only single tag search is allowed
		foreach ( $searchableTags as $key => $val ) {
			if ( $opts[$key] ) {
				if( $val['val'] === false ) {
					// if val is false, use the value that was supplied via the api call
					$tagConds = " ptrpt_page_id = ptrp_page_id AND ptrpt_tag_id = '" . $tags[$val['name']] . "' AND ptrpt_value " .
						$val['op'] . " " . $dbr->addQuotes( $opts[$key] );
				} else {
					$tagConds = " ptrpt_page_id = ptrp_page_id AND ptrpt_tag_id = '" . $tags[$val['name']] . "' AND ptrpt_value " .
						$val['op'] . " " . $dbr->addQuotes( $val['val'] );
				}
				break;
			}
		}

		return $tagConds;
	}

	public function getAllowedParams() {
		return array(
			'page_id' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'showbots' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showredirs' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showreviewed'=> array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showunreviewed'=> array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'showdeleted' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'limit' => array(
				ApiBase::PARAM_MAX => '200',
				ApiBase::PARAM_DFLT => '20',
				ApiBase::PARAM_MIN => '1',
				ApiBase::PARAM_TYPE => 'integer',
			),
			'offset' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'pageoffset' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'dir' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'namespace' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'no_category' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'no_inbound_links' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'non_autoconfirmed_users' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'blocked_users' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'username' => array(
				ApiBase::PARAM_TYPE => 'user',
			),
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'page_id' => 'Return data for the specified page ids, ignoring other parameters',
			'showbots' => 'Whether to show only bot edits',
			'showredirs' => 'Whether to include redirects or not', // default is not to show redirects
			'showreviewed' => 'Whether to include reviewed or not', // default is not to show reviewed
			'showunreviewed' => 'Whether to include unreviewed or not', // default is not to show unreviewed
			'showdeleted' => 'Whether to include "proposed for deleted" or not', // default is not to show deleted
			'limit' => 'The maximum number of results to return',
			'offset' => 'Timestamp to start from',
			'pageoffset' => 'Page ID to start from (requires offset param to be passed as well)',
			'dir' => 'The direction the list should be sorted in - oldestfirst or newestfirst',
			'namespace' => 'What namespace to pull pages from',
			'no_category' => 'Whether to show only pages with no category',
			'no_inbound_links' => 'Whether to show only pages with no inbound links',
			'non_autoconfirmed_users' => 'Whether to show only pages created by non auto confirmed users',
			'blocked_users' => 'Whether to show only pages created by blocked users',
			'username' => 'Show only pages created by username',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Get a list of page IDs for building a PageTriage queue.';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=pagetriagelist&limit=1000&namespace=0',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=pagetriagelist&limit=1000&namespace=0'
				=> 'apihelp-pagetriagelist-example-1',
		);
	}
}
