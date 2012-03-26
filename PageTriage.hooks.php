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
	 * Remove the metadata we added when the article is deleted.
	 *
	 * 'ArticleDeleteComplete': after an article is deleted
	 * $article: the WikiPage that was deleted
	 * $user: the user that deleted the article
	 * $reason: the reason the article was deleted
	 * $id: id of the article that was deleted
	 */
	public static function onArticleDeleteComplete( $article, $user, $reason, $id ) {
		// delete everything
		$articleMetadata = new ArticleMetadata( array( $id ) );
		$articleMetadata->deleteMetadata();
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

	/**
	 * Add last time user visited the triage page to preferences.
	 * @param $user User object
	 * @param &$preferences Preferences object
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		$preferences['pagetriage-lastuse'] = array(
			'type' => 'hidden',
		);

		return true;
	}

	/**
	 * Adds "mark as patrolled" link to articles
	 *
	 * @param &$article Article object to show link for.
	 * @param &$outputDone Set if there is no more output to do.
	 * @param &$pcache Set if you want to use the parser cache.
	 * @return type description
	 */
	public static function onArticleViewHeader( &$article, &$outputDone, &$pcache ) {
		global $wgUser, $wgPageTriageMarkPatrolledLinkExpiry, $wgOut;

		$lastUse = $wgUser->getOption('pagetriage-lastuse');
		$lastUse = wfTimestamp( TS_UNIX, $lastUse );
		$now = wfTimestamp( TS_UNIX, wfTimestampNow() );

		$periodSince = $now - $lastUse;

		if ( !$lastUse || $periodSince > $wgPageTriageMarkPatrolledLinkExpiry ) {
			return true;
		}

		if ( ! PageTriageUtil::doesPageNeedTriage( $article ) ) {
			return true;
		}

		$wgOut->addModules( array('ext.pageTriage.article') );

		$msg = wfMessage( 'pagetriage-markpatrolled' )->parse();
		$html = Html::element( 'div', array( 'class' => 'mw-pagetriage-markpatrolled' ), $msg );

		$wgOut->addHTML( $html );

		return true;
	}
}
