// dependencies provided by ext.pageTriage.external
global.$ = require( 'jquery' );
global._ = require( './modules/external/underscore.js' );
global.Backbone = require( './modules/external/backbone.js' );
Backbone.setDomLibrary( $ );
// mediawiki
const mockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' );
global.mw = mockMediaWiki();
