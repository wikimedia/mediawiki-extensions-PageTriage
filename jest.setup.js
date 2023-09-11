// dependencies provided by ext.pageTriage.external
global.$ = require( 'jquery' );
global._ = require( './modules/external/underscore.js' );
global.Backbone = require( './modules/external/backbone.js' );
Backbone.setDomLibrary( $ );
// mediawiki
const mockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' );
global.mw = mockMediaWiki();
global.mw.Map = Map;

class Message {
	text() {
		return '<message>';
	}
}
class Title {
}

class MessagePoster {
	post() {
		return Promise.resolve();
	}
}

class Api {
	get() {
		return Promise.resolve( { pagetriagestats: { stats: '' } } );
	}
}

class IntersectionObserver {
	observe() {
		return null;
	}
}

global.mw.Title = Title;
global.mw.Message = Message;
global.mw.messagePoster = {
	factory: {
		create: () => Promise.resolve( new MessagePoster() )
	}
};
global.mw.Api = Api;
global.IntersectionObserver = IntersectionObserver;
