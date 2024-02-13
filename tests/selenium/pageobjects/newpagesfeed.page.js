'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class NewPagesFeed extends Page {

	get listview() {
		return $( '#mwe-pt-list' );
	}

	open() {
		super.openTitle( 'Special:NewPagesFeed' );
	}

	get articleRows() {
		return $( '.mwe-vue-pt-article-row' );
	}
}

module.exports = new NewPagesFeed();
