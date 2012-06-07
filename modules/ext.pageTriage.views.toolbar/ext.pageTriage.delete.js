// view for marking a page as reviewed or unreviewed

$( function() {
	mw.pageTriage.DeleteView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-delete',
		icon: 'icon_trash.png', // the default icon
		title: 'Mark for Deletion',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'delete.html' } ),

		render: function() {
			this.$tel.html( this.template() );			
		}

	} );

} );
