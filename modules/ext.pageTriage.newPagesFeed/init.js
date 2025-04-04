const { createMwApp } = require( 'vue' );
const { createPinia } = require( 'pinia' );
const pinia = createPinia();
const app = require( './App.vue' );

// get toolbar version parameter
const pageTriageUi = mw.util.getParamValue( 'pagetriage_ui' );
const props = {};

// Drop bad input instead of validating and throwing a warning
if ( [ 'old' ].includes( pageTriageUi ) ) {
	props.pageTriageUi = pageTriageUi;
}

createMwApp( app, props )
	.use( pinia )
	.mount( '#mwe-pt-list' );
