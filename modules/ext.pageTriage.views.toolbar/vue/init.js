const { createMwApp } = require( 'vue' );
const { createPinia } = require( 'pinia' );
const pinia = createPinia();
const backbone = require( './plugins/backbone.js' );
const app = require( './App.vue' );
const config = require( '../config.json' );
// Loads Vue with Backbone enabled for a mixed environment
module.exports = {
	newToolbar: ( props ) => {
		mw.loader.using( config.PageTriageCurationDependencies ).then( () => {
			createMwApp( app, props )
				.use( backbone )
				.use( pinia )
				.mount( '#mw-pagetriage-toolbar' );
		} );
	}
};
