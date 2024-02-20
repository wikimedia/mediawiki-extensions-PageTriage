const jq = typeof $ !== 'undefined' ? $ : require( 'jquery' );
const us = typeof _ !== 'undefined' ? _ : require( '../../modules/external/underscore.js' );
const bb = typeof Backbone !== 'undefined' ? Backbone : require( '../../modules/external/backbone.js' );
bb.setDomLibrary( jq );
module.exports = {
	install: ( app ) => {
		app.config.globalProperties._ = us;
		app.config.globalProperties.Backbone = bb;
	}
};
