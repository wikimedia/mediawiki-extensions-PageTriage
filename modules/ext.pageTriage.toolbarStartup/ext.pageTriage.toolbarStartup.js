jQuery( function () {
	var modules,
		ns = mw.config.get( 'wgNamespaceNumber' );

	// Only show curation toolbar for enabled namespaces, minus the draftspace.
	if ( mw.config.get( 'pageTriageNamespaces' ).indexOf( ns ) === -1 ||
		ns === mw.config.get( 'wgPageTriageDraftNamespaceId' )
	) {
		return true;
	}

	// Load the curation toolbar
	mw.loader.load( 'ext.pageTriage.views.toolbar' );

	// If the WikiLove module is activated, load WikiLove as well
	modules = mw.config.get( 'wgPageTriageCurationModules' );
	if ( typeof modules.wikiLove !== 'undefined' ) {
		mw.loader.load( 'ext.wikiLove.init' );
	}
} );
