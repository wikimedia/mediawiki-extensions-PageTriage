<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

/**
 * Article link count
 */
class ArticleCompileLinkCount extends ArticleCompile {

	public function compile() {
		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		foreach ( $this->mPageId as $pageId ) {
			$res = PageTriageUtil::getLinkCount( $linksMigration, $pageId );
			$this->processEstimatedCount( $pageId, $res, 50, 'linkcount' );
		}
		$this->fillInZeroCount( 'linkcount' );
		return true;
	}

}
