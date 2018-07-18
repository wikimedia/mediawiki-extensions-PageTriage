<?php

use MediaWiki\Extension\PageTriage\PageTriageUtil;

class PageTriageUtilTest extends MediaWikiTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::isOresWp10Query()
	 */
	public function testIsOresWp10Query() {
		self::assertEquals( false, PageTriageUtil::isOresWp10Query( [ 'page_id' => '123' ] ) );
		self::assertEquals(
			true,
			PageTriageUtil::isOresWp10Query( [ 'show_predicted_class_stub' => true ] )
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
