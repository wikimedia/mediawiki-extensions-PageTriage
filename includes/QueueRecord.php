<?php

namespace MediaWiki\Extension\PageTriage;

use JsonSerializable;

/**
 * Value object for PageTriage queue items.
 */
class QueueRecord implements JsonSerializable {

	/** @var int */
	private int $pageId;
	/** @var int */
	private int $reviewedStatus;
	/** @var bool */
	private bool $isNominatedForDeletion;
	/** @var string */
	private string $createdTimestamp;
	/** @var string */
	private string $tagsUpdatedTimestamp;
	/** @var string */
	private string $reviewedUpdatedTimestamp;
	/** @var int */
	private int $lastReviewedByUserId;

	/**
	 * @param int $pageId
	 * @param int $reviewedStatus
	 * @param bool $isNominatedForDeletion
	 * @param string $createdTimestamp
	 * @param string $tagsUpdatedTimestamp
	 * @param string $reviewedUpdatedTimestamp
	 * @param int $lastReviewedByUserId
	 */
	public function __construct(
		int $pageId,
		int $reviewedStatus,
		bool $isNominatedForDeletion,
		string $createdTimestamp,
		string $tagsUpdatedTimestamp,
		string $reviewedUpdatedTimestamp,
		int $lastReviewedByUserId
	) {
		$this->pageId = $pageId;
		$this->reviewedStatus = $reviewedStatus;
		$this->isNominatedForDeletion = $isNominatedForDeletion;
		$this->createdTimestamp = $createdTimestamp;
		$this->tagsUpdatedTimestamp = $tagsUpdatedTimestamp;
		$this->reviewedUpdatedTimestamp = $reviewedUpdatedTimestamp;
		$this->lastReviewedByUserId = $lastReviewedByUserId;
	}

	/**
	 * @return int The ID of the user who last reviewed the article associated with this item.
	 */
	public function getLastReviewedByUserId(): int {
		return $this->lastReviewedByUserId;
	}

	/**
	 * @return int The page ID associated with the queue record's article.
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * @return int An integer reflecting the review status, valid values range from 0-3.
	 *  TODO: Document what the review statuses are.
	 */
	public function getReviewedStatus(): int {
		return $this->reviewedStatus;
	}

	/**
	 * @return bool true if the associated page has been nominated for deletion, false otherwise.
	 */
	public function isNominatedForDeletion(): bool {
		return $this->isNominatedForDeletion;
	}

	/**
	 * @return string TS_MW timestamp of when the queue record was created.
	 */
	public function getCreatedTimestamp(): string {
		return $this->createdTimestamp;
	}

	/**
	 * @return string TS_MW timestamp of when the metadata for this record was updated.
	 */
	public function getTagsUpdatedTimestamp(): string {
		return $this->tagsUpdatedTimestamp;
	}

	/**
	 * @return string TW_MW timestamp of when the article was last reviewed.
	 */
	public function getReviewedUpdatedTimestamp(): string {
		return $this->reviewedUpdatedTimestamp;
	}

	public function jsonSerialize(): array {
		return [
			'ptrp_page_id' => $this->getPageId(),
			'ptrp_reviewed' => $this->getReviewedStatus(),
			'ptrp_deleted' => (int)$this->isNominatedForDeletion(),
			'ptrp_created' => $this->getCreatedTimestamp(),
			'ptrp_tags_updated' => $this->getTagsUpdatedTimestamp(),
			'ptrp_reviewed_updated' => $this->getReviewedUpdatedTimestamp(),
			'ptrp_last_reviewed_by' => $this->getLastReviewedByUserId(),
		];
	}
}
