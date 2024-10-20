<?php

namespace MediaWiki\Extension\PageTriage;

use Exception;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\PageTriage\Api\ApiPageTriageList;
use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileAfcTag;
use MediaWiki\Linker\LinksMigration;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use ORES\Hooks\Helpers;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use WikiPage;

/**
 * Utility class for PageTriage
 */
class PageTriageUtil {

	/**
	 * Get whether a page needs triaging
	 *
	 * @param WikiPage $page
	 *
	 * @throws Exception
	 * @return bool|null Null if the page is not in the triage system,
	 * true if the page is unreviewed, false otherwise.
	 */
	public static function isPageUnreviewed( WikiPage $page ): ?bool {
		$queueLookup = PageTriageServices::wrap( MediaWikiServices::getInstance() )
			->getQueueLookup();
		$queueRecord = $queueLookup->getByPageId( $page->getId() );
		if ( !$queueRecord ) {
			return null;
		}
		return $queueRecord->getReviewedStatus() === QueueRecord::REVIEW_STATUS_UNREVIEWED;
	}

	/**
	 * Return the ptrl_reviewed status of a page.
	 *
	 * @param WikiPage $page
	 *
	 * @throws Exception
	 * @return int|null 0 = unreviewed, 1 = marked as reviewed, 2 = marked as
	 * patrolled, 3 = autopatrolled, null = not in queue (treated as marked as
	 * reviewed)
	 */
	public static function getStatus( WikiPage $page ) {
		$queueLookup = PageTriageServices::wrap( MediaWikiServices::getInstance() )
			->getQueueLookup();
		$queueRecord = $queueLookup->getByPageId( $page->getId() );
		if ( !$queueRecord ) {
			return null;
		}
		return $queueRecord->getReviewedStatus();
	}

	/**
	 * Get the IDs of applicable PageTriage namespaces, including draftspace.
	 *
	 * This is useful if you want to get the namespaces where PageTriage should
	 * write to SQL for the Special:NewPagesFeed. If you want to get the namespaces
	 * where PageTriage should display a toolbar or send a notification, you should
	 * instead use $config->get( 'PageTriageNamespaces' ), which does not include
	 * draftspace.
	 *
	 * @param Config|null $config
	 * @return int[]
	 */
	public static function getNamespaces( ?Config $config = null ): array {
		$config ??= MediaWikiServices::getInstance()->getMainConfig();
		$pageTriageDraftNamespaceId = $config->get( 'PageTriageDraftNamespaceId' );
		$pageTriageNamespaces = $config->get( 'PageTriageNamespaces' );
		// Add the Draft namespace if configured.
		if ( $pageTriageDraftNamespaceId
			&& !in_array( $pageTriageDraftNamespaceId, $pageTriageNamespaces )
		) {
			$pageTriageNamespaces[] = $pageTriageDraftNamespaceId;
		}
		return $pageTriageNamespaces;
	}

	/**
	 * Validate a page namespace ID.
	 * @param int $namespace The namespace ID to validate.
	 * @return int The provided namespace if valid, otherwise 0 (main namespace).
	 */
	public static function validatePageNamespace( $namespace ) {
		$pageTriageNamespaces = static::getNamespaces();
		if ( !in_array( $namespace, $pageTriageNamespaces ) ) {
			$namespace = NS_MAIN;
		}

		return (int)$namespace;
	}

	/**
	 * Get a list of stat for unreviewed articles
	 * @param int $namespace Namespace number
	 * @return array
	 *
	 */
	public static function getUnreviewedArticleStat( $namespace = 0 ) {
		return self::getUnreviewedPageStat( $namespace, false );
	}

	/**
	 * Get a list of stat for unreviewed redirects
	 * @param int $namespace Namespace number
	 * @return array
	 *
	 */
	public static function getUnreviewedRedirectStat( $namespace = 0 ) {
		return self::getUnreviewedPageStat( $namespace, true );
	}

	/**
	 * Get a list of stat for unreviewed pages
	 * @param int $namespace Namespace number
	 * @param bool $redirect
	 * @return array
	 *
	 * @todo - Limit the number of records by a timestamp filter, maybe 30 days etc,
	 *         depends on the time the triage queue should look back for listview
	 */
	public static function getUnreviewedPageStat( $namespace = 0, $redirect = false ) {
		$namespace = self::validatePageNamespace( $namespace );

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		$key = ( $redirect ) ? 'pagetriage-unreviewed-redirects-stat' : 'pagetriage-unreviewed-articles-stat';

		return $cache->getWithSetCallback(
			$cache->makeKey( $key, $namespace ),
			10 * $cache::TTL_MINUTE,
			static function () use ( $namespace, $fname, $redirect ) {
				$dbr = self::getReplicaConnection();

				$conds = [
					'ptrp_reviewed' => 0,
					// remove redirect from the unreviewd number per bug40540
					'page_is_redirect' => (int)$redirect,
					// remove deletion nominations from stats per T205741
					'ptrp_deleted' => 0,
					'page_namespace' => $namespace
				];

				$res = $dbr->newSelectQueryBuilder()
					->select( [
						'total' => 'COUNT(ptrp_page_id)',
						'oldest' => 'MIN(ptrp_reviewed_updated)'
					] )
					->from( 'pagetriage_page' )
					->join( 'page', null, 'page_id = ptrp_page_id' )
					->where( $conds )
					->caller( $fname )
					->fetchRow();

				$data = [ 'count' => 0, 'oldest' => '' ];

				if ( $res ) {
					$data['count'] = (int)$res->total;
					$data['oldest'] = wfTimestamp( TS_ISO_8601, $res->oldest );
				}

				return $data;
			},
			[ 'version' => PageTriage::CACHE_VERSION ]
		);
	}

	/**
	 * Get the number of pages based on the selected filters.
	 * @param array $filters Associative array of filter names/values.
	 *                       See ApiPageTriageStats->getAllowedParams() for possible values,
	 *                       which are the same that the ApiPageTriageList endpoint accepts.
	 * @return int Number of pages based on the selected filters
	 */
	public static function getArticleFilterStat( $filters ) {
		if ( !isset( $filters['showreviewed'] ) && !isset( $filters['showunreviewed'] ) ) {
			$filters['showunreviewed'] = 'showunreviewed';
		}

		return ApiPageTriageList::getPageIds( $filters, true );
	}

	/**
	 * Get number of reviewed articles in the past week
	 * @param int $namespace Namespace number
	 * @return array Stats to be returned
	 */
	public static function getReviewedArticleStat( $namespace = 0 ) {
		return self::getReviewedPageStat( $namespace, false );
	}

	/**
	 * Get number of reviewed redirects in the past week
	 * @param int $namespace Namespace number
	 * @return array Stats to be returned
	 */
	public static function getReviewedRedirectStat( $namespace = 0 ) {
		return self::getReviewedPageStat( $namespace, true );
	}

	/**
	 * Get number of reviewed articles in the past week
	 * @param int $namespace Namespace number
	 * @param bool $redirect
	 * @return array Stats to be returned
	 */
	public static function getReviewedPageStat( $namespace = 0, $redirect = false ) {
		$namespace = self::validatePageNamespace( $namespace );

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		$key = ( $redirect ) ? 'pagetriage-reviewed-redirects-stat' : 'pagetriage-reviewed-articles-stat';

		return $cache->getWithSetCallback(
			$cache->makeKey( $key, $namespace ),
			10 * $cache::TTL_MINUTE,
			static function () use ( $namespace, $fname, $redirect ) {
				$time = (int)wfTimestamp( TS_UNIX ) - 7 * 24 * 60 * 60;

				$dbr = self::getReplicaConnection();
				$conds = [
					// T310108
					'ptrp_reviewed' => [ 1, 2 ],
					'page_namespace' => $namespace,
					'page_is_redirect' => (int)$redirect,
					$dbr->expr( 'ptrp_reviewed_updated', '>', $dbr->timestamp( $time ) ),
				];

				$res = $dbr->newSelectQueryBuilder()
					->select( [ 'reviewed_count' => 'COUNT(ptrp_page_id)' ] )
					->from( 'pagetriage_page' )
					->join( 'page', null, 'page_id = ptrp_page_id' )
					->where( $conds )
					->caller( $fname )
					->fetchRow();

				$data = [ 'reviewed_count' => 0 ];

				if ( $res ) {
					$data['reviewed_count'] = (int)$res->reviewed_count;
				}

				return $data;
			},
			[ 'version' => PageTriage::CACHE_VERSION ]
		);
	}

	/**
	 * Get number of drafts awaiting review and the age of the oldest submitted draft
	 *
	 * @return array ['count' => (int) number of unreviewed drafts,
	 * 'oldest' => (string) timestamp of oldest unreviewed draft].
	 * An empty array is returned if $wgPageTriageDraftNamespaceId is not enabled.
	 */
	public static function getUnreviewedDraftStats(): array {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( !$config->get( 'PageTriageDraftNamespaceId' ) ) {
			return [];
		}

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		return $cache->getWithSetCallback(
			'pagetriage-unreviewed-drafts-stats',
			// 10 minute cache
			10 * $cache::TTL_MINUTE,
			static function () use ( $fname, $config ) {
				$dbr = self::getReplicaConnection();

				$afcStateTagId = $dbr->newSelectQueryBuilder()
					->select( 'ptrt_tag_id' )
					->from( 'pagetriage_tags' )
					->where( [ 'ptrt_tag_name' => 'afc_state' ] )
					->caller( $fname )
					->fetchField();

				if ( !$afcStateTagId ) {
					return [];
				}

				$conds = [
					'ptrpt_tag_id' => $afcStateTagId,
					'ptrpt_value' => (string)ArticleCompileAfcTag::PENDING,
					'page_namespace' => $config->get( 'PageTriageDraftNamespaceId' )
				];

				$res = $dbr->newSelectQueryBuilder()
					->select( [
						'total' => 'COUNT(ptrp_page_id)',
						'oldest' => 'MIN(ptrp_reviewed_updated)',
					] )
					->from( 'page' )
					->join( 'pagetriage_page', null, 'page_id = ptrp_page_id' )
					->join( 'pagetriage_page_tags', null, 'ptrp_page_id = ptrpt_page_id' )
					->where( $conds )
					->caller( $fname )
					->fetchRow();

				$data = [];

				if ( $res ) {
					$data['count'] = (int)$res->total;
					$data['oldest'] = wfTimestamp( TS_ISO_8601, $res->oldest );
				}

				return $data;
			},
			[ 'version' => PageTriage::CACHE_VERSION ]
		);
	}

	/**
	 * returns the cache key for user status
	 * @param string $userName
	 * @return string
	 */
	public static function userStatusKey( $userName ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->makeKey(
			'pagetriage-user-page-status',
			sha1( $userName ),
			PageTriage::CACHE_VERSION
		);
	}

	/**
	 * Check the existance of user page and talk page for a list of users
	 * @param array $users contains user_name db keys
	 * @return array
	 */
	public static function pageStatusForUser( $users ) {
		$return = [];
		$title  = [];

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		foreach ( $users as $user ) {
			$user = (array)$user;
			$searchKey = [ 'user_name', 'reviewer' ];

			foreach ( $searchKey as $val ) {
				if ( !isset( $user[$val] ) || !$user[$val] ) {
					continue;
				}
				$data = $cache->get( self::userStatusKey( $user[$val] ) );
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
						$title[$u->getDBkey()] = [ 'user_name' => $user[$val], 'u' => $u, 't' => $t ];
					}
				}
			}
		}

		if ( $title ) {
			$dbr = self::getReplicaConnection();
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'page_namespace', 'page_title' ] )
				->from( 'page' )
				->where( [
					'page_title' => array_map( 'strval', array_keys( $title ) ),
					'page_namespace' => [ NS_USER, NS_USER_TALK ]
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			$dataToCache = [];
			// if there is result from the database, that means the page exists, set it to the
			// cache array with value 1
			foreach ( $res as $row ) {
				$user = $title[$row->page_title];
				if ( (int)$row->page_namespace === NS_USER ) {
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
				$cache->set(
					self::userStatusKey( $value['user_name'] ),
					$dataToCache[$value['user_name']],
					3600
				);
			}
		}

		return $return;
	}

	/**
	 * Update user metadata when a user's block status is updated
	 * @param DatabaseBlock $block block object
	 * @param int $userBlockStatusToWrite 1/0
	 */
	public static function updateMetadataOnBlockChange( $block, $userBlockStatusToWrite = 1 ) {
		// do instant update if the number of page to be updated is less or equal to
		// the number below, otherwise, delay this to the cron
		$maxNumToProcess = 500;

		$tags = ArticleMetadata::getValidTags();
		if ( !$tags ) {
			return;
		}

		$dbr = self::getReplicaConnection();

		// Select all articles in PageTriage queue created by the blocked user
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'ptrpt_page_id' ] )
			->from( 'pagetriage_page_tags' )
			->where( [
				'ptrpt_tag_id' => $tags['user_name'],
				'ptrpt_value' => $block->getTargetName()
			] )
			->limit( $maxNumToProcess + 1 )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res->numRows() > $maxNumToProcess ) {
			return;
		}

		$pageIds = [];
		foreach ( $res as $row ) {
			$pageIds[] = $row->ptrpt_page_id;
		}

		$noArticlesNeedUpdating = !$pageIds;
		if ( $noArticlesNeedUpdating ) {
			return;
		}

		$dbw = self::getPrimaryConnection();
		$dbw->startAtomic( __METHOD__ );
		$dbw->newUpdateQueryBuilder()
			->update( 'pagetriage_page_tags' )
			->set( [ 'ptrpt_value' => (string)$userBlockStatusToWrite ] )
			->where( [ 'ptrpt_page_id' => $pageIds, 'ptrpt_tag_id' => $tags['user_block_status'] ] )
			->caller( __METHOD__ )
			->execute();

		PageTriage::bulkSetTagsUpdated( $pageIds );
		$dbw->endAtomic( __METHOD__ );

		$metadata = new ArticleMetadata( $pageIds );
		$metadata->flushMetadataFromCache();
	}

	/**
	 * Attempt to create an Echo notification event for
	 * 1. 'Mark as Reviewed' curation flyout
	 * 2. 'Mark as Patrolled' from Special:NewPages
	 * 3. 'Add maintenance tag' curation flyout
	 * 4. 'Add deletion tag' curation flyout
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $type notification type
	 * @param array|null $extra
	 * @return StatusValue
	 */
	public static function createNotificationEvent(
		Title $title, UserIdentity $user, string $type, ?array $extra = null
	): StatusValue {
		$status = StatusValue::newGood();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return $status;
		}

		$params = [
			'type' => $type,
			'title' => $title,
			'agent' => $user,
		];

		if ( $extra ) {
			$extra['note'] = self::truncateLongText( $extra['note'] );
			$params['extra'] = $extra;
		}

		$echoEvent = Event::create( $params );
		if ( $echoEvent instanceof Event ) {
			return StatusValue::newGood( $echoEvent );
		} else {
			return StatusValue::newFatal( new ApiRawMessage( 'Failed to create Echo event.' ) );
		}
	}

	/**
	 * @param string $text The text to truncate.
	 * @param int $length Maximum number of characters.
	 * @param string $ellipsis String to append to the end of truncated text.
	 * @return string
	 */
	public static function truncateLongText( $text, $length = 150, $ellipsis = '...' ) {
		if ( !is_string( $text ) ) {
			return $text;
		}

		return RequestContext::getMain()->getLanguage()->truncateForVisual( $text, $length, $ellipsis );
	}

	/**
	 * Get an array of ORES articlequality API parameters.
	 *
	 * @return array
	 */
	private static function getOresArticleQualityApiParams() {
		return [
			'show_predicted_class_stub' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_class_start' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_class_c' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_class_b' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_class_good' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_class_featured' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
		];
	}

	/**
	 * Get an array of ORES draftquality API parameters.
	 *
	 * @return array
	 */
	private static function getOresDraftQualityApiParams() {
		return [
			'show_predicted_issues_vandalism' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_issues_spam' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_issues_attack' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'show_predicted_issues_none' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
		];
	}

	/**
	 * Get an array of ORES API parameters.
	 *
	 * These are used in both NPP and AFC contexts.
	 *
	 * @return array
	 */
	public static function getOresApiParams() {
		return self::getOresArticleQualityApiParams() + self::getOresDraftQualityApiParams();
	}

	/**
	 * Get array of common API parameters, for use with getAllowedParams().
	 *
	 * @return array
	 */
	public static function getCommonApiParams() {
		return [
			'showbots' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showautopatrolled' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showredirs' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showothers' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showreviewed' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showunreviewed' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'showdeleted' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'namespace' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'afc_state' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'no_category' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'unreferenced' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'no_inbound_links' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'recreated' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'non_autoconfirmed_users' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'learners' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'blocked_users' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'username' => [
				ParamValidator::PARAM_TYPE => 'user',
			],
			'date_range_from' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'date_range_to' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
		];
	}

	/**
	 * Helper method to check if the API call includes ORES articlequality parameters.
	 *
	 * @param array $opts
	 * @return bool
	 */
	public static function isOresArticleQualityQuery( $opts ) {
		return self::queryContains( $opts, self::getOresArticleQualityApiParams() );
	}

	/**
	 * Helper method to check if the API call includes ORES draftquality parameters.
	 *
	 * @param array $opts
	 * @return bool
	 */
	public static function isOresDraftQualityQuery( $opts ) {
		return self::queryContains( $opts, self::getOresDraftQualityApiParams() );
	}

	/**
	 * Helper method to check if $opts contains some of the parameters in $params.
	 *
	 * @param array $opts Selected parameters from API request
	 * @param array $params
	 * @return bool
	 */
	private static function queryContains( $opts, $params ) {
		$params = array_keys( $params );
		foreach ( $params as $key ) {
			if ( isset( $opts[ $key ] ) && $opts[ $key ] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert ORES param names to class names.
	 *
	 * @param string $model Which model to convert names for ('articlequality' or 'draftquality')
	 * @param array $opts Selected parameters
	 * @return array Corresponding ORES class names
	 */
	public static function mapOresParamsToClassNames( $model, $opts ) {
		$paramsToClassesMap = [
			'articlequality' => [
				'show_predicted_class_stub' => 'Stub',
				'show_predicted_class_start' => 'Start',
				'show_predicted_class_c' => 'C',
				'show_predicted_class_b' => 'B',
				'show_predicted_class_good' => 'GA',
				'show_predicted_class_featured' => 'FA',
			],
			'draftquality' => [
				'show_predicted_issues_vandalism' => 'vandalism',
				'show_predicted_issues_spam' => 'spam',
				'show_predicted_issues_attack' => 'attack',
				'show_predicted_issues_none' => 'OK',
			],
		];
		$result = [];
		foreach ( $paramsToClassesMap[ $model ] as $param => $className ) {
			if ( isset( $opts[ $param ] ) && $opts[ $param ] ) {
				$result[] = $className;
			}
		}
		return $result;
	}

	/**
	 * Check if the ORES extension is present and configured
	 * correctly for PageTriage to integrate with it.
	 *
	 * @return bool
	 */
	public static function oresIsAvailable() {
		return ExtensionRegistry::getInstance()->isLoaded( 'ORES' ) &&
			Helpers::isModelEnabled( 'articlequality' ) &&
			Helpers::isModelEnabled( 'draftquality' );
	}

	/**
	 * @return array The copyvio filter parameter
	 */
	public static function getCopyvioApiParam() {
		return [
			'show_predicted_issues_copyvio' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
		];
	}

	/**
	 * Check if $opts contain the copyvio filter parameter
	 *
	 * @param array $opts
	 * @return bool
	 */
	public static function isCopyvioQuery( $opts ) {
		return $opts[ 'show_predicted_issues_copyvio' ] ?? false;
	}

	/**
	 * Get a count of how many links are in a specific page.
	 * @param LinksMigration $linksMigration
	 * @param int $pageId The page for which links need to be fetched
	 * @param int $limit Number of links to fetch, defaults to 51
	 * @return int Number of links
	 */
	public static function getLinkCount( LinksMigration $linksMigration, int $pageId, int $limit = 51 ): int {
		[ $blNamespace, $blTitle ] = $linksMigration->getTitleFields( 'pagelinks' );
		$dbr = self::getReplicaConnection();
		$queryInfo = $linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );
		$res = $dbr->newSelectQueryBuilder()
			->select( '1' )
			->tables( $queryInfo['tables'] )
			->joinConds( $queryInfo['joins'] )
			->join( 'page', null, [ "page_namespace = $blNamespace", "page_title = $blTitle" ] )
			->where( [
				'page_id' => $pageId,
				'page_is_redirect' => 0,
				// T313777 - only considering backlinks from mainspace pages
				'pl_from_namespace' => 0,
			] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet()->numRows();
		return $res;
	}

	/**
	 * Return an SQL primary database connection.
	 *
	 * @return IDatabase
	 */
	public static function getPrimaryConnection() {
		return MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
	}

	/**
	 * Return an SQL replica database connection.
	 *
	 * @return IReadableDatabase
	 */
	public static function getReplicaConnection() {
		return MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
	}
}
