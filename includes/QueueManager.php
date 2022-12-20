<?php

namespace MediaWiki\Extension\PageTriage;

use IDatabase;
use Status;

/**
 * Class for adding, updating and deleting items from a queue of pages awaiting triage.
 */
class QueueManager {

	/** @var IDatabase */
	private IDatabase $dbw;

	/**
	 * @param IDatabase $dbw
	 */
	public function __construct( IDatabase $dbw ) {
		$this->dbw = $dbw;
	}

	/**
	 * Delete an item by page ID.
	 *
	 * @param int $pageId
	 * @return Status OK if a page was deleted, not OK otherwise.
	 */
	public function deleteByPageId( int $pageId ): Status {
		return $this->deleteByPageIds( [ $pageId ] );
	}

	/**
	 * @param int[] $pageIds
	 * @return Status OK if all pages were deleted, not OK otherwise.
	 */
	public function deleteByPageIds( array $pageIds ): Status {
		$status = new Status();
		if ( !$pageIds ) {
			return $status;
		}
		// TODO: Factor out ArticleMetadata into value object / manager.
		$articleMetadata = new ArticleMetadata( $pageIds );
		$this->dbw->startAtomic( __METHOD__ );
		$this->dbw->delete(
			'pagetriage_page',
			[ 'ptrp_page_id' => $pageIds ],
			__METHOD__,
		);
		$status->setOK( count( $pageIds ) === $this->dbw->affectedRows() );
		// TODO: Is "ArticleMetadata" used/useful without the core queue data in pagetriage_page?
		//  if it isn't, we could make QueueManager handle create/update/delete for the page table
		//  and metadata.
		$articleMetadata->deleteMetadata();
		$this->dbw->endAtomic( __METHOD__ );
		return $status;
	}

	/**
	 * Check if a namespace is managed by PageTriage. This perhaps belongs better in
	 * another service.
	 *
	 * @param int $namespace
	 * @return bool True if the article is in a namespace managed by PageTriage.
	 */
	public function isPageTriageNamespace( int $namespace ): bool {
		// TODO: Factor PageTriageUtil::getNamespaces into this service.
		return in_array( $namespace, PageTriageUtil::getNamespaces() );
	}

}
