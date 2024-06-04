'use strict';
const assert = require( 'assert' ),
	Toolbar = require( '../pageobjects/toolbar.page' ),
	EditPage = require( '../pageobjects/editpage.page' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	RunJobs = require( 'wdio-mediawiki/RunJobs' ),
	Util = require( 'wdio-mediawiki/Util' ),
	MWBot = require( 'mwbot' ),
	articleName = Util.getTestString( 'NewArticle-' ),
	username = Util.getTestString( 'User-' ),
	Api = require( 'wdio-mediawiki/Api' );

describe( 'PageTriage Toolbar', function () {
	before( async function () {
		const bot = await Api.bot();
		// Create account with known username and password
		const password = Util.getTestString();
		await Api.createAccount( bot, username, password );

		const nonAdminUserBot = new MWBot();

		await nonAdminUserBot.loginGetEditToken( {
			apiUrl: `${ browser.config.baseUrl }/api.php`,
			username: username,
			password: password
		} );
		await nonAdminUserBot.create( articleName, Util.getTestString(), Util.getTestString() );
		RunJobs.run();
	} );

	it( 'should load', async function () {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		Toolbar.open( articleName );
		await browser.waitUntil( async () => {
			return await Toolbar.toolbarBody.waitForDisplayed();
		} );

		assert( Toolbar.toolbarBody.isExisting() );
	} );

	it( 'should allow user to add a maintainence tag to a page', async function () {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		Toolbar.open( articleName );
		await browser.waitUntil( async () => {
			return await Toolbar.toolbarBody.waitForDisplayed();
		} );

		await browser.waitUntil( async () => {
			return await Toolbar.tagToolIcon.waitForDisplayed();
		} );

		assert( await Toolbar.tagToolIcon.isExisting() );

		await Toolbar.tagToolIcon.click();

		await browser.waitUntil( async () => {
			return await Toolbar.tagToolBody.waitForDisplayed();
		} );

		assert( await Toolbar.tagToolBody.isExisting() );

		await Toolbar.tagToolFirstCheckbox.click();
		await Toolbar.tagToolSubmitButton.waitForEnabled();
		await Toolbar.tagToolSubmitButton.click();
		await browser.waitUntil( async () => {
			return !( await Toolbar.tagToolSubmitButton.isExisting() );
		} );

		await EditPage.open( articleName );

		const articleTxt = await EditPage.content.getValue();

		assert( articleTxt.includes( '{{ai-generated|date=' ) );

	} );

	it( 'should allow user to add a maintainence tag to a page and send a note to user', async function () {
		await browser.reloadSession();
		await LoginPage.loginAdmin();
		Toolbar.open( articleName );
		await browser.waitUntil( async () => {
			return await Toolbar.toolbarBody.waitForDisplayed();
		} );

		await browser.waitUntil( async () => {
			return await Toolbar.tagToolIcon.waitForDisplayed();
		} );

		Toolbar.tagToolIcon.click();

		await browser.waitUntil( async () => {
			return await Toolbar.tagToolBody.waitForDisplayed();
		} );

		Toolbar.tagToolFirstCheckbox.click();
		await Toolbar.tagToolNoteBox.waitForDisplayed();
		const comment = Util.getTestString( 'Comment-' );
		await Toolbar.tagToolNoteBox.setValue( comment );
		Toolbar.tagToolSubmitButton.click();
		await browser.waitUntil( async () => {
			return !( await Toolbar.tagToolSubmitButton.isExisting() );
		} );

		await EditPage.open( `User talk:${ username }` );

		const userTalkPageTxt = await EditPage.content.getValue();

		assert( userTalkPageTxt.includes( comment ) );

	} );
} );
