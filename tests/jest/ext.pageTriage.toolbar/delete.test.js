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

	test( 'T41319: does not re-tag when the deletion template is already present', () => {
		const postWithToken = jest.spyOn( mw.Api.prototype, 'postWithToken' )
			.mockResolvedValue( {} );
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.deletionTagsOptions = {
			proposeddeletion: {
				multiple: false,
				reviewed: '0'
			}
		};
		toolbar.selectedCat = 'proposeddeletion';
		toolbar.selectedTag = {
			blpprod: {
				tag: 'blp-prod',
				params: {}
			}
		};

		jest.spyOn( toolbar, 'fetchArticleContent' )
			.mockResolvedValue( '{{subst:blp-prod}}\nLead.' );
		const handleError = jest.spyOn( toolbar, 'handleError' ).mockImplementation( () => {
		} );
		const tagPage = jest.spyOn( toolbar, 'tagPage' );

		return toolbar.submit().then( () => {
			expect( handleError ).toHaveBeenCalled();
			expect( tagPage ).not.toHaveBeenCalled();
			expect( postWithToken ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'displayTags', () => {
		const originalElement = mw.html.element;

		beforeEach( () => {
			// Simplified mw.html.element, not provided by the mock
			mw.html.element = ( name, attrs, contents ) => {
				const $el = $( '<' + name + '>' );
				for ( const attr in attrs ) {
					if ( attrs[ attr ] !== false ) {
						$el.attr( attr, attrs[ attr ] === true ? attr : attrs[ attr ] );
					}
				}
				return $el.text( contents || '' ).prop( 'outerHTML' );
			};
		} );

		afterEach( () => {
			mw.html.element = originalElement;
			document.body.innerHTML = '';
		} );

		test( 'T242069: selecting another tag hides the details form of the previous tag', () => {
			// Parameter forms are hidden by default in delete.less
			document.body.innerHTML = '<style>.mwe-pt-delete-params-form { display: none; }</style>' +
				'<div id="mwe-pt-delete"></div>' +
				'<button id="mwe-pt-delete-submit-button"></button>';
			const toolbar = new DeleteToolView( { eventBus, model } );
			toolbar.deletionTagsOptions = {
				proposeddeletion: {
					label: 'Proposed deletion',
					multiple: false,
					tags: {
						blpprod: {
							label: 'Proposed Deletion (Living People)',
							tag: 'blp-prod',
							desc: '',
							params: {}
						},
						prod: {
							label: 'Proposed Deletion',
							tag: 'prod',
							desc: '',
							params: {
								1: {
									label: 'Details',
									input: 'required',
									type: 'textarea',
									value: ''
								}
							}
						}
					}
				}
			};
			toolbar.displayTags( 'proposeddeletion' );

			$( '#mwe-pt-delete-prod' ).trigger( 'click' );
			expect( $( '#mwe-pt-delete-params-form-prod' ).css( 'display' ) ).toBe( 'block' );
			expect( $( '#mwe-pt-delete-submit-button' ).prop( 'disabled' ) ).toBe( true );

			$( '#mwe-pt-delete-blpprod' ).trigger( 'click' );
			expect( Object.keys( toolbar.selectedTag ) ).toEqual( [ 'blpprod' ] );
			expect( $( '#mwe-pt-delete-params-form-prod' ).css( 'display' ) ).toBe( 'none' );
			expect( toolbar.visibleParamsFormCount ).toBe( 0 );
			expect( $( '#mwe-pt-delete-submit-button' ).prop( 'disabled' ) ).toBe( false );
		} );
	} );
} );
