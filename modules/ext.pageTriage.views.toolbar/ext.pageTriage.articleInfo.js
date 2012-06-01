// view for displaying all the article metadata

$( function() {
	mw.pageTriage.ArticleInfoView = mw.pageTriage.ToolView.extend( {
		id: 'info',
		icon: 'icon_info.png', // the default icon
		title: gM( 'pagetriage-info-title'),
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'articleInfo.html' } ),

		badgeCount: function() {
			// calculate the badge count.
			return 0;
		},

		render: function() {
			// create the info view content here.
			// return the HTML that gets inserted.
			this.$tel.html( this.template( this.model.toJSON() ) );
		}
	} );

} );
