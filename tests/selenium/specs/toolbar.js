'use strict';
const Toolbar = require( '../pageobjects/toolbar.page' );
const EditPage = require( '../pageobjects/editpage.page' );
const LoginPage = require( 'wdio-mediawiki/LoginPage' );
const RunJobs = require( 'wdio-mediawiki/RunJobs' );
const Util = require( 'wdio-mediawiki/Util' );
const MWBot = require( 'mwbot' );
const articleName = Util.getTestString( 'NewArticle-' );
const username = Util.getTestString( 'User-' );
const Api = require( 'wdio-mediawiki/Api' );

describe( 'PageTriage Toolbar', () => {
	before( async () => {
		const bot = await Api.bot();
		// Create account with known username and password
		const password = Util.getTestString();
		await Api.createAccount( bot, username, password );

		const nonAdminUserBot = new MWBot();

		await nonAdminUserBot.loginGetEditToken( {
			apiUrl: `${ browser.options.baseUrl }/api.php`,
			username: username,
			password: password
		} );
		await nonAdminUserBot.create( articleName, Util.getTestString(), Util.getTestString() );
		RunJobs.run();
	} );

	it( 'should load', async () => {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		await Toolbar.open( articleName );
		await browser.waitUntil( async () => await Toolbar.toolbarBody.waitForDisplayed() );

		await expect( await Toolbar.toolbarBody ).toExist();
	} );

	it( 'should allow user to add a maintainence tag to a page', async () => {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		await Toolbar.open( articleName );
		await browser.waitUntil( async () => await Toolbar.toolbarBody.waitForDisplayed() );

		await browser.waitUntil( async () => await Toolbar.tagToolIcon.waitForDisplayed() );

		await expect( await Toolbar.tagToolIcon ).toExist();

		await Toolbar.tagToolIcon.click();

		await browser.waitUntil( async () => await Toolbar.tagToolBody.waitForDisplayed() );

		await expect( await Toolbar.tagToolBody ).toExist();

		await Toolbar.tagToolFirstCheckbox.click();
		await Toolbar.tagToolSubmitButton.waitForEnabled();
		await Toolbar.tagToolSubmitButton.click();
		await browser.waitUntil( async () => !( await Toolbar.tagToolSubmitButton.isExisting() ) );

		await EditPage.open( articleName );

		await expect( await EditPage.content ).toHaveValue( expect.stringContaining( '{{ai-generated|date=' ) );

	} );

	it( 'should allow user to add a maintainence tag to a page and send a note to user', async () => {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		await Toolbar.open( articleName );
		await browser.waitUntil( async () => await Toolbar.toolbarBody.waitForDisplayed() );

		await browser.waitUntil( async () => await Toolbar.tagToolIcon.waitForDisplayed() );

		await Toolbar.tagToolIcon.click();

		await browser.waitUntil( async () => await Toolbar.tagToolBody.waitForDisplayed() );

		await Toolbar.tagToolFirstCheckbox.click();
		await Toolbar.tagToolNoteBox.waitForDisplayed();
		const comment = Util.getTestString( 'Comment-' );
		await Toolbar.tagToolNoteBox.setValue( comment );
		await Toolbar.tagToolSubmitButton.waitForEnabled();
		await Toolbar.tagToolSubmitButton.click();
		await browser.waitUntil( async () => !( await Toolbar.tagToolSubmitButton.isExisting() ) );

		await EditPage.open( `User talk:${ username }` );

		await expect( await EditPage.content ).toHaveValue( expect.stringContaining( comment ) );

	} );
} );
