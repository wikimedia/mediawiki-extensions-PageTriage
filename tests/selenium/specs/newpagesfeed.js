'use strict';

const assert = require( 'assert' ),
	NewPagesFeed = require( '../pageobjects/newpagesfeed.page' );

describe( 'Special:NewPagesFeed', function () {

	it( 'is configured correctly', async function () {
		await NewPagesFeed.open();
		assert( await NewPagesFeed.listview.isExisting() );
	} );

} );
