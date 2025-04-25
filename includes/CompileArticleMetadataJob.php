<?php

namespace MediaWiki\Extension\PageTriage;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileProcessor;
use MediaWiki\JobQueue\Job;

class CompileArticleMetadataJob extends Job {

	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'compileArticleMetadata', $title, $params );
	}

	/**
	 * @inheritDoc
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();
		return $info['params']['pageId'];
	}

	/** @inheritDoc */
	public function ignoreDuplicates() {
		return true;
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		// Metadata now exists in the replica, so there is no need to save.
		$metadata = ArticleMetadata::getMetadataForArticles( [ $this->params['pageId'] ] );
		if ( isset( $metadata[ $this->params['pageId'] ] ) &&
			ArticleMetadata::isValidMetadata( $metadata[ $this->params['pageId'] ] )
		) {
			return true;
		}

		// Validate the page ID before proceeding.
		$acp = ArticleCompileProcessor::newFromPageId( [ $this->params['pageId'] ], false,
			DB_REPLICA );
		if ( !$acp ) {
			// The article could not be found in the PageTriage queue in the replica, so perhaps it
			// was deleted at some time before this job was invoked.
			return true;
		}
		// Use the replica for compilation of all components.
		$config = ArticleCompileProcessor::getSafeComponentDbConfigForCompilation()
			+ [ 'BasicData' => DB_REPLICA ];
		$acp->configComponentDb( $config );
		$acp->compileMetadata();
		return true;
	}
}
