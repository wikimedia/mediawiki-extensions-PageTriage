// view for displaying all the article metadata

$( function() {
	mw.pageTriage.ArticleInfoView = mw.pageTriage.ToolView.extend( {
		icon: '', // the default icon
		activeIcon: '', // the icon for when the item is selected
		disabledIcon: '', // the grayed out icon
		title: 'Page Info',
		
		badgeCount: function() {
			// calculate the badge count.
			return 0;
		},
		
		render: function() {
			// create the info view content here.
			// return the HTML that gets inserted.
			return "<i>article info html</i>";
		}

	} );	
	
} );
