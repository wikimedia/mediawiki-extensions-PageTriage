<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Tags for AfC submission state.
 */
class ArticleCompileAfcTag extends ArticleCompileInterface {

	const UNSUBMITTED = 1;
	const PENDING = 2;
	const UNDER_REVIEW = 3;
	const DECLINED = 4;

	/**
	 * ArticleCompileAfcTag constructor.
	 * @param array $pageId
	 * @param int $componentDb
	 * @param array|null $articles
	 */
	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

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
		foreach ( $this->mPageId as $pageId ) {
			// Default to unsubmitted state; will be overridden if relevant category is present.
			$this->metadata[$pageId]['afc_state'] = self::UNSUBMITTED;

			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( !$parserOutput ) {
				continue;
			}

			$categories = array_keys( $parserOutput->getCategories() );
			foreach ( self::getAfcCategories() as $afcStateValue => $afcCategory ) {
				if ( in_array( $afcCategory, $categories ) ) {
					$this->metadata[$pageId]['afc_state'] = $afcStateValue;

					// Drafts re-use the ptrp_reviewed_updated to serve as the time of the last
					// submission or last decline. See T195547
					if ( in_array( $afcStateValue, [ self::PENDING, self::DECLINED ] ) ) {
						$this->metadata[$pageId]['update_reviewed_timestamp'] = true;
					}

					// Only set the first found category (highest priority one).
					break;
				}
			}
		}

		return true;
	}

}
