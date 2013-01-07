// Minimize the toolbar

$( function() {

	mw.pageTriage.MinimizeView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-minimize',
		icon: 'icon_minimize.png', // the default icon
		title: '',
		tooltip: 'pagetriage-toolbar-minimize',

		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.toolbar = options.toolbar;
		},
		click: function() {

			// minimize the toolbar.
			this.toolbar.minimize();
		}

	} );

} );
