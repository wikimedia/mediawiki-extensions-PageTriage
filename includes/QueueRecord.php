<?php

namespace MediaWiki\Extension\PageTriage;

use JsonSerializable;

/**
 * Value object for PageTriage queue items.
 */
class QueueRecord implements JsonSerializable {

	/**
	 * The article is in an unreviewed state. "Needs review" is probably more accurate, as the
	 * article can go between reviewed and back to unreviewed state if a PageTriage user marks
	 * it as "unreviewed".
	 */
	public const REVIEW_STATUS_UNREVIEWED = 0;
	/**
	 * The article has been reviewed by a Special:NewPagesFeed user.
	 */
	public const REVIEW_STATUS_REVIEWED = 1;
	/**
	 * Set when an article is added to the page triage queue via the MarkPatrolledComplete hook.
	 */
	public const REVIEW_STATUS_PATROLLED = 2;
	/**
	 * The article was auto-patrolled.
	 */
	public const REVIEW_STATUS_AUTOPATROLLED = 3;

	public const VALID_REVIEW_STATUSES = [
		self::REVIEW_STATUS_UNREVIEWED,
		self::REVIEW_STATUS_REVIEWED,
		self::REVIEW_STATUS_PATROLLED,
		self::REVIEW_STATUS_AUTOPATROLLED
	];

	/** @var int */
	private int $pageId;
	/** @var int */
	private int $reviewedStatus;
	/** @var bool */
	private bool $isNominatedForDeletion;
	/** @var string */
	private string $createdTimestamp;
	/** @var null|string */
	private ?string $tagsUpdatedTimestamp;
	/** @var string */
	private string $reviewedUpdatedTimestamp;
	/** @var int */
	private int $lastReviewedByUserId;

	/**
	 * @param int $pageId
	 * @param int $reviewedStatus
	 * @param bool $isNominatedForDeletion
	 * @param string $createdTimestamp
	 * @param null|string $tagsUpdatedTimestamp
	 * @param string $reviewedUpdatedTimestamp
	 * @param int $lastReviewedByUserId
	 */
	public function __construct(
		int $pageId,
		int $reviewedStatus,
		bool $isNominatedForDeletion,
		string $createdTimestamp,
		?string $tagsUpdatedTimestamp,
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
	 * @return int An integer reflecting the review status. Valid values are in self::VALID_REVIEW_STATUSES
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
	 * @return null|string TS_MW timestamp of when the metadata for this record was updated, or null
	 *  if that has not yet occurred.
	 */
	public function getTagsUpdatedTimestamp(): ?string {
		return $this->tagsUpdatedTimestamp;
	}

	/**
	 * @return string TW_MW timestamp of when the article was last reviewed.
	 */
	public function getReviewedUpdatedTimestamp(): string {
		return $this->reviewedUpdatedTimestamp;
	}

	/**
	 * @return array Returns an array suitable for insertion into the pagetriage_page table. Arguably
	 *  such a mapping shouldn't be defined in this class, but creating e.g. a formatter class for doing
	 *  so seems like unnecessary paperwork.
	 */
	public function jsonSerialize(): array {
		$data = [
			'ptrp_page_id' => $this->getPageId(),
			'ptrp_reviewed' => $this->getReviewedStatus(),
			'ptrp_deleted' => (int)$this->isNominatedForDeletion(),
			'ptrp_created' => $this->getCreatedTimestamp(),
			'ptrp_tags_updated' => $this->getTagsUpdatedTimestamp(),
			'ptrp_reviewed_updated' => $this->getReviewedUpdatedTimestamp(),
			'ptrp_last_reviewed_by' => $this->getLastReviewedByUserId(),
		];
		// Tags are set separately via ArticleMetadata so exclude this field on insert, as we don't know
		// the timestamp value of when the tags will have been updated.
		unset( $data['ptrp_tags_updated'] );
		return $data;
	}
}
