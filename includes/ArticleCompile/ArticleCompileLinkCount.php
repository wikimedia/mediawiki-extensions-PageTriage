<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompile {

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$this->processEstimatedCount(
				$pageId,
				[ 'page', 'pagelinks' ],
				[
					'page_id' => $pageId,
					'page_namespace = pl_namespace',
					'page_title = pl_title',
					// T313777 - only considering backlinks from mainspace pages
					'pl_from_namespace = 0',
				],
				$maxNumToProcess = 50,
				'linkcount'
			);
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}
