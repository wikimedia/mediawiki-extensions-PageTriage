<?php

use MediaWiki\Extension\PageTriage\PageTriageUtil;

class PageTriageUtilTest extends MediaWikiTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::isOresArticleQualityQuery()
	 */
	public function testIsOresArticlequalityQuery() {
		self::assertFalse( PageTriageUtil::isOresArticleQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertTrue(

			PageTriageUtil::isOresArticleQualityQuery( [ 'show_predicted_class_stub' => true ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::isOresDraftQualityQuery()
	 */
	public function testIsOresDraftQualityQuery() {
		self::assertFalse( PageTriageUtil::isOresDraftQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertTrue(

			PageTriageUtil::isOresDraftQualityQuery( [ 'show_predicted_issues_vandalism' => true ] )
		);
	}

}
