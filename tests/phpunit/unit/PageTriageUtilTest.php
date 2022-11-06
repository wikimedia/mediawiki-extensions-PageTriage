<?php

use MediaWiki\Extension\PageTriage\PageTriageUtil;

/**
 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil
 */
class PageTriageUtilTest extends MediaWikiUnitTestCase {

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

}
