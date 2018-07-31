// Register the Underscore.js "JavaScript micro-templating" compiler with MediaWiki.
( function () {
	mw.template.registerCompiler( 'underscore', {
		compile: function ( src ) {
			return _.template( src );
		}
	} );
}() );
