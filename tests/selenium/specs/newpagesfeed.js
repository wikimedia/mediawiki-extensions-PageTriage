'use strict';

const NewPagesFeed = require( '../pageobjects/newpagesfeed.page' );
const EditPage = require( '../pageobjects/editpage.page' );
const CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' );
const RunJobs = require( 'wdio-mediawiki/RunJobs' );
const Util = require( 'wdio-mediawiki/Util' );

describe( 'Special:NewPagesFeed', () => {
	it( 'is viewable', async () => {
		await NewPagesFeed.open();
		await browser.waitUntil( async () => await NewPagesFeed.listview.getText() !== 'Please wait...' );
		await expect( await NewPagesFeed.listview ).toExist();
	} );

	it( 'new article appears in feed', async () => {
		// Create account
		const username = Util.getTestString( 'User-' );
		const password = Util.getTestString();
		await CreateAccountPage.createAccount( username, password );

		// Create an unreviewed article
		const articleName = Util.getTestString( 'NewArticle-' );
		await EditPage.open( articleName );
		await EditPage.saveArticle( Util.getTestString() );
		RunJobs.run();

		// close and reopen the browser window, logging out the user and making it easier
		// to navigate to Special:NewPagesFeed
		await browser.reloadSession();

		// Special:NewPagesFeed
		await NewPagesFeed.open();
		await browser.waitUntil( async () => await NewPagesFeed.listview.getText() !== 'Please wait...' );

		await expect( await NewPagesFeed.listview ).toExist();

		// wait untill atleast one article is loaded
		await NewPagesFeed.articleRows.waitForDisplayed();

		// Check that unreviewed article shows up in Special:NewPagesFeed
		await expect( await NewPagesFeed.listview ).toHaveText( expect.stringContaining( articleName ) );
	} );
} );
