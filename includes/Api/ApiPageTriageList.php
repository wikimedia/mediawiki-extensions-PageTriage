<?php

namespace MediaWiki\Extension\PageTriage\Api;

use ApiBase;
use ApiMain;
use ApiResult;
use MediaWiki\Extension\PageTriage\ArticleMetadata;
use MediaWiki\Extension\PageTriage\OresMetadata;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Linker\LinksMigration;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserFactory;
use ORES\Services\ORESServices;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\OrExpressionGroup;

/**
 * API module to generate a list of pages to triage
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiPageTriageList extends ApiBase {

	/** @var UserFactory */
	private UserFactory $userFactory;

	/** @var RedirectLookup */
	private $redirectLookup;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var LinksMigration */
	private $linksMigration;

	/**
	 * @param ApiMain $query
	 * @param string $moduleName
	 */
	public function __construct(
		ApiMain $query,
		string $moduleName,
		UserFactory $userFactory,
		RedirectLookup $redirectLookup,
		TitleFormatter $titleFormatter,
		LinksMigration $linksMigration
	) {
		$this->userFactory = $userFactory;
		$this->linksMigration = $linksMigration;
		$this->redirectLookup = $redirectLookup;
		$this->titleFormatter = $titleFormatter;
		parent::__construct( $query, $moduleName );
	}

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
		$pageIdValidateDb = DB_REPLICA;

		$sortedMetaData = [];

		$result = [
			'result' => 'success',
			'pages_missing_metadata' => [],
		];

		if ( $pages ) {
			// fetch metadata for those pages
			$articleMetadata = new ArticleMetadata( $pages, $pageIdValidated, $pageIdValidateDb );
			$metaData = $articleMetadata->getMetadata();

			$userPageStatus = PageTriageUtil::pageStatusForUser( $metaData );
			$oresMetadata = null;

			if ( PageTriageUtil::oresIsAvailable() ) {
				$oresMetadata = OresMetadata::newFromGlobalState( $this->getContext(), $pages );
			}

			// Sort data according to page order returned by our query. Also convert it to a
			// slightly different format that's more Backbone-friendly.
			foreach ( $pages as $page ) {
				if ( !isset( $metaData[$page] ) || !ArticleMetadata::isValidMetadata( $metaData[$page] ) ) {
					// If metadata is missing for a page, add warning to API output and exclude
					// from feed.
					$result['pages_missing_metadata'][] = $page;
					$result['result'] = 'warning';
					continue;
				}
				$metaData[$page]['creation_date_utc'] = $metaData[$page]['creation_date'];
				$metaData[$page]['creation_date'] = $this->getContext()->getLanguage()->userAdjust(
					$metaData[$page]['creation_date']
				);

				if ( $metaData[$page]['user_name'] ) {
					// Page creator
					$user = $this->userFactory->newFromName( $metaData[$page]['user_name'] );
					if ( $user && $user->isHidden() ) {
						$metaData[$page]['user_name'] = null;
						$metaData[$page]['creator_hidden'] = true;
					} else {
						$metaData[$page] += $this->createUserInfo(
							$metaData[$page]['user_name'],
							$userPageStatus,
							'creator'
						);
					}
				}

				// Page reviewer
				if ( $metaData[$page]['reviewer'] ) {
					$metaData[$page] += $this->createUserInfo(
						$metaData[$page]['reviewer'],
						$userPageStatus,
						'reviewer'
					);
				}

				$pageTitle = Title::newFromText( $metaData[ $page ]['title'] );

				// Talk page feedback count and URL.
				if ( $opts['page_id'] ) {
					// Only add when a single page is being requested, e.g. for the PageTriage toolbar.
					$talkPage = $pageTitle->getTalkPageIfDefined();
					$metaData[$page]['talk_page_title'] = $talkPage->getPrefixedText();
					$metaData[$page]['talkpage_feedback_count'] = $this->getTalkpageFeedbackCount( $talkPage );
					$metaData[$page]['talk_page_url'] = $talkPage->getInternalURL();
					$metaData[$page]['is_orphan'] = $this->isOrphan( $page );
				}

				$redirectTarget = $this->redirectLookup->getRedirectTarget( $pageTitle );

				if ( $redirectTarget !== null ) {
					$metaData[$page]['redirect_target'] = $this->titleFormatter->getFullText( $redirectTarget );
				}

				// Add ORES data
				if ( $oresMetadata !== null ) {
					$metaData[$page] += $oresMetadata->getMetadata( $page );
				}

				$metaData[$page][ApiResult::META_BC_BOOLS] = [
					'creator_hidden', 'creator_user_page_exist', 'creator_user_talk_page_exist',
					'reviewer_user_page_exist', 'reviewer_user_talk_page_exist', 'is_orphan'
				];

				$sortedMetaData[] = [ 'pageid' => $page ] + $metaData[$page];
			}
		}

		// Log missing metadata.
		if ( count( $result['pages_missing_metadata'] ) ) {
			LoggerFactory::getInstance( 'PageTriage' )->debug( 'Metadata is missing for some pages.',
				[
					'pages_missing_metadata' => implode( ',', $result['pages_missing_metadata'] ),
					'opts' => json_encode( $opts, JSON_PRETTY_PRINT ),
				]
			);
		}

		// Output the results
		$result['pages'] = $sortedMetaData;
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param string[] &$tables
	 * @param array &$join_conds
	 */
	private static function joinWithTagCopyvio( &$tables, &$join_conds ) {
		$tags = ArticleMetadata::getValidTags();
		$tagId = $tags[ 'copyvio' ];

		$tables[ 'pagetriage_page_tags_copyvio' ] = 'pagetriage_page_tags';
		$join_conds[ 'pagetriage_page_tags_copyvio' ] = [
			'LEFT JOIN',
			[
				'pagetriage_page_tags_copyvio.ptrpt_page_id = ptrp_page_id',
				'pagetriage_page_tags_copyvio.ptrpt_tag_id' => $tagId,
			]
		];
	}

	/**
	 * @param string[] &$tables
	 * @param array &$join_conds
	 */
	private static function joinWithTags( &$tables, &$join_conds ) {
		$tables[ 'pagetriage_pt' ] = 'pagetriage_page_tags';
		$join_conds[ 'pagetriage_pt' ] = [
			'INNER JOIN',
			"pagetriage_pt.ptrpt_page_id = ptrp_page_id",
		];
	}

	/**
	 * @param array $opts
	 *
	 * @return string|false
	 */
	private static function buildCopyvioCond( $opts ) {
		if (
			!isset( $opts[ 'show_predicted_issues_copyvio' ] ) ||
			!$opts[ 'show_predicted_issues_copyvio' ]
		) {
			return false;
		}
		$tags = ArticleMetadata::getValidTags();
		if ( !isset( $tags[ 'copyvio' ] ) ) {
			return false;
		}

		return "pagetriage_page_tags_copyvio.ptrpt_value IS NOT NULL";
	}

	/**
	 * Get the total number of pagetriage-tagged revisions of a talk page.
	 * @param Title $pageTitle The title of the talk page
	 * @return int
	 */
	protected function getTalkpageFeedbackCount( Title $pageTitle ) {
		$dbr = PageTriageUtil::getReplicaConnection();
		$feedbackCount = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'change_tag_def' )
			->join( 'change_tag', 'change_tag', 'ctd_id = ct_tag_id' )
			->join( 'revision', 'revision', 'ct_rev_id = rev_id' )
			->join( 'page', 'page', 'rev_page = page_id' )
			->where( [
				'ctd_name' => 'pagetriage',
				'page_id' => $pageTitle->getArticleID(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		return $feedbackCount;
	}

	/**
	 * Get if a specific page is a orphan. This will only be used
	 * when the user is on a specific page and not the feed. The feed will continue to use
	 * the compiled and cached link count.
	 * @param int $pageId The ID of the page
	 * @return bool
	 */
	protected function isOrphan( int $pageId ): bool {
		$linkCount = PageTriageUtil::getLinkCount( $this->linksMigration, $pageId, 1 );
		return $linkCount === 0;
	}

	/**
	 * Create user info like user page, user talk page, user contribution page
	 * @param string $userName a valid username
	 * @param array $userPageStatus an array of user page, user talk page existing status
	 * @param string $prefix array key prefix
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
			$prefix . '_hidden' => false,
		];
	}

	/**
	 * Return all the page ids in PageTriage matching the specified filters
	 * @param array $opts Array of filtering options
	 * @param bool $count Set to true to return a count instead
	 * @return array|int an array of ids or total number of pages
	 *
	 * @todo - enforce a range of timestamp to reduce tag record scan
	 */
	public static function getPageIds( $opts = [], $count = false ) {
		// Initialize required variables
		$pages = [];
		$options = [];
		$conds = [];
		$join_conds = [];
		$offsetOperator = '';

		// Database setup
		$dbr = PageTriageUtil::getReplicaConnection();

		if ( !$count ) {
			// Get the expected limit as defined in getAllowedParams
			$options['LIMIT'] = $opts['limit'] + 1;

			switch ( strtolower( $opts['dir'] ?? '' ) ) {
				case 'oldestfirst':
					$options['ORDER BY'] = 'ptrp_created ASC, ptrp_page_id ASC';
					$offsetOperator = '>';
					break;
				case 'oldestreview':
					$options['ORDER BY'] = 'ptrp_reviewed_updated ASC, ptrp_page_id ASC';
					$offsetOperator = '>';
					break;
				case 'newestreview':
					$options['ORDER BY'] = 'ptrp_reviewed_updated DESC, ptrp_page_id DESC';
					$offsetOperator = '<';
					break;
				default:
					$options['ORDER BY'] = 'ptrp_created DESC, ptrp_page_id DESC';
					$offsetOperator = '<';
			}
		}

		// Start building the massive filter which includes meta data
		$tables	= [ 'pagetriage_page', 'page' ];
		$join_conds['page'] = [
			'INNER JOIN',
			'ptrp_page_id = page_id',
		];

		// Helpful hint: In the ptrp_reviewed column...
		// 0 = unreviewed
		// 1 = reviewed
		// 2 = patrolled
		// 3 = autopatrolled
		$reviewOpr = '';
		if ( isset( $opts['showreviewed'] ) && $opts['showreviewed'] ) {
			$reviewOpr .= '>';
		}
		if ( isset( $opts['showunreviewed'] ) && $opts['showunreviewed'] ) {
			$reviewOpr .= '=';
		}
		if ( !$reviewOpr ) {
			if ( $count ) {
				return 0;
			} else {
				return $pages;
			}
		}
		if ( $reviewOpr !== '>=' ) {
			$conds[] = 'ptrp_reviewed ' . $reviewOpr . ' 0';
		}

		if ( isset( $opts['showautopatrolled'] ) && $opts['showautopatrolled'] ) {
			$conds[] = 'ptrp_reviewed = 3';
		}

		if ( isset( $opts['date_range_from'] ) && $opts['date_range_from'] ) {
			$conds[] = ' ptrp_created  >= ' . $dbr->addQuotes( $dbr->timestamp( $opts['date_range_from'] ) );
		}

		if ( isset( $opts['date_range_to'] ) && $opts['date_range_to'] ) {
			$conds[] = 'ptrp_created <= ' . $dbr->addQuotes( $dbr->timestamp( $opts['date_range_to'] ) );
		}

		// Filter on types
		$redirects = $opts['showredirs'] ?? false;
		$deleted = $opts['showdeleted'] ?? false;
		$others = $opts['showothers'] ?? false;
		$typeConds = [];

		if ( $redirects !== $others ) {
			$typeConds['page_is_redirect'] = $redirects ? 1 : 0;
		}
		if ( $deleted !== $others ) {
			$typeConds['ptrp_deleted'] = $deleted ? 1 : 0;
		}
		if ( $typeConds ) {
			$conds[] = $dbr->makeList( $typeConds, $others ? LIST_AND : LIST_OR );
		}

		// Show by namespace. Defaults to main namespace.
		$nsId = ( isset( $opts['namespace'] ) && $opts['namespace'] ) ? $opts['namespace'] : NS_MAIN;
		$conds['page_namespace'] = PageTriageUtil::validatePageNamespace( $nsId );

		// Offset the list by timestamp
		$offsetConds = [];
		if (
			array_key_exists( 'offset', $opts ) &&
			is_numeric( $opts['offset'] ) &&
			$opts['offset'] > 0 &&
			!$count
		) {
			$offsetConds['ptrp_created'] = $dbr->timestamp( $opts['offset'] );
			// Offset the list by page ID as well (in case multiple pages have the same timestamp)
			if (
				array_key_exists( 'pageoffset', $opts ) &&
				is_numeric( $opts['pageoffset'] ) &&
				$opts['pageoffset'] > 0
			) {
				$offsetConds['ptrp_page_id'] = $opts['pageoffset'];
			}
			$conds[] = $dbr->buildComparison( $offsetOperator, $offsetConds );
		}

		$tagConds = self::buildTagQuery( $opts );
		$numberOfTagConds = count( $tagConds );

		if ( $numberOfTagConds > 0 ) {
			$conds[] = new OrExpressionGroup( ...$tagConds );
			$options['GROUP BY'] = "ptrp_page_id";
			$options['HAVING'] = "COUNT(*) = $numberOfTagConds";
			self::joinWithTags( $tables, $join_conds );
		}

		// ORES articlequality filter
		if ( PageTriageUtil::oresIsAvailable() &&
			PageTriageUtil::isOresArticleQualityQuery( $opts ) ) {
			$oresCond = ORESServices::getDatabaseQueryBuilder()->buildQuery(
				'articlequality',
				PageTriageUtil::mapOresParamsToClassNames( 'articlequality', $opts )
			);
			if ( $oresCond ) {
				self::joinWithOres( 'articlequality', $tables, $join_conds );
				$conds[] = $oresCond;
			}
		}

		// ORES draftquality and copyvio filters
		if ( PageTriageUtil::oresIsAvailable() &&
			( PageTriageUtil::isOresDraftQualityQuery( $opts ) ||
			PageTriageUtil::isCopyvioQuery( $opts ) )
		) {
			$draftqualityCopyvioConds = [];

			// "Issues: none" used to be map straight to DraftQuality class OK
			// It now means: no known ORES DraftQuality issues or Copyvio
			// It has to be removed from the $opts ORES will used to build a query
			// and handled separately.
			$showOK = $opts[ 'show_predicted_issues_none' ] ?? false;
			if ( $showOK ) {
				unset( $opts[ 'show_predicted_issues_none' ] );
				$draftqualityCopyvioConds[] = $dbr->makeList( [
					$dbr->makeList( [
						'ores_draftquality_cls.oresc_class=1',
						'ores_draftquality_cls.oresc_class IS NULL'
					], LIST_OR ),
					'pagetriage_page_tags_copyvio.ptrpt_value IS NULL',
				], LIST_AND );
			}

			$oresCond = ORESServices::getDatabaseQueryBuilder()->buildQuery(
				'draftquality',
				PageTriageUtil::mapOresParamsToClassNames( 'draftquality', $opts ),
				true
			);
			if ( $oresCond ) {
				$draftqualityCopyvioConds[] = $oresCond;
			}

			$copyvioCond = self::buildCopyvioCond( $opts );
			if ( $copyvioCond ) {
				$draftqualityCopyvioConds[] = $copyvioCond;
			}

			if ( $draftqualityCopyvioConds ) {
				$conds[] = $dbr->makeList( $draftqualityCopyvioConds, LIST_OR );
			}

			if ( $showOK || $oresCond ) {
				self::joinWithOres( 'draftquality', $tables, $join_conds );
			}

			if ( $showOK || $copyvioCond ) {
				self::joinWithTagCopyvio( $tables, $join_conds );
			}
		}

		if ( $count ) {
			$res = $dbr->selectRowCount(
				$tables,
				'ptrp_page_id',
				$conds,
				__METHOD__,
				$options,
				$join_conds
			);

			return $res;
		} else {
			// Pull page IDs from database
			$res = $dbr->select(
				$tables,
				'ptrp_page_id',
				$conds,
				__METHOD__,
				$options,
				$join_conds
			);

			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$pages[] = $row->ptrp_page_id;
			}
			return $pages;
		}
	}

	/**
	 * @param string $model Name of the model this join is for
	 * @param array &$tables
	 * @param array &$join_conds
	 */
	private static function joinWithOres( $model, &$tables, &$join_conds ) {
		$modelId = ORESServices::getModelLookup()->getModelId( $model );
		$tableAlias = "ores_{$model}_cls";
		$tables[ $tableAlias ] = 'ores_classification';
		$innerJoinConds = [
			"$tableAlias.oresc_rev = page_latest",
			"$tableAlias.oresc_model" => $modelId,
		];
		if ( $model === 'draftquality' ) {
			$innerJoinConds[] = "$tableAlias.oresc_is_predicted = 1";
		}
		$join_conds[ $tableAlias ] = [ 'LEFT JOIN', $innerJoinConds ];
	}

	/**
	 * @param array $opts
	 * @return IExpression[] SQL condition for use in a WHERE clause
	 */
	private static function buildTagQuery( array $opts ) {
		$dbr = PageTriageUtil::getReplicaConnection();
		$tagConds = [];

		$searchableTags = [
			// no categories assigned
			'no_category' => [ 'name' => 'category_count', 'val' => 0 ],
			// No citations
			'unreferenced' => [ 'name' => 'reference', 'val' => 0 ],
			// AfC status
			'afc_state' => [ 'name' => 'afc_state', 'val' => null ],
			// no inbound links
			'no_inbound_links' => [ 'name' => 'linkcount', 'val' => 0 ],
			// previously deleted
			'recreated' => [ 'name' => 'recreated', 'val' => 1 ],
			// non auto confirmed users
			'non_autoconfirmed_users' => [ 'name' => 'user_autoconfirmed', 'val' => 0 ],
			// learning users (newly autoconfirmed)
			'learners' => [ 'name' => 'user_experience', 'val' => 'learner' ],
			// blocked users
			'blocked_users' => [ 'name' => 'user_block_status', 'val' => 1 ],
			// bots
			'showbots' => [ 'name' => 'user_bot', 'val' => 1 ],
			// user name
			// false means use the actual value
			'username' => [ 'name' => 'user_name', 'val' => null ]
		];

		$tagIDs = ArticleMetadata::getValidTags();
		// "pagetriage_pt" alias from self::joinWithTags
		$table = 'pagetriage_pt';

		// only single tag search is allowed
		foreach ( $searchableTags as $key => $val ) {
			if ( isset( $opts[$key] ) && $opts[$key] ) {
				$tagConds[] = $dbr
					->expr( "$table.ptrpt_tag_id", "=", $tagIDs[$val['name']] )
					->and( "$table.ptrpt_value", "=", $val['val'] ?? $opts[$key] );
			}
		}

		return $tagConds;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return array_merge(
			PageTriageUtil::getOresApiParams(),
			PageTriageUtil::getCopyvioApiParam(),
			PageTriageUtil::getCommonApiParams(),
			[
				'page_id' => [
					ParamValidator::PARAM_TYPE => 'integer',
				],
				'limit' => [
					IntegerDef::PARAM_MAX => 200,
					ParamValidator::PARAM_DEFAULT => 20,
					IntegerDef::PARAM_MIN => 1,
					ParamValidator::PARAM_TYPE => 'integer',
				],
				'offset' => [
					ParamValidator::PARAM_TYPE => 'integer',
				],
				'pageoffset' => [
					ParamValidator::PARAM_TYPE => 'integer',
				],
				'dir' => [
					ParamValidator::PARAM_TYPE => [
						'newestfirst',
						'oldestfirst',
						'oldestreview',
						'newestreview',
					],
				]
			]
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=pagetriagelist&limit=100&namespace=0&showunreviewed=1'
				=> 'apihelp-pagetriagelist-example-1',
		];
	}

}
