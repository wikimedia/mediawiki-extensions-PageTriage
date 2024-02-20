const tbVersion = mw.util.getParamValue( 'pagetriage_tb' ),
	{ newToolbar } = require( './vue/init.js' ),
	{ oldToolbar } = require( './ToolbarView.js' );
// Currently, there is only a single flag
// but this could have different flags for migrating individual tools/buttons
// within the new toolbar once it is the old toolbar is dropped
if ( tbVersion === 'new' ) {
	newToolbar( { tbVersion } );
} else {
	oldToolbar( { tbVersion } );
}
