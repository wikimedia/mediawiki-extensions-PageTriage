const actionQueue = require( './ext.pageTriage.actionQueue.js' );

$( () => {
	const ns = mw.config.get( 'wgNamespaceNumber' );

	// Only show curation toolbar for enabled namespaces, minus the draftspace.
	if ( mw.config.get( 'pageTriageNamespaces' ).indexOf( ns ) === -1 ||
		ns === mw.config.get( 'wgPageTriageDraftNamespaceId' )
	) {
		return;
	}

	// Append toolbar element to the body.
	// It needs to be near the top of the DOM for stacking context.
	const toolbar = document.createElement( 'div' );
	toolbar.id = 'mw-pagetriage-toolbar';
	$( 'body' ).append( toolbar );

	// Load the curation toolbar
	mw.loader.using( 'ext.pageTriage.toolbar' )
		.then( () => {
			// Fire the 'ready' hook
			mw.hook( 'ext.pageTriage.toolbar.ready' )
				.fire( actionQueue );
		} );
} );

module.exports = { actionQueue };

// public facing API
mw.pageTriage = { actionQueue };
