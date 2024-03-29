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
			// 'delete_redir' is the same as 'delete' except for redirects, which we do want.
			'log_action' => [ 'delete', 'delete_redir' ],
		];

		$res = $this->db->newSelectQueryBuilder()
			->select( [ 'DISTINCT page_id' ] )
			->from( 'logging' )
			->join( 'page', 'page', [ 'page_title = log_title', 'page_namespace = log_namespace' ] )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

		// The recreated tag will never change, so we don't need to set false
		// for the pages that aren't recreations.
		foreach ( $res as $row ) {
			$this->metadata[$row->page_id]['recreated'] = true;
		}

		return true;
	}

}
