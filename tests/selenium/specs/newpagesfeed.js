'use strict';

const assert = require( 'assert' ),
	NewPagesFeed = require( '../pageobjects/newpagesfeed.page' ),
	EditPage = require( '../pageobjects/editpage.page' ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	RunJobs = require( 'wdio-mediawiki/RunJobs' ),
	Util = require( 'wdio-mediawiki/Util' );

describe( 'Special:NewPagesFeed', function () {
	it( 'is viewable', async function () {
		await NewPagesFeed.open();
		await browser.waitUntil( async () => {
			return await NewPagesFeed.listview.getText() !== 'Please wait...';
		} );
		assert( await NewPagesFeed.listview.isExisting() );
	} );

	it( 'new article appears in feed', async function () {
		// Create account
		const username = Util.getTestString( 'User-' );
		const password = Util.getTestString();
		await CreateAccountPage.createAccount( username, password );

		// Create an unreviewed article
		const articleName = Util.getTestString( 'NewArticle-' );
		await EditPage.open( articleName );
		await EditPage.saveArticle( Util.getTestString() );
		await RunJobs.run();

		// close and reopen the browser window, logging out the user and making it easier
		// to navigate to Special:NewPagesFeed
		await browser.reloadSession();

		// Special:NewPagesFeed
		await NewPagesFeed.open();
		await browser.waitUntil( async () => {
			return await NewPagesFeed.listview.getText() !== 'Please wait...';
		} );

		assert( await NewPagesFeed.listview.isExisting() );

		// wait untill atleast one article is loaded
		await NewPagesFeed.articleRows.waitForDisplayed();

		// Check that unreviewed article shows up in Special:NewPagesFeed
		expect( await NewPagesFeed.listview.getText() ).toContain( articleName );
	} );
} );
