const ListControlNav = require( './ext.pageTriage.listControlNav.js' );
const ListItem = require( './ext.pageTriage.listItem.js' );
const ListStatsNav = require( './ext.pageTriage.listStatsNav.js' );
const init = require( './ext.pageTriage.listView.js' );

$( init );

mw.pageTriage.ListControlNav = ListControlNav;
mw.pageTriage.ListItem = ListItem;
mw.pageTriage.ListStatsNav = ListStatsNav;

module.exports = {
	ListControlNav,
	ListItem,
	ListStatsNav
};
