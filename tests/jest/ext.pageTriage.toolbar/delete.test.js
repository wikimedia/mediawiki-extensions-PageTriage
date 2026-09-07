let Article, DeleteToolView, model, eventBus;

describe( 'DeleteToolView', () => {
	beforeEach( () => {
		eventBus = _.extend( {}, Backbone.Events );
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				case 'wgArticleId':
					return 5;
				default:
					return null;
			}
		} );
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		Article = require( 'ext.pageTriage.util' ).Article;
		model = new Article( {
			eventBus,
			pageId: 5,
			includeHistory: true
		} );

		DeleteToolView = require( '../../../modules/ext.pageTriage.toolbar/delete.js' );
	} );

	test( 'notifyUser with no talk page template', () => {
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( () => {
			expect( true ).toBe( true );
		} );
	} );

	test( 'notifyUser with talk page template', () => {
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false,
			talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
			talkpagenotiftpl: 'Db-foreign-notice-NPF'
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( () => {
			expect( true ).toBe( true );
		} );
	} );

	test( 'notifyUser without talkpagenotiftopictitle', () => {
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false,
			talkpagenotiftpl: 'Db-foreign-notice-NPF'
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( () => {
			expect( true ).toBe( true );
		} );
	} );

	test( 'tagPage inserts deletion tags after a short description', () => {
		const postWithToken = jest.spyOn( mw.Api.prototype, 'postWithToken' )
			.mockResolvedValue( {} );
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag = {
			g11: {
				tag: 'db-g11',
				params: {}
			}
		};

		return toolbar.tagPage( '{{Short description|Foo}}\n\nYou should buy this product. It\'s great. Wikipedia says so.' ).then( () => {
			const posted = postWithToken.mock.calls[ 0 ][ 1 ].wikitext;
			expect( posted ).toBe(
				'{{Short description|Foo}}\n\n{{db-g11}}\n\nYou should buy this product. It\'s great. Wikipedia says so.\n'
			);
		} );
	} );

	test( 'tagPage replaces the page when blank is set', () => {
		const postWithToken = jest.spyOn( mw.Api.prototype, 'postWithToken' )
			.mockResolvedValue( {} );
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag = {
			attack: {
				tag: 'db-attack',
				params: {},
				blank: true
			}
		};

		return toolbar.tagPage( '{{Short description|Foo}}\n\n Evil, evil attack page. Very bad.' ).then( () => {
			expect( postWithToken.mock.calls[ 0 ][ 1 ].wikitext ).toBe( '{{db-attack}}' );
		} );
	} );

	test( 'tagPage replaces the page when wrapTagAroundPage is set', () => {
		const postWithToken = jest.spyOn( mw.Api.prototype, 'postWithToken' )
			.mockResolvedValue( {} );
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag = {
			rfd: {
				tag: 'rfd',
				params: {
					content: {
						input: 'pagecontent'
					}
				},
				wrapTagAroundPage: true
			}
		};

		return toolbar.tagPage( '#REDIRECT [[Foo]]' ).then( () => {
			expect( postWithToken.mock.calls[ 0 ][ 1 ].wikitext )
				.toBe( '{{rfd|content=#REDIRECT [[Foo]]}}' );
		} );
	} );
} );
