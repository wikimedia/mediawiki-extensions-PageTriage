<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

/**
 * Article User data
 */
class ArticleCompileUserData extends ArticleCompile {

	public function compile() {
		// Grab the earliest revision based on rev_timestamp and rev_id
		$revId = [];
		foreach ( $this->mPageId as $pageId ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( 'rev_id' )
				->from( 'revision' )
				->where( [ 'rev_page' => $pageId ] )
				->limit( 1 )
				->orderBy( [ 'rev_timestamp', 'rev_id' ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( $res ) {
				$revId[] = $res->rev_id;
			}
		}

		if ( count( $revId ) === 0 ) {
			return true;
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()
			->get( MainConfigNames::BlockTargetMigrationStage ) & SCHEMA_COMPAT_READ_OLD
		) {
			$blockQuery = $this->db->newSelectQueryBuilder()
				->select( '1' )
				->from( 'ipblocks' )
				->where( [
					'ipb_user=actor_user',
					$this->db->expr( 'ipb_expiry', '>', $this->db->timestamp() ),
					'ipb_sitewide' => 1
				] )
				->getSQL();
		} else {
			$blockQuery = $this->db->newSelectQueryBuilder()
				->select( '1' )
				->from( 'block' )
				->join( 'block_target', null, 'bt_id=bl_target' )
				->where( [
					'bt_user=actor_user',
					$this->db->expr( 'bl_expiry', '>', $this->db->timestamp() ),
					'bl_sitewide' => 1
				] )
				->getSQL();
		}

		$res = $this->db->newSelectQueryBuilder()
			->select( [
				'rev_page', 'actor_name',
				'user_id', 'user_name', 'user_real_name', 'user_registration', 'user_editcount',
				'blocked' => 'EXISTS (' . $blockQuery . ')'
			] )
			->from( 'revision' )
			->join( 'actor', null, 'actor_id=rev_actor' )
			->leftJoin( 'user', null, 'user_id=actor_user' )
			->where( [ 'rev_id' => $revId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$data = [];
			// User exists
			if ( $row->user_id ) {
				$user = User::newFromRow( $row );
				$data['user_id'] = $row->user_id;
				$data['user_name'] = $user->getName();
				$data['user_editcount'] = $user->getEditCount();
				$data['user_creation_date'] = wfTimestamp(
					TS_MW,
					$user->getRegistration()
				);
				$data['user_autoconfirmed'] =
					$user->isAllowed( 'autoconfirmed' ) ? '1' : '0';
				$data['user_experience'] = $user->getExperienceLevel();
				$data['user_bot'] = $user->isAllowed( 'bot' ) ? '1' : '0';
			} else {
				// User doesn't exist, etc IP
				$data['user_id'] = 0;
				$data['user_name'] = $row->actor_name;
				$data['user_editcount'] = 0;
				$data['user_creation_date'] = '';
				$data['user_autoconfirmed'] = '0';
				$data['user_experience'] = 'anonymous';
				$data['user_bot'] = '0';
			}
			$data['user_block_status'] = $row->blocked ? '1' : '0';
			$this->metadata[$row->rev_page] = $data;
		}

		return true;
	}

}
