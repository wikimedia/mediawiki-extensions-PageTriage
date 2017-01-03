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

		if ( $opts['page_id'] ) {
			// page id was specified
			$pages = [ $opts['page_id'] ];
			$pageIdValidated = false;
		} else {
			// Retrieve the list of page IDs
			$pages = self::getPageIds( $opts );
			$pageIdValidated = true;
		}
		$pageIdValidateDb = DB_SLAVE;

		$sortedMetaData = [];

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
					$metaData[$page]['creation_date'] = $this->getContext()->getLanguage()->userAdjust(
						$metaData[$page]['creation_date']
					);

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

					$metaData[$page][ApiResult::META_BC_BOOLS] = [
						'creator_user_page_exist', 'creator_user_talk_page_exist',
						'reviewer_user_page_exist', 'reviewer_user_talk_page_exist',
					];

					$sortedMetaData[] = [ 'pageid' => $page ] + $metaData[$page];
				}
			}
		}

		// Output the results
		$result = [ 'result' => 'success', 'pages' => $sortedMetaData ];
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

		return [
			$prefix . '_user_page' => $userPage->getPrefixedText(),
			$prefix . '_user_page_url' => $userPage->getFullURL(),
			$prefix . '_user_page_exist' => isset( $userPageStatus[$userPage->getPrefixedDBkey()] ),
			$prefix . '_user_talk_page' => $userTalkPage->getPrefixedText(),
			$prefix . '_user_talk_page_url' => $userTalkPage->getFullURL(),
			$prefix . '_user_talk_page_exist' => isset( $userPageStatus[$userTalkPage->getPrefixedDBkey()] ),
			$prefix . '_contribution_page' => $userContribsPage->getPrefixedText(),
			$prefix . '_contribution_page_url' => $userContribsPage->getFullURL(),
		];
	}

	/**
	 * Return all the page ids in PageTraige matching the specified filters
	 * @param $opts array of filtering options
	 * @return array an array of ids
	 *
	 * @Todo - enforce a range of timestamp to reduce tag record scan
	 */
	public static function getPageIds( $opts = [] ) {
		// Initialize required variables
		$pages = $options = [];

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
		$tables	= [ 'pagetriage_page', 'page' ];
		$conds	= [ 'ptrp_page_id = page_id' ];

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
		if (
			array_key_exists( 'offset', $opts ) &&
			is_numeric( $opts['offset'] ) &&
			$opts['offset'] > 0
		) {
			$opts['offset'] = $dbr->addQuotes( $dbr->timestamp( $opts['offset'] ) );
			// Offset the list by page ID as well (in case multiple pages have the same timestamp)
			if (
				array_key_exists( 'pageoffset', $opts ) &&
				is_numeric( $opts['pageoffset'] ) &&
				$opts['pageoffset'] > 0
			) {
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

		$searchableTags = [
			// no categories assigned
			'no_category' => [ 'name' => 'category_count', 'op' => '=', 'val' => '0' ],
			// no inbound links
			'no_inbound_links' => [ 'name' => 'linkcount', 'op' => '=', 'val' => '0' ],
			// non auto confirmed users
			'non_autoconfirmed_users' => [ 'name' => 'user_autoconfirmed', 'op' => '=', 'val' => '0' ],
			// blocked users
			'blocked_users' => [ 'name' => 'user_block_status', 'op' => '=', 'val' => '1' ],
			// bots
			'showbots' => [ 'name' => 'user_bot', 'op' => '=', 'val' => '1' ],
			// user name
			// false means use the actual value
			'username' => [ 'name' => 'user_name', 'op' => '=', 'val' => false ]
		];

		$tags = ArticleMetadata::getValidTags();

		// only single tag search is allowed
		foreach ( $searchableTags as $key => $val ) {
			if ( $opts[$key] ) {
				if ( $val['val'] === false ) {
					// if val is false, use the value that was supplied via the api call
					$tagConds = " ptrpt_page_id = ptrp_page_id AND ptrpt_tag_id = '"
						. $tags[$val['name']] . "' AND ptrpt_value "
						. $val['op'] . " " . $dbr->addQuotes( $opts[$key] );
				} else {
					$tagConds = " ptrpt_page_id = ptrp_page_id AND ptrpt_tag_id = '"
						. $tags[$val['name']] . "' AND ptrpt_value "
						. $val['op'] . " " . $dbr->addQuotes( $val['val'] );
				}
				break;
			}
		}

		return $tagConds;
	}

	public function getAllowedParams() {
		return [
			'page_id' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'showbots' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showredirs' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showreviewed'=> [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showunreviewed'=> [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'showdeleted' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'limit' => [
				ApiBase::PARAM_MAX => '200',
				ApiBase::PARAM_DFLT => '20',
				ApiBase::PARAM_MIN => '1',
				ApiBase::PARAM_TYPE => 'integer',
			],
			'offset' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'pageoffset' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'dir' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'namespace' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'no_category' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'no_inbound_links' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'non_autoconfirmed_users' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'blocked_users' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
			'username' => [
				ApiBase::PARAM_TYPE => 'user',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=pagetriagelist&limit=100&namespace=0&showunreviewed=1'
				=> 'apihelp-pagetriagelist-example-1',
		];
	}
}
