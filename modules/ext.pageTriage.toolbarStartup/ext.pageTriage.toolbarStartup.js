jQuery( function( $ ) {
	// only show curation toolbar for enabled namespaces
	if ( $.inArray( mw.config.get( 'wgNamespaceNumber' ),
		mw.config.get( 'wgPageTriageNamespaces' ) ) === -1 )
	{
		return true;
	}
	
	// Load the curation toolbar
	mw.loader.load( 'ext.pageTriage.views.toolbar' );
	
	// If the WikiLove module is activated, load WikiLove as well
	var modules = mw.config.get( 'wgPageTriageCurationModules' );
	if ( $.inArray( 'WikiLove', modules ) !== -1 ) {
		mw.loader.load( 'ext.wikiLove.init' );
	}
} );
