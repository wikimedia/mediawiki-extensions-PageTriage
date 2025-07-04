import Page from 'wdio-mediawiki/Page.js';

class NewPagesFeed extends Page {

	get listview() {
		return $( '#mwe-pt-list' );
	}

	async open() {
		return super.openTitle( 'Special:NewPagesFeed' );
	}

	get articleRows() {
		return $( '.mwe-vue-pt-article-row' );
	}
}

export default new NewPagesFeed();
