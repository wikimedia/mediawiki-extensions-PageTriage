<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompileInterface {

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount(
					$pageId,
					[ 'page', 'pagelinks' ],
					[
						'page_id' => $pageId,
						'page_namespace = pl_namespace',
						'page_title = pl_title',
						'pl_from_namespace = 0' // T313777 - only considering backlinks from mainspace pages
					],
					$maxNumToProcess = 50,
					'linkcount'
			);
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}
