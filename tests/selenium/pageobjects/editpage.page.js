import Page from 'wdio-mediawiki/Page.js';
import { waitForModuleState } from 'wdio-mediawiki/Util.js';

class EditPage extends Page {

	get content() {
		return $( '#wpTextbox1' );
	}

	get save() {
		return $( '#wpSave' );
	}

	get displayedContent() {
		return $( '#mw-content-text .mw-parser-output' );
	}

	async open( article ) {
		await super.openTitle( article, { action: 'edit', vehidebetadialog: 1 } );
		// WikiEditor adds the toolbar after the load, thus the layout changes.
		// Wait for a stable form, as core's edit.page.js does. (T324879)
		await waitForModuleState( 'mediawiki.base' );
		const hasToolbar = await this.save.isExisting() &&
			await browser.execute( () => mw.loader.getState( 'ext.wikiEditor' ) !== null );
		if ( hasToolbar ) {
			await $( '#wikiEditor-ui-toolbar' ).waitForDisplayed();
		}
	}

	async saveArticle( articleText ) {
		await this.content.setValue( articleText );
		await this.save.click();
		// The click does not wait for the save request. The caller can close
		// the session before the browser sends it. (T437133)
		await this.displayedContent.waitForDisplayed();
	}
}

export default new EditPage();
