<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\MediaWikiServices;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompile {

	public function compile() {
		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ $blNamespace, $blTitle ] = $linksMigration->getTitleFields( 'pagelinks' );
		$queryInfo = $linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( '1' )
				->tables( $queryInfo['tables'] )
				->joinConds( $queryInfo['joins'] )
				->join( 'page', null, [ "page_namespace = $blNamespace", "page_title = $blTitle" ] )
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
