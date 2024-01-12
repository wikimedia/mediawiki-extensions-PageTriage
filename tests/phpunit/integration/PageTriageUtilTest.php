<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil
 * @group Database
 */
class PageTriageUtilTest extends PageTriageTestCase {

	public function testIsOresArticlequalityQuery() {
		$this->assertFalse( PageTriageUtil::isOresArticleQualityQuery( [ 'page_id' => '123' ] ) );
		$this->assertTrue(
			PageTriageUtil::isOresArticleQualityQuery( [ 'show_predicted_class_stub' => true ] )
		);
	}

	public function testIsOresDraftQualityQuery() {
		$this->assertFalse( PageTriageUtil::isOresDraftQualityQuery( [ 'page_id' => '123' ] ) );
		$this->assertTrue(
			PageTriageUtil::isOresDraftQualityQuery( [ 'show_predicted_issues_vandalism' => true ] )
		);
	}

	/**
	 * @dataProvider provideMapOresParamsToClassNames
	 */
	public function testMapOresParamsToClassNames( $model, $opts, $expect ) {
		$this->assertTrue( PageTriageUtil::mapOresParamsToClassNames( $model, $opts ) === $expect );
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
		// The hook handler in GrowthExperiments triggers DB access.
		$this->clearHook( 'UserGetDefaultOptions' );
		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnection' )->willReturn( $this->createMock( IDatabase::class ) );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getExternalLB' )->willReturn( $lb );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
		$this->setService( 'DBLoadBalancer', $lb );

		$title = Title::makeTitle( NS_MAIN, 'NotificationTest' );
		$user = $this->createMock( User::class );
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

		$this->assertSame( 'NotificationTest', $titleResult );
		$this->assertSame( $type, $typeResult );
		$this->assertSame( $extra['tags'], $extraTagResult );
	}

	public function testGetUnreviewedDraftStats() {
		$unsubmittedDraft = $this->makeDraft( 'Unsubmitted draft', false, false, null, 'test' );
		$this->assertTrue( (bool)$unsubmittedDraft, 'Page successfully created (1)' );

		$submittedDraft1 = $this->makeDraft( 'Submitted draft 1', false, false, null,
			'[[Category:Pending AfC submissions]]' );
		$this->assertTrue( (bool)$submittedDraft1, 'Page successfully created (2)' );

		$submittedDraft2 = $this->makeDraft( 'Submitted draft 2', false, false, null,
			'[[Category:Pending AfC submissions]]' );
		$this->assertTrue( (bool)$submittedDraft2, 'Page successfully created (3)' );

		$declinedDraft = $this->makeDraft( 'Declined draft', false, false, null,
			'[[Category:Declined AfC submissions]]' );
		$this->assertTrue( (bool)$declinedDraft, 'Page successfully created (4)' );

		$stats = PageTriageUtil::getUnreviewedDraftStats();
		$this->assertSame(
			2,
			$stats[ 'count' ],
			'Number of unreviewed drafts is correct'
		);

		$revision = $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByPageId( $submittedDraft1 );
		$timestamp = wfTimestamp( TS_ISO_8601, $revision->getTimestamp() );
		$this->assertSame(
			$timestamp,
			$stats[ 'oldest' ],
			'Timestamp of oldest unreviewed draft is correct'
		);
	}
}
