import NewPagesFeed from '../pageobjects/newpagesfeed.page.js';
import EditPage from '../pageobjects/editpage.page.js';
import CreateAccountPage from 'wdio-mediawiki/CreateAccountPage.js';
import RunJobs from 'wdio-mediawiki/RunJobs.js';
import { getTestString } from 'wdio-mediawiki/Util.js';

describe( 'Special:NewPagesFeed', () => {
	it( 'is viewable', async () => {
		await NewPagesFeed.open();
		await browser.waitUntil( async () => await NewPagesFeed.listview.getText() !== 'Please wait...' );
		await expect( await NewPagesFeed.listview ).toExist();
	} );

	it( 'new article appears in feed', async () => {
		// Create account
		const username = getTestString( 'User-' );
		const password = getTestString();
		await CreateAccountPage.createAccount( username, password );

		// Create an unreviewed article
		const articleName = getTestString( 'NewArticle-' );
		await EditPage.open( articleName );
		await EditPage.saveArticle( getTestString() );
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
