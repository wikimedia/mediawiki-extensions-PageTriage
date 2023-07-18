const { Article } = require( 'ext.pageTriage.util' );
let TagToolView;
describe( 'TagToolView', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		TagToolView = require( '../../../modules/ext.pageTriage.views.toolbar/tags.js' );
	} );

	test( 'talkPageNote', () => {
		const actionQueue = {
			runAndRefresh: jest.fn()
		};
		mw.pageTriage = { actionQueue };
		const eventBus = _.extend( {}, Backbone.Events );
		const model = new Article( {
			eventBus,
			pageId: 5,
			includeHistory: true
		} );
		// pageTriageTagsOptions will not import for some reason.
		const tagsOptions = { all: {} };
		const toolbar = new TagToolView( { tagsOptions, eventBus, model } );
		return toolbar.talkPageNote( 'foo' ).then( () => {
			expect( actionQueue.runAndRefresh ).toBeCalled();
		} );
	} );
} );
