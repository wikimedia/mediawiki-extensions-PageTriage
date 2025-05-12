<?php
namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageTriage\SpecialNewPagesFeed;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use SpecialPageTestBase;

/**
 * Tests for SpecialNewPagesFeed class (PageTriage list view)
 *
 * @group Database
 * @covers \MediaWiki\Extension\PageTriage\SpecialNewPagesFeed
 */
class SpecialNewPagesFeedTest extends SpecialPageTestBase {
	use MockAuthorityTrait;
	use TempUserTestTrait;

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		$userOptionsLookup = $this->getServiceContainer()->getUserOptionsLookup();
		return new SpecialNewPagesFeed( $userOptionsLookup );
	}

	public function testPageLoads() {
		[ $html, ] = $this->executeSpecialPage(
			'',
			null,
			'qqx'
		);

		// Welcome message should display
		$this->assertStringContainsString( 'pagetriage-welcome', $html );
		// List View content should load
		$this->assertStringContainsString( 'pagetriage-please-wait', $html );
		$this->assertStringContainsString( 'pagetriage-js-required', $html );
	}

	public function testShowIpModuleDoesNotLoadIfNoCheckUserExtension() {
		$this->disableAutoCreateTempUser();
		$page = new SpecialNewPagesFeed( $this->getServiceContainer()->getUserOptionsLookup() );

		$output = RequestContext::getMain()->getOutput();
		$page->execute( '' );

		$this->assertCount( 3, $output->getModules() );
		$this->assertNotContains( 'ext.pageTriage.showIp', $output->getModules() );
	}

	public function testShowIpModuleDoesLoadIfUserHasPermissions() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$this->enableAutoCreateTempUser();
		$mediaWikiServices = $this->getServiceContainer();
		$title = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$title2 = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$newPage = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title );
		$newPage2 = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title2 );

		$tempUser = $mediaWikiServices
			->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		$this->editPage( $newPage, "some content", NS_MAIN, $tempUser );
		$this->editPage( $newPage2, "some content", NS_MAIN, $tempUser );

		// user has checkuser-temporary-account-no-preference permission
		$testAuthority = $this->mockRegisteredAuthorityWithPermissions(
			[ 'checkuser-temporary-account-no-preference' ] );
		$page = new SpecialNewPagesFeed( $mediaWikiServices->getUserOptionsLookup() );
		$requestContext = RequestContext::getMain();
		$requestContext->setAuthority( $testAuthority );
		$output = $requestContext->getOutput();
		$page->execute( '' );
		$this->assertCount( 4, $output->getModules() );
		$this->assertContains( 'ext.pageTriage.showIp', $output->getModules() );
	}

	public function testShowIpModuleDoesNotLoadIfUserMissingAllPermissions() {
		$this->enableAutoCreateTempUser();
		$mediaWikiServices = $this->getServiceContainer();
		$title = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$title2 = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$newPage = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title );
		$newPage2 = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title2 );

		$tempUser = $mediaWikiServices
			->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		$this->editPage( $newPage, "some content", NS_MAIN, $tempUser );
		$this->editPage( $newPage2, "some content", NS_MAIN, $tempUser );

		// user has no permissions
		$testAuthority = $this->mockRegisteredAuthorityWithPermissions(
			[] );
		$page = new SpecialNewPagesFeed( $mediaWikiServices->getUserOptionsLookup() );
		$requestContext = RequestContext::getMain();
		$requestContext->setAuthority( $testAuthority );
		$output = $requestContext->getOutput();
		$page->execute( '' );
		$this->assertCount( 3, $output->getModules() );
		$this->assertNotContains( 'ext.pageTriage.showIp', $output->getModules() );
	}

	public function testShowIpModuleDoesNotLoadIfUserBlocked() {
		$this->enableAutoCreateTempUser();
		$mediaWikiServices = $this->getServiceContainer();
		$title = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$title2 = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$newPage = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title );
		$newPage2 = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title2 );

		$tempUser = $mediaWikiServices
			->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		$this->editPage( $newPage, "some content", NS_MAIN, $tempUser );
		$this->editPage( $newPage2, "some content", NS_MAIN, $tempUser );

		// user has all permissions but is blocked
		$block = $this->createMock( DatabaseBlock::class );
		$testAuthority = $this->mockRegisteredAuthorityWithPermissions(
			[ 'checkuser-temporary-account-no-preference' ]
		);
		$testAuthority = $this->mockUserAuthorityWithBlock( $testAuthority->getUser(), $block );
		$page = new SpecialNewPagesFeed( $mediaWikiServices->getUserOptionsLookup() );
		$requestContext = RequestContext::getMain();
		$requestContext->setAuthority( $testAuthority );
		$output = $requestContext->getOutput();
		$page->execute( '' );
		$this->assertCount( 3, $output->getModules() );
		$this->assertNotContains( 'ext.pageTriage.showIp', $output->getModules() );
	}

	public function testShowIpModuleDoesNotLoadIfUserOptionDisabled() {
		$this->enableAutoCreateTempUser();
		$mediaWikiServices = $this->getServiceContainer();
		$title = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$title2 = $mediaWikiServices->getTitleFactory()->newFromText( __METHOD__ );
		$newPage = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title );
		$newPage2 = $mediaWikiServices->getWikiPageFactory()->newFromTitle( $title2 );

		$tempUser = $mediaWikiServices
			->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		$this->editPage( $newPage, "some content", NS_MAIN, $tempUser );
		$this->editPage( $newPage2, "some content", NS_MAIN, $tempUser );

		// user has checkuser-temporary-account permissions
		// but user options not enabled
		$testAuthority = $this->mockRegisteredAuthorityWithPermissions(
				[ 'checkuser-temporary-account' ] );
		$mediaWikiServices->getUserOptionsManager()->setOption(
			$testAuthority->getUser(),
			'checkuser-temporary-account-enable',
			false
		);
		$page = new SpecialNewPagesFeed( $mediaWikiServices->getUserOptionsLookup() );
		$requestContext = RequestContext::getMain();
		$requestContext->setAuthority( $testAuthority );
		$output = $requestContext->getOutput();
		$page->execute( '' );
		$this->assertCount( 3, $output->getModules() );
		$this->assertNotContains( 'ext.pageTriage.showIp', $output->getModules() );
	}
}
