<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

/**
 * Whether or not the page was previously deleted.
 * FIXME: Ideally we wouldn't redundantly re-query the deletion log on every edit,
 *   but it's a lot of work to get around this, and we're querying the replica in
 *   a deferred update anyway.
 */
class ArticleCompileRecreated extends ArticleCompile {

	/**
	 * Implements ArticleCompileInterface::compile(), called when generating tags.
	 * @return bool
	 */
	public function compile() {
		$conds = [
			'page_id' => $this->mPageId,
			'log_type' => 'delete',

			// We only care about full-page deletions, not revision deletions.
			// 'delete_redir' and 'delete_redir2' are the same as 'delete' except for
			// redirects, which we do want.
			'log_action' => [ 'delete', 'delete_redir', 'delete_redir2' ],
		];

		$res = $this->db->newSelectQueryBuilder()
			->select( [ 'DISTINCT page_id' ] )
			->from( 'logging' )
			->join( 'page', 'page', [ 'page_title = log_title', 'page_namespace = log_namespace' ] )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

		$wasPreviouslyDeleted = [];

		foreach ( $res as $row ) {
			$wasPreviouslyDeleted[$row->page_id] = true;
		}

		foreach ( $this->mPageId as $pageId ) {
			if ( array_key_exists( $pageId, $wasPreviouslyDeleted ) ) {
				$this->metadata[$pageId]['recreated'] = true;
			} else {
				$this->metadata[$pageId]['recreated'] = false;
			}
		}

		return true;
	}

}
