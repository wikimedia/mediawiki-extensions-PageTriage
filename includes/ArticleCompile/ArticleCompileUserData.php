<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use ActorMigration;
use User;

/**
 * Article User data
 */
class ArticleCompileUserData extends ArticleCompileInterface {

	public function compile() {
		// Grab the earliest revision based on rev_timestamp and rev_id
		$revId = [];
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->db->selectRow(
				[ 'revision' ],
				[ 'rev_id' ],
				[ 'rev_page' => $pageId ],
				__METHOD__,
				[ 'LIMIT' => 1, 'ORDER BY' => 'rev_timestamp, rev_id' ]
			);

			if ( $res ) {
				$revId[] = $res->rev_id;
			}
		}

		if ( count( $revId ) === 0 ) {
			return true;
		}

		$now = $this->db->addQuotes( $this->db->timestamp() );

		$actorQuery = ActorMigration::newMigration()->getJoin( 'rev_user' );
		$res = $this->db->select(
			array_merge( [ 'revision' ], $actorQuery['tables'],  [ 'user', 'ipblocks' ] ),
			[
				'rev_page AS page_id', 'user_id', 'user_name',
				'user_real_name', 'user_registration', 'user_editcount',
				'ipb_id', 'rev_user_text' => $actorQuery['fields']['rev_user_text']
			],
			[ 'rev_id' => $revId ],
			__METHOD__,
			[],
			$actorQuery['joins'] + [
				'user' => [ 'LEFT JOIN', $actorQuery['fields']['rev_user'] . ' = user_id' ],
				'ipblocks' => [
					'LEFT JOIN', [
						$actorQuery['fields']['rev_user'] . ' = ipb_user',
						$actorQuery['fields']['rev_user_text'] . ' = ipb_address',
						'ipb_expiry > ' . $now,
						'ipb_sitewide' => 1
					]
				]
			]
		);

		foreach ( $res as $row ) {
			// User exists
			if ( $row->user_id ) {
				$user = User::newFromRow( $row );
				$this->metadata[$row->page_id]['user_id'] = $row->user_id;
				$this->metadata[$row->page_id]['user_name'] = $user->getName();
				$this->metadata[$row->page_id]['user_editcount'] = $user->getEditCount();
				$this->metadata[$row->page_id]['user_creation_date'] = wfTimestamp(
					TS_MW,
					$user->getRegistration()
				);
				$this->metadata[$row->page_id]['user_autoconfirmed'] =
					$user->isAllowed( 'autoconfirmed' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_experience'] = $user->getExperienceLevel();
				$this->metadata[$row->page_id]['user_bot'] = $user->isAllowed( 'bot' ) ? '1' : '0';
				$this->metadata[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';
			} else {
				// User doesn't exist, etc IP
				$this->metadata[$row->page_id]['user_id'] = 0;
				$this->metadata[$row->page_id]['user_name'] = $row->rev_user_text;
				$this->metadata[$row->page_id]['user_editcount'] = 0;
				$this->metadata[$row->page_id]['user_creation_date'] = '';
				$this->metadata[$row->page_id]['user_autoconfirmed'] = '0';
				$this->metadata[$row->page_id]['user_experience'] = 'anonymous';
				$this->metadata[$row->page_id]['user_bot'] = '0';
				$this->metadata[$row->page_id]['user_block_status'] = $row->ipb_id ? '1' : '0';
			}
		}

		return true;
	}

}
