<?php

use MediaWiki\Extension\PageTriage\PageTriageUtil;

class PageTriageUtilTest extends MediaWikiTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::isOresArticleQualityQuery()
	 */
	public function testIsOresArticlequalityQuery() {
		self::assertEquals( false, PageTriageUtil::isOresArticleQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertEquals(
			true,
			PageTriageUtil::isOresArticleQualityQuery( [ 'show_predicted_class_stub' => true ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::isOresDraftQualityQuery()
	 */
	public function testIsOresDraftQualityQuery() {
		self::assertEquals( false, PageTriageUtil::isOresDraftQualityQuery( [ 'page_id' => '123' ] ) );
		self::assertEquals(
			true,
			PageTriageUtil::isOresDraftQualityQuery( [ 'show_predicted_issues_vandalism' => true ] )
		);
	}

}
