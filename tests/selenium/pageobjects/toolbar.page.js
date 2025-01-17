'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class PageTriageToolbar extends Page {

	get toolbarBody() {
		return $( '#mw-pagetriage-toolbar' );
	}

	async open( page ) {
		return super.openTitle( page, { showcurationtoolbar: 1 } );
	}

	get tagToolIcon() {
		return $( '#mwe-pt-tag .mwe-pt-tool-icon' );
	}

	get tagToolBody() {
		return $( '#mwe-pt-tags' );
	}

	get tagToolFirstCheckbox() {
		return $( '#mwe-pt-tags .mwe-pt-tag-checkbox' );
	}

	get tagToolNoteBox() {
		return $( '#mwe-pt-tag-note-input' );
	}

	get tagToolSubmitButton() {
		return $( '#mwe-pt-tag-submit' );
	}
}

module.exports = new PageTriageToolbar();
