<?php

class PageTriageHooks {

	/**
	 * Mark a page as untriaged after moving the page if the new title is in main namespace 
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/SpecialMovepageAfterMove
	 * @param $movePage: MovePageForm object
	 * @param $oldTitle: old title object
	 * @param $newTitle: new title object
	 * @return bool
	 */
	public static function onSpecialMovepageAfterMove( $movePage, &$oldTitle, &$newTitle ) {
		$pageId = $newTitle->getArticleID();

		if ( $newTitle->getNamespace() === NS_MAIN ) {
			self::addToPageTriageQueue( $pageId );
		}

		//@Todo - find a solution for partial data compilation
		$articleMetadata = new ArticleMetadata( array( $pageId ) );
		$articleMetadata->compileMetadata();

		return true;
	}

	/**
	 * Check if a page is created from a redirect page, then insert into it PageTriage Queue
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 * @param $article: the WikiPage edited
	 * @param $rev: the new revision
	 * @param $baseID: the revision ID this was based off, if any
	 * @param $user: the editing user
	 * @return bool
	 */
	public static function onNewRevisionFromEditComplete( $article, $rev, $baseID, $user ) {
		$prev = $rev->getPrevious();
		if ( $prev && !$article->isRedirect() && $article->isRedirect( $prev->getRawText() ) ) {
			self::addToPageTriageQueue( $article->getId() );	
		}
		return true;
	}

	/**
	 * Insert new page into PageTriage Queue
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/ArticleInsertComplete
	 * @param $article: WikiPage created
	 * @param $user: User creating the article
	 * @param $text: New content
	 * @param $summary: Edit summary/comment
	 * @param $isMinor: Whether or not the edit was marked as minor
	 * @param $isWatch: (No longer used)
	 * @param $section: (No longer used)
	 * @param $flags: Flags passed to Article::doEdit()
	 * @param $revision: New Revision of the article
	 * @return bool
	 */
	public static function onArticleInsertComplete( $article, $user, $text, $summary, $isMinor, $isWatch, $section, $flags, $revision ) {
		self::addToPageTriageQueue( $article->getId() );	

		return true;
	}

	/**
	 * Compile the metadata on successful save, this is only for page in PageTriage Queue already
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/ArticleSaveComplete
	 * @param $article WikiPage
	 * @param $user
	 * @param $text
	 * @param $summary
	 * @param $minoredit
	 * @param $watchthis
	 * @param $sectionanchor
	 * @param $flags
	 * @param $revision
	 * @param $status
	 * @param $baseRevId
	 * @return bool
	 */
	public static function onArticleSaveComplete( $article, $user, $text, $summary, $minoredit, $watchthis, $sectionanchor, $flags, $revision, $status, $baseRevId ) {
		$articleMetadata = new ArticleMetadata( array( $article->getId() ) );
		$articleMetadata->compileMetadata();
		return true;
	}

	/**
	 * Add page to page triage queue
	 */
	private static function addToPageTriageQueue( $pageId ) {
		$dbw = wfGetDB( DB_MASTER );
			
		$row = array(
				'ptrp_page_id' => $pageId,
				'ptrp_triaged' => 0,
				'ptrp_timestamp' => $dbw->timestamp( wfTimestampNow() )
			);

		$dbw->replace( 'pagetriage_page', array( 'ptrp_page_id' ), $row, __METHOD__ );	
	}

}
