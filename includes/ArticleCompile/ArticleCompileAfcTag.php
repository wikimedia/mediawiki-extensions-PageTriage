<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Tags for AfC submission state.
 */
class ArticleCompileAfcTag extends ArticleCompileInterface {

	public const UNSUBMITTED = 1;
	public const PENDING = 2;
	public const UNDER_REVIEW = 3;
	public const DECLINED = 4;

	/**
	 * @var array Associative array of page id to afc state. e.g. [ '123' => 2, '124' => 4 ]
	 */
	private $previousAfcStates;

	/**
	 * AFC categories in priority order (i.e. the first found will be used if a page is in more
	 * than one of these categories). UNSUBMITTED is not actually a category, rather the absence
	 * of the other categories.
	 * @return string[]
	 */
	public static function getAfcCategories() {
		return [
			self::UNDER_REVIEW => 'Pending_AfC_submissions_being_reviewed_now',
			self::PENDING => 'Pending_AfC_submissions',
			self::DECLINED => 'Declined_AfC_submissions',
		];
	}

	/**
	 * Implements ArticleCompileInterface::compile(), called when generating tags.
	 * @return bool
	 */
	public function compile() {
		$this->previousAfcStates = $this->loadPreviousAfcStates( $this->mPageId );
		foreach ( $this->mPageId as $pageId ) {
			// Default to unsubmitted state; will be overridden if relevant category is present.
			$this->metadata[$pageId]['afc_state'] = self::UNSUBMITTED;

			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( !$parserOutput ) {
				continue;
			}

			$previousState = $this->previousAfcStates[ $pageId ] ?? self::UNSUBMITTED;

			// Try to identify the current afc_state of the page
			// and request updating of 'update_reviewed_timestamp'
			// if the state has changed.
			$categories = $parserOutput->getCategoryNames();
			foreach ( self::getAfcCategories() as $afcStateValue => $afcCategory ) {
				if ( in_array( $afcCategory, $categories ) ) {
					$this->metadata[$pageId]['afc_state'] = $afcStateValue;

					// Drafts re-use the ptrp_reviewed_updated to serve as the time of the last
					// submission or last decline. See T195547
					if (
						in_array( $afcStateValue, [ self::PENDING, self::DECLINED ] ) &&
						$afcStateValue !== $previousState
					) {
						$this->metadata[$pageId]['update_reviewed_timestamp'] = true;
					}

					// Only set the first found category (highest priority one).
					break;
				}
			}
		}

		return true;
	}

	/**
	 * @param int[] $pageIds
	 *
	 * @return int[]
	 */
	private function loadPreviousAfcStates( $pageIds ) {
		$states = [];
		$afcStateTagId = $this->db->selectField(
			'pagetriage_tags', 'ptrt_tag_id', [ 'ptrt_tag_name' => 'afc_state' ], __METHOD__
		);
		if ( $afcStateTagId ) {
			$result = $this->db->select(
				'pagetriage_page_tags',
				[ 'pageId' => 'ptrpt_page_id', 'afcState' => 'ptrpt_value' ],
				[ 'ptrpt_page_id' => $pageIds, 'ptrpt_tag_id' => $afcStateTagId ],
				__METHOD__
			);
			foreach ( $result as $row ) {
				$states[$row->pageId] = intval( $row->afcState );
			}
		}
		return $states;
	}

}
