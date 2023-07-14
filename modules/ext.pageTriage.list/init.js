const { createMwApp } = require( 'vue' );
const { createPinia } = require( 'pinia' );
const pinia = createPinia();
const app = require( './App.vue' );
createMwApp( app )
	.use( pinia )
	.mount( '#mw-content-text' );
