<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article category count
 */
class ArticleCompileCategoryCount extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$parserOutput = $this->getParserOutputByPageId( $pageId );
			if ( $parserOutput ) {
				$this->metadata[$pageId]['category_count'] = count( $parserOutput->getCategories() );
			}
		}
		$this->fillInZeroCount( 'category_count' );
		return true;
	}

}
