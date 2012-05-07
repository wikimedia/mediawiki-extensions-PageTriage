<?php

class PageTriageHooks {

	/**
	 * Mark a page as unreviewed after moving the page if the new title is in main namespace
	 * Note: Page will be automatically marked as triaged for users with autopatrol right
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/SpecialMovepageAfterMove
	 * @param $movePage: MovePageForm object
	 * @param $oldTitle: old title object
	 * @param $newTitle: new title object
	 * @return bool
	 */
	public static function onSpecialMovepageAfterMove( $movePage, &$oldTitle, &$newTitle ) {
		$pendingId = array();

		if ( $newTitle->getNamespace() === NS_MAIN ) {
			$pageId = $newTitle->getArticleID();
			// New record added to pagetriage queue, compile all data
			if ( self::addToPageTriageQueue( $pageId, $newTitle ) ) {
				$pendingId[] = $pageId;
			} else {
				$acp = ArticleCompileProcessor::newFromPageId( array( $pageId ) );
				if ( $acp ) {
					// compile only basic data
					$acp->registerComponent( 'BasicData' );
					$acp->compileMetadata();
				}
			}
		}

		// Check if there is a new page id for redirect left behind
		$oldPageId = $oldTitle->getArticleID();
		if ( $oldPageId ) {
			self::addToPageTriageQueue( $oldPageId, $oldTitle );
			$pendingId[] = $oldPageId;
		}

		// Compile all the data in $pendingId
		if ( $pendingId ) {
			$acp = ArticleCompileProcessor::newFromPageId( $pendingId );
			if ( $acp ) {
				$acp->compileMetadata();
			}
		}
		return true;
	}

	/**
	 * Check if a page is created from a redirect page, then insert into it PageTriage Queue
	 * Note: Page will be automatically marked as triaged for users with autopatrol right
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
			self::addToPageTriageQueue( $article->getId(), $article->getTitle(), $user );
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
		self::addToPageTriageQueue( $article->getId(), $article->getTitle(), $user );

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
		$acp = ArticleCompileProcessor::newFromPageId( array( $article->getId() ) );
		if ( $acp ) {
			// Register the article object so we can get the content and other useful information 
			// this is primarily for replication delay from slave
			$acp->registerArticle( $article );
			$acp->compileMetadata();
		}

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
		$pageTriage = new PageTriage( $id );
		$pageTriage->deleteFromPageTriage();
		return true;
	}

	/**
	 * Add page to page triage queue
	 */
	private static function addToPageTriageQueue( $pageId, $title, $user = null ) {
		global $wgUser, $wgUseRCPatrol, $wgUseNPPatrol;
		
		$user = is_null( $user ) ? $wgUser : $user;

		$patrolled = ( $wgUseRCPatrol || $wgUseNPPatrol ) && !count(
					$title->getUserPermissionsErrors( 'autopatrol', $user ) );

		$pageTriage = new PageTriage( $pageId );
		// Without autopatrol right, we consider the system updates the triage status to '0' or adds a brand new
		// record with '0' triage status to the queue, hence we should not pass a user for logging
		if ( $patrolled ) {
			return $pageTriage->addToPageTriageQueue( '1', $user );
		} else {
			return $pageTriage->addToPageTriageQueue( '0' );
		}
		
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
	 * Determines whether to show no-index for the article specified, show no-index if
	 * 1. the page contains a template listed in $wgPageTriageNoIndexTemplates page
	 * 2. the page is in triage queue and has not been triaged
	 * @param $article Article
	 * @return bool
	 */
	private static function shouldShowNoIndex( $article ) {
		global $wgPageTriageNoIndexTemplates;

		if ( $wgPageTriageNoIndexTemplates && $article->mParserOutput instanceof ParserOutput) {
			$noIndexTitle = Title::newFromText( $wgPageTriageNoIndexTemplates, NS_MEDIAWIKI );
			if ( $noIndexTitle ) {
				$noIndexArticle = WikiPage::newFromID( $noIndexTitle->getArticleID() );
				if ( $noIndexArticle ) {
					$noIndexTemplateText = $noIndexArticle->getText();
					if ( $noIndexTemplateText ) {
						// Collect all the noindex template names into an array
						$noIndexTemplates = explode( '|', $noIndexTemplateText );
						// Properly format the template names to match what getTemplates() returns
						$noIndexTemplates = array_map( array( 'PageTriageHooks', 'formatTemplateName' ), $noIndexTemplates );
						foreach ( $article->mParserOutput->getTemplates() as $ns => $templates ) {
							foreach ( $templates as $template => $pageId ) {
								if ( in_array( $template, $noIndexTemplates ) ) {
									return true;
								}
							}
						}
					}
				}
			}
		}

		if ( PageTriageUtil::doesPageNeedTriage( $article ) ) {
			return true;
		}

		return false;
	}
	
	/**
	 * Formats a template name to match the format returned by getTemplates()
	 * @param $template string
	 * @return string
	 */
	private static function formatTemplateName( $template ) {
		$template = ucfirst( trim( $template ) );
		$template = str_replace( ' ', '_', $template );
		return $template;
	}

	/**
	 * Adds "mark as patrolled" link to articles
	 *
	 * @param &$article Article object to show link for.
	 * @param &$outputDone Set if there is no more output to do.
	 * @param &$pcache Set if you want to use the parser cache.
	 * @return type description
	 */
	public static function onArticleViewFooter( $article ) {
		global $wgUser, $wgPageTriageMarkPatrolledLinkExpiry, $wgOut, $wgRequest;
		// the presence of rcid means this is coming from Special:NewPages,
		// and hence don't make any interference, this also applies to
		// user with no right
		if ( $wgRequest->getVal( 'rcid' ) ) {
			return true;
		}

		// Overwrite the noindex rule defined in Article::view(), this also affects main namespace
		if ( self::shouldShowNoIndex( $article ) ) {
			$wgOut->setRobotPolicy( 'noindex,nofollow' );
		}

		// don't show anything for user with no patrol right
		if ( !$article->getTitle()->quickUserCan('patrol') ) {
			return true;
		}

		$lastUse = $wgUser->getOption( 'pagetriage-lastuse' );
		if ( $lastUse ) {
			$lastUse = wfTimestamp( TS_UNIX, $lastUse );
			$now = wfTimestamp( TS_UNIX, wfTimestampNow() );
			$periodSince = $now - $lastUse;
		}

		if ( !$lastUse || $periodSince > $wgPageTriageMarkPatrolledLinkExpiry ) {
			return true;
		}

		$status = PageTriageUtil::doesPageNeedTriage( $article );
		if ( $status === true ) {
			// Users without the autopatrol right can't review their own pages
			if ( is_callable( array( 'WikiPage', 'getOldestRevision' ) )
				&& $wgUser->getId() == $article->getOldestRevision()->getUser()
				&& !$wgUser->isAllowed( 'autopatrol' )
			) {
				$msg = wfMessage( 'pagetriage-self-review-error' )->escaped();
			} else {
				// show 'Mark as reviewed' link
				$msg = wfMessage( 'pagetriage-markpatrolled' )->text();
				$msg = Html::element( 'a', array( 'href' => '#', 'class' => 'mw-pagetriage-markpatrolled-link' ), $msg );
			}
		} else if ( $status === false ) {
			// show 'Reviewed' text
			$msg = wfMessage( 'pagetriage-reviewed' )->escaped();
		} else {
			// Do nothing as this page is not in PageTriage queue
			return true;
		}

		$wgOut->addModules( array('ext.pageTriage.article') );

		$html = Html::rawElement( 'div', array( 'class' => 'mw-pagetriage-markpatrolled' ), $msg );

		$wgOut->addHTML( $html );

		return true;
	}

	/**
	 * Sync records from patrol queue to triage queue 
	 *
	 * 'MarkPatrolledComplete': after an edit is marked patrolled
	 * $rcid: ID of the revision marked as patrolled
	 * $user: user (object) who marked the edit patrolled
	 * $wcOnlySysopsCanPatrol: config setting indicating whether the user
	 * must be a sysop to patrol the edit
	 */
	public static function onMarkPatrolledComplete( $rcid, &$user, $wcOnlySysopsCanPatrol ) {
		$rc = RecentChange::newFromId( $rcid );

		if ( $rc ) {
			$pt = new PageTriage( $rc->getAttribute( 'rc_cur_id' ) );
			if ( $pt->addToPageTriageQueue( '1', $user, $fromRc = true ) ) {
				// Compile metadata for new page triage record
				$acp = ArticleCompileProcessor::newFromPageId( array( $rc->getAttribute( 'rc_cur_id' ) ) );
				if ( $acp ) {
					$acp->compileMetadata();
				}
			}
		}

		return true;
	}

	/**
	 * BeforePageDisplay hook
	 */
	public static function beforePageDisplay( $out ) {
		$out->addModules( 'ext.pageTriage.startup' );
		return true;
	}

	/**
	 * Update Article metadata when a user gets blocked 
	 *
	 * 'BlockIpComplete': after an IP address or user is blocked
	 * $block: the Block object that was saved
	 * $performer: the user who did the block (not the one being blocked)
	 */
	public static function onBlockIpComplete( $block, $performer ) {
		PageTriageUtil::updateMetadataOnBlockChange( $block );
		return true;
	}
}
