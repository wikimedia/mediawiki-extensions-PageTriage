// dependencies provided by ext.pageTriage.external
global.jQuery = require( 'jquery' );
global.$ = global.jQuery;
// enough jquery-ui for testing
global._ = require( './modules/external/underscore.js' );
global.Backbone = require( './modules/external/backbone.js' );
Backbone.setDomLibrary( $ );
// mediawiki
const mockMediaWiki = require( '@wikimedia/mw-node-qunit/src/mockMediaWiki.js' );
global.mw = mockMediaWiki();
global.mw.Map = Map;
global.mediaWiki = mw;

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
	saveOption() {
		return Promise.resolve( true );
	}
	postWithToken() {
		return Promise.resolve();
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
global.mw.user.options = new mw.Map();
