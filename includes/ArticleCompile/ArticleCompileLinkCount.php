<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount(
					$pageId,
					[ 'page', 'pagelinks' ],
					[
						'page_id' => $pageId,
						'page_namespace = pl_namespace',
						'page_title = pl_title'
					],
					$maxNumToProcess = 50,
					'linkcount'
			);
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}
