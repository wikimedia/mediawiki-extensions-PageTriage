<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article Deletion Tag
 */
class ArticleCompileDeletionTag extends ArticleCompile {

	/**
	 * Returns the category to deletion tag map
	 *
	 * @return array
	 */
	public static function getDeletionTags() {
		return [
			'All_articles_proposed_for_deletion' => 'prod_status',
			'BLP_articles_proposed_for_deletion' => 'blp_prod_status',
			'Candidates_for_speedy_deletion' => 'csd_status',
			// The next two are both treated as deletion nominations,
			// because RfD pages are not actual redirects. See T157046.
			'Articles_for_deletion' => 'afd_status',
			'All_redirects_for_discussion' => 'afd_status',
		];
	}

	/** @inheritDoc */
	public function compile() {
		$deletionTags = self::getDeletionTags();
		foreach ( $this->mPageId as $pageId ) {
			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( $parserOutput ) {
				$deleted = false;
				foreach ( $deletionTags as $category => $tag ) {
					if ( !isset( $this->metadata[$pageId][$tag] )
						|| $this->metadata[$pageId][$tag] === "0"
					) {
						$hasCategory = $parserOutput->getCategorySortKey( $category ) !== null;
						$this->metadata[$pageId][$tag] = $hasCategory ? '1' : '0';
						$deleted = $hasCategory || $deleted;
					}
				}
				$this->metadata[$pageId]['deleted'] = $deleted;
			}
		}
		return true;
	}

}
