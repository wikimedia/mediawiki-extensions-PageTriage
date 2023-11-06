<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompile {

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( '1' )
				->from( 'page' )
				->join( 'pagelinks', null, [ 'page_namespace = pl_namespace', 'page_title = pl_title' ] )
				->where( [
					'page_id' => $pageId,
					// T313777 - only considering backlinks from mainspace pages
					'pl_from_namespace = 0',
				] )
				->limit( 51 )
				->caller( __METHOD__ )->fetchResultSet();
			$this->processEstimatedCount( $pageId, $res, 50, 'linkcount' );
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}
