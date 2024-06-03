$( () => {
	let config;
	if ( mw.config.get( 'wgPageTriageUserCanPatrol' ) ) {
		config = require( './enqueue.js' );
	} else if ( mw.config.get( 'wgPageTriageUserCanAutoPatrol' ) ) {
		config = require( './unreview.js' );
	} else {
		return;
	}

	const sideBarLink = mw.util.addPortletLink( ...config.portletConfig );

	sideBarLink.addEventListener( 'click', () => {
		mw.loader.using( [ 'oojs-ui-core' ] ).then( () => {
			config.onClick();
		} );
	} );
} );
