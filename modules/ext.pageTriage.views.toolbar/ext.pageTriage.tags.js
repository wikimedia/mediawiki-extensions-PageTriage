// view for displaying tags

$( function() {
	mw.pageTriage.TagsView = mw.pageTriage.ToolView.extend( {
		id: 'tag',
		icon: 'icon_tag.png', // the default icon
		title: 'Add Tags',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'tags.html' } ),

		render: function() {
			// create the info view content here.
			// return the HTML that gets inserted.
			return this.template( { 'tags': $.pageTriageTagsOptions, 'title': this.title } );
		}

	} );
} );
