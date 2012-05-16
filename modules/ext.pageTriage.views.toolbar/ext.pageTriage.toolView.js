// abstract class for individual tool views.  Basically just a set of defaults.
// extend this to make a new tool.

$( function() {
	mw.pageTriage.ToolView = Backbone.View.extend( {
		// These things will probably be overrideen with static values.  You can use a function
		// if you want to, though.
		//		
		icon: 'icon_default.png', // icon to display in the toolbar		
		activeIcon: 'icon_default_active.png', // the icon for when the item is selected
		disabledIcon: 'icon_default_disabled.png', // the grayed out icon
		title: 'Abstract tool view', // the title for the flyout window
		scrollable: false, // should the output of render be in a scrollable div?
		
		// These things will likely be overridden with functions.
		//		
		// function that returns the number of items to display in an icon badge
		// if null, badge won't be displayed.
		badgeCount: null,
		
		// function to bind to the icon's click handler
		// if not defined, runs render() and inserts the result into a flyout instead
		// useful for the "next" button, for example
		onClick: null,

		// generate the stuff that goes in this tool's flyout.
		render: 'this is some example html',

		// this is stuff you probably won't want to override
		chromeTemplate: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'toolbarView.html' } ),
		open: function() {
			// is an onClick present?  do that and return.

			// otherwise...
			// show the tool div.
			// set the contents to this.render()
		},

		close: function() {
			// hide the div
		},

		place: function() {
			// return the HTML for the closed up version of this tool.
			return this.chromeTemplate();
		}

	} );
} );
