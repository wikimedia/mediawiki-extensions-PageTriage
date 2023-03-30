<?php

namespace MediaWiki\Extension\PageTriage\HookHandlers;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * n.b. service dependencies cannot be injected for hooks handled by this class.
 */
class Schema implements LoadExtensionSchemaUpdatesHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . "/../../sql";
		$dbType = $updater->getDB()->getType();
		$updater->addExtensionTable( 'pagetriage_tags', "$base/$dbType/tables-generated.sql" );

		$updater->addExtensionUpdate( [
			[ __CLASS__, 'doOnSchemaUpdatesPopulatePageTriageTags' ],
		] );

		// 1.35
		$updater->dropExtensionIndex(
			'pagetriage_page_tags',
			'ptrpt_page_tag_id',
			$base . '/PageTriagePageTagsPatch-pk.sql'
		);

		// 1.39
		if ( $dbType === 'mysql' ) {
			$updater->modifyExtensionField(
				'pagetriage_page',
				'ptrp_reviewed_updated',
				$base . '/patch-pagetriage_page-timestamps.sql'
			);
			// T325519
			$updater->dropExtensionTable( 'pagetriage_log' );
		}

		// T333389
		$updater->modifyExtensionField(
			'pagetriage_page',
			'ptrp_tags_updated',
			$base . '/' . $dbType . '/patch_ptrp_tags_updated_nullable.sql'
		);
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public static function doOnSchemaUpdatesPopulatePageTriageTags( DatabaseUpdater $updater ): void {
		$updateKey = 'populatePageTriageTags-1.34';
		if ( $updater->updateRowExists( $updateKey ) ) {
			$updater->output( "...default pagetriage tags already added\n" );
			return;
		}

		$updater->output( "Adding pagetriage tags...\n" );
		$dbw = $updater->getDB();
		$dbw->insert(
			'pagetriage_tags', [
				[ 'ptrt_tag_name' => 'linkcount', 'ptrt_tag_desc' => 'Number of inbound links' ],
				[ 'ptrt_tag_name' => 'category_count', 'ptrt_tag_desc' => 'Category mapping count' ],
				[ 'ptrt_tag_name' => 'csd_status', 'ptrt_tag_desc' => 'CSD status' ],
				[ 'ptrt_tag_name' => 'prod_status', 'ptrt_tag_desc' => 'PROD status' ],
				[ 'ptrt_tag_name' => 'blp_prod_status', 'ptrt_tag_desc' => 'BLP PROD status' ],
				[ 'ptrt_tag_name' => 'afd_status', 'ptrt_tag_desc' => 'AFD status' ],
				[ 'ptrt_tag_name' => 'rev_count', 'ptrt_tag_desc' => 'Number of edits to the article' ],
				[ 'ptrt_tag_name' => 'page_len', 'ptrt_tag_desc' => 'Number of bytes of article' ],
				[ 'ptrt_tag_name' => 'snippet', 'ptrt_tag_desc' => 'Beginning of article snippet' ],
				[ 'ptrt_tag_name' => 'user_name', 'ptrt_tag_desc' => 'User name' ],
				[ 'ptrt_tag_name' => 'user_editcount', 'ptrt_tag_desc' => 'User total edit' ],
				[ 'ptrt_tag_name' => 'user_creation_date', 'ptrt_tag_desc' => 'User registration date' ],
				[ 'ptrt_tag_name' => 'user_autoconfirmed', 'ptrt_tag_desc' => 'Check if user is autoconfirmed' ],
				[ 'ptrt_tag_name' => 'user_experience',
					'ptrt_tag_desc' => 'Experience level: newcomer, learner, experienced or anonymous' ],
				[ 'ptrt_tag_name' => 'user_bot', 'ptrt_tag_desc' => 'Check if user is in bot group' ],
				[ 'ptrt_tag_name' => 'user_block_status', 'ptrt_tag_desc' => 'User block status' ],
				[ 'ptrt_tag_name' => 'user_id', 'ptrt_tag_desc' => 'User id' ],
				[ 'ptrt_tag_name' => 'reference', 'ptrt_tag_desc' => 'Check if page has references' ],
				// 1.32
				[ 'ptrt_tag_name' => 'afc_state', 'ptrt_tag_desc' => 'The submission state of drafts' ],
				[ 'ptrt_tag_name' => 'copyvio', 'ptrt_tag_desc' =>
					'Latest revision ID that has been tagged as a likely copyright violation, if any' ],
				// 1.34
				[ 'ptrt_tag_name' => 'recreated', 'ptrt_tag_desc' => 'Check if the page has been previously deleted.' ],
			],
			__METHOD__,
			[ 'IGNORE' ]
		);

		$updater->output( "Done\n" );
		$updater->insertUpdateRow( $updateKey );
	}
}
