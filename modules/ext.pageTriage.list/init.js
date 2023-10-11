const { createMwApp } = require( 'vue' );
const { createPinia } = require( 'pinia' );
const pinia = createPinia();
const app = require( './App.vue' );

// get toolbar version parameter
const tbVersion = mw.util.getParamValue( 'pagetriage_tb' );
const props = {};

// Drop bad input instead of validating and throwing a warning
if ( [ 'new', 'old' ].indexOf( tbVersion ) !== -1 ) {
	props.tbVersion = tbVersion;
}

createMwApp( app, props )
	.use( pinia )
	.mount( '#mwe-pt-list' );
