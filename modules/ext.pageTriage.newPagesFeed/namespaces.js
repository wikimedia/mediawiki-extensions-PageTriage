module.exports = {
	getNamespaceOptions: function () {
		const wgFormattedNamespaces = mw.config.get( 'wgFormattedNamespaces' ),
			pageTriageNamespaces = mw.config.get( 'pageTriageNamespaces' ),
			draftNamespaceId = mw.config.get( 'wgPageTriageDraftNamespaceId' ),
			draftIndex = pageTriageNamespaces.indexOf( draftNamespaceId );

		// Remove draft from namespaces shown in NPP controls
		if ( draftIndex !== -1 ) {
			pageTriageNamespaces.splice( draftIndex, 1 );
		}

		return pageTriageNamespaces.map( ( ns ) => ns === 0 ?
			mw.msg( 'pagetriage-filter-article' ) :
			wgFormattedNamespaces[ ns ] );
	}
};
