'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class EditPage extends Page {

	get content() { return $( '#wpTextbox1' ); }
	get save() { return $( '#wpSave' ); }

	async open( article ) {
		await super.openTitle( article, { action: 'edit', vehidebetadialog: 1 } );
	}

	async saveArticle( articleText ) {
		await this.content.setValue( articleText );
		await this.save.click();
	}
}

module.exports = new EditPage();
