<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace MediaWiki\Extension\PageTriage;

use IContextSource;
use ORES\ORESServices;
use ORES\Storage\ModelLookup;
use ORES\ThresholdLookup;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\ResultWrapper;

/**
 * Helper class to add metadata to articles in the list view of Special:NewPagesFeed.
 *
 * @package MediaWiki\Extension\PageTriage
 */
class OresMetadata {

	/**
	 * @var ThresholdLookup
	 */
	private $thresholdLookup;

	/**
	 * @var ModelLookup
	 */
	private $modelLookup;

	/**
	 * @var array
	 */
	private $oresModelClasses;

	/**
	 * @var IContextSource
	 */
	private $requestContext;

	/**
	 * Map of ORES class names from thresholds lookup mapped to translatable strings.
	 */
	private static $oresClassToMsgKey = [
		'Stub' => 'pagetriage-filter-stat-predicted-class-stub',
		'Start' => 'pagetriage-filter-stat-predicted-class-start',
		'C' => 'pagetriage-filter-stat-predicted-class-c',
		'B' => 'pagetriage-filter-stat-predicted-class-b',
		'GA' => 'pagetriage-filter-stat-predicted-class-good',
		'FA' => 'pagetriage-filter-stat-predicted-class-featured',
		'vandalism' => 'pagetriage-filter-stat-predicted-issues-vandalism',
		'attack' => 'pagetriage-filter-stat-predicted-issues-attack',
		'spam' => 'pagetriage-filter-stat-predicted-issues-spam',
		'OK' => 'pagetriage-filter-stat-predicted-issues-none',
	];

	/**
	 * OresMetadata constructor.
	 * @param ThresholdLookup $thresholdLookup
	 * @param ModelLookup $modelLookup
	 * @param array $oresModelClasses
	 * @param IContextSource $requestContext
	 * @param int[] $pageIds
	 */
	public function __construct(
		ThresholdLookup $thresholdLookup,
		ModelLookup $modelLookup,
		array $oresModelClasses,
		IContextSource $requestContext,
		$pageIds
	) {
		$this->thresholdLookup = $thresholdLookup;
		$this->modelLookup = $modelLookup;
		$this->oresModelClasses = $oresModelClasses;
		$this->requestContext = $requestContext;

		// Pre-fetch the ORES scores for all the pages of interest
		$this->scores = $this->fetchScores( $pageIds );
	}

	/**
	 * Create an instance of OresMetadata by getting dependencies from
	 * global variables and static ORESServices
	 *
	 * @param IContextSource $context
	 * @param int[] $pageIds
	 * @return OresMetadata
	 */
	public static function newFromGlobalState( IContextSource $context, $pageIds ) {
		global $wgOresModelClasses;
		return new self(
			ORESServices::getThresholdLookup(),
			ORESServices::getModelLookup(),
			$wgOresModelClasses,
			$context,
			$pageIds
		);
	}

	/**
	 * Get ORES metadata (wp10, draftquality) from the database for an article.
	 *
	 * @param int $pageId
	 * @return array
	 *   An array to merge in with other metadata for the article.
	 */
	public function getMetadata( $pageId ) {
		return $this->scores[ $pageId ];
	}

	/**
	 * @param float $probability
	 * @return string Name of the class corresponding to the given probability
	 */
	private function getWp10Class( $probability ) {
		$thresholds = $this->thresholdLookup->getThresholds( 'wp10' );
		foreach ( $thresholds as $className => $threshold ) {
			if ( $probability >= $threshold[ 'min' ] &&
				$probability <= $threshold[ 'max' ] ) {
				return $className;
			}
		}
	}

	/**
	 * @param int $classId
	 * @return string Name of the class corresponding to the given class id
	 */
	private function getDraftQualityClass( $classId ) {
		$modelClasses = array_flip( $this->oresModelClasses[ 'draftquality' ] );
		return $modelClasses[ $classId ];
	}

	/**
	 * @param string $className
	 * @return string Translated name of the class
	 */
	private function classToMessage( $className ) {
		$key = self::$oresClassToMsgKey[ $className ];
		return $this->requestContext->msg( $key )->text();
	}

	/**
	 * Fetch the 'wp10' and 'draftquality' scores for the given page ids
	 *
	 * @param int[] $pageIds
	 * @return array
	 */
	private function fetchScores( $pageIds ) {
		$pendingScore = $this->requestContext->msg(
			'pagetriage-filter-pending-ores-score' )->text();

		$scores = [];
		foreach ( $pageIds as $pageId ) {
			$scores[ $pageId ] = [
				'ores_wp10' => $pendingScore,
				'ores_draftquality' => $pendingScore,
			];
		}

		$result = $this->getORESScores( 'wp10', $pageIds );
		foreach ( $result as $row ) {
			$scores[$row->ptrp_page_id]['ores_wp10'] = $this->classToMessage(
				$this->getWp10Class( $row->oresc_probability ) );
		}

		$result = $this->getORESScores( 'draftquality', $pageIds, [ 'oresc_is_predicted' => 1 ] );
		foreach ( $result as $row ) {
			$scores[$row->ptrp_page_id]['ores_draftquality'] = $this->classToMessage(
				$this->getDraftQualityClass( $row->oresc_class ) );
		}

		return $scores;
	}

	/**
	 * Select ORES scores from the database.
	 *
	 * @param string $modelName
	 * @param int[] $pageIds
	 * @param array $extraConds
	 * @return array|IResultWrapper|ResultWrapper
	 */
	private function getORESScores( $modelName, $pageIds, $extraConds = [] ) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select(
			[
				'pagetriage_page',
				'page',
				'ores_classification',
			],
			[
				'ptrp_page_id',
				'oresc_probability', // used for wp10
				'oresc_class', // used for draftquality
			],
			[
				'oresc_model' => $this->modelLookup->getModelId( $modelName ),
				'ptrp_page_id' => $pageIds,
			] + $extraConds,
			__METHOD__,
			[],
			[
				'page' => [ 'INNER JOIN', 'ptrp_page_id=page_id' ],
				'ores_classification' => [ 'INNER JOIN', 'page_latest=oresc_rev' ],
			]
		);
		return $result ?: [];
	}
}
