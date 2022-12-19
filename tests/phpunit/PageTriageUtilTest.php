<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil
 */
class PageTriageUtilTest extends MediaWikiIntegrationTestCase {

	public function testIsOresArticlequalityQuery() {
		self::assertFalse( PageTriageUtil::isOresArticleQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertTrue(
			PageTriageUtil::isOresArticleQualityQuery( [ 'show_predicted_class_stub' => true ] )
		);
	}

	public function testIsOresDraftQualityQuery() {
		self::assertFalse( PageTriageUtil::isOresDraftQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertTrue(
			PageTriageUtil::isOresDraftQualityQuery( [ 'show_predicted_issues_vandalism' => true ] )
		);
	}

	/**
	 * @dataProvider provideMapOresParamsToClassNames
	 */
	public function testMapOresParamsToClassNames( $model, $opts, $expect ) {
		self::assertTrue( PageTriageUtil::mapOresParamsToClassNames( $model, $opts ) === $expect );
	}

	public static function provideMapOresParamsToClassNames() {
		$optsNoQualitySelected = [
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		$optsOneArticleQualitySelected = [
			'show_predicted_class_stub' => true,
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		$optsTwoArticleQualitySelected = [
			'show_predicted_class_stub' => true,
			'show_predicted_class_start' => true,
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		$optsOneDraftQualitySelected = [
			'show_predicted_issues_vandalism' => true,
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		$optsTwoDraftQualitySelected = [
			'show_predicted_issues_vandalism' => true,
			'show_predicted_issues_spam' => true,
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		$optsMixOfBothQualityTypes = [
			'show_predicted_class_stub' => true,
			'show_predicted_class_start' => true,
			'show_predicted_issues_vandalism' => true,
			'show_predicted_issues_spam' => true,
			'showothers' => true,
			'showunreviewed' => true,
			'showdeleted' => true,
			'namespace' => 0,
		];

		return [
			[ 'articlequality', $optsNoQualitySelected, [] ],
			[ 'articlequality', $optsOneArticleQualitySelected, [ 'Stub' ] ],
			[ 'articlequality', $optsTwoArticleQualitySelected, [ 'Stub', 'Start' ] ],
			[ 'draftquality', $optsNoQualitySelected, [] ],
			[ 'draftquality', $optsOneDraftQualitySelected, [ 'vandalism' ] ],
			[ 'draftquality', $optsTwoDraftQualitySelected, [ 'vandalism', 'spam' ] ],
			[ 'draftquality', $optsMixOfBothQualityTypes, [ 'vandalism', 'spam' ] ],
			[ 'draftquality', [], [] ],
		];
	}

	public function testCreateNotificationEvent() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );

		$title = Title::newFromText( 'NotificationTest' );
		$user = static::getTestSysop()->getUser( 'TestWikiAdmin' );
		$type = 'pagetriage-add-maintenance-tag';
		$extra = [
			'tags' => [ 'under review' ],
			'note' => '',
			'revId' => 163,
		];

		$status = PageTriageUtil::createNotificationEvent( $title, $user, $type, $extra );
		$echoEvent = $status->getValue();

		$titleResult = $echoEvent->getTitle()->getText();
		$typeResult = $echoEvent->getType();
		$extraTagResult = $echoEvent->getExtraParam( 'tags' );

		$this->assertTrue( $titleResult === 'NotificationTest' );
		$this->assertTrue( $typeResult === $type );
		$this->assertTrue( $extraTagResult === $extra['tags'] );
	}
}
