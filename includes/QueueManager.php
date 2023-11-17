<?php

namespace MediaWiki\Extension\PageTriage;

use Status;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Class for adding, updating and deleting items from a queue of pages awaiting triage.
 */
class QueueManager {

	/** @var IConnectionProvider */
	private IConnectionProvider $dbProvider;

	/**
	 * @param IConnectionProvider $dbProvider
	 */
	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * @param QueueRecord $queueRecord
	 * @return Status OK if the row was added, not OK otherwise.
	 */
	public function insert( QueueRecord $queueRecord ): Status {
		$status = new Status();
		$queueRecordData = $queueRecord->jsonSerialize();
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->insert(
			'pagetriage_page',
			$queueRecordData,
			__METHOD__,
			// 'IGNORE' is needed for fields like ptrp_tags_updated which will not be
			// present on a new record.
			[ 'IGNORE' ]
		);
		$status->setOK( $dbw->affectedRows() === 1 );
		return $status;
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
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$status = new Status();
		if ( !$pageIds ) {
			return $status;
		}
		// TODO: Factor out ArticleMetadata into value object / manager.
		$articleMetadata = new ArticleMetadata( $pageIds );
		$dbw->startAtomic( __METHOD__ );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageIds ] )
			->caller( __METHOD__ )
			->execute();
		$status->setOK( count( $pageIds ) === $dbw->affectedRows() );
		// TODO: Is "ArticleMetadata" used/useful without the core queue data in pagetriage_page?
		//  if it isn't, we could make QueueManager handle create/update/delete for the page table
		//  and metadata.
		$articleMetadata->deleteMetadata();
		$dbw->endAtomic( __METHOD__ );
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
