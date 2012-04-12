// abstract class for individual tool views.  Basically just a set of defaults.
// extend this to make a new tool.

$( function() {
	mw.pageTriage.ToolView = Backbone.View.extend( {
		// icon to display in the toolbar
		toolbarIcon: 'icon_default.png',
		
		// function that returns the number of items to display in an icon badge
		badgeCount: 0,
		
		// function to bind to the icon's click handler
		// if not defined, runs render() and inserts the result into a flyout instead
		// useful for the "next" button, for example
		clickAction: null
	} );
} );
