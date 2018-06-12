<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article Deletion Tag
 */
class ArticleCompileDeletionTag extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public static function getDeletionTags() {
		return [
			'All_articles_proposed_for_deletion' => 'prod_status',
			'BLP_articles_proposed_for_deletion' => 'blp_prod_status',
			'Candidates_for_speedy_deletion' => 'csd_status',
			'Articles_for_deletion' => 'afd_status'
		];
	}

	public function compile() {
		$deletionTags = self::getDeletionTags();
		foreach ( $this->mPageId as $pageId ) {
			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( $parserOutput ) {
				$categories = $parserOutput->getCategories();
				foreach ( $deletionTags as $category => $tag ) {
					$this->metadata[$pageId][$tag] = isset( $categories[$category] ) ? '1' : '0';
				}
			}
		}
		return true;
	}

}
