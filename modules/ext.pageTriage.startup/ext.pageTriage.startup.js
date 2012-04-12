jQuery( function( $ ) {
	
	if( mw.config.get( 'wgNamespaceNumber' ) !== 0 ) {
		return true;
	}

	// check to see if the curation toolbar should load here.
	mw.loader.load( 'ext.pageTriage.views.toolbar' );
} );
