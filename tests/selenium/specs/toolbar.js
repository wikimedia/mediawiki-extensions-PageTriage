import Toolbar from '../pageobjects/toolbar.page.js';
import EditPage from '../pageobjects/editpage.page.js';
import LoginPage from 'wdio-mediawiki/LoginPage.js';
import RunJobs from 'wdio-mediawiki/RunJobs.js';
import MWBot from 'mwbot';
import { getTestString } from 'wdio-mediawiki/Util.js';
const articleName = getTestString( 'NewArticle-' );
const username = getTestString( 'User-' );
import { createAccount, mwbot } from 'wdio-mediawiki/Api.js';

describe( 'PageTriage Toolbar', () => {
	before( async () => {
		const bot = await mwbot();
		// Create account with known username and password
		const password = getTestString();
		await createAccount( bot, username, password );

		const nonAdminUserBot = new MWBot();

		await nonAdminUserBot.loginGetEditToken( {
			apiUrl: `${ browser.options.baseUrl }/api.php`,
			username: username,
			password: password
		} );
		await nonAdminUserBot.create( articleName, getTestString(), getTestString() );
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
		const comment = getTestString( 'Comment-' );
		await Toolbar.tagToolNoteBox.setValue( comment );
		await Toolbar.tagToolSubmitButton.waitForEnabled();
		await Toolbar.tagToolSubmitButton.click();
		await browser.waitUntil( async () => !( await Toolbar.tagToolSubmitButton.isExisting() ) );

		await EditPage.open( `User talk:${ username }` );

		await expect( await EditPage.content ).toHaveValue( expect.stringContaining( comment ) );

	} );
} );
