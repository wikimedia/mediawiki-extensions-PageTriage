let Article,
	TagToolView;
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
		TagToolView = require( '../../../modules/ext.pageTriage.toolbar/tags.js' );
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

	test( 'extractTagFromWikitext', () => {
		// setup our toolbar
		const model = new Article( {
			pageId: 5,
			includeHistory: true
		} );
		const toolbar = new TagToolView( { tagsOptions: {}, model } );

		// actually test the function
		expect( toolbar.extractTagFromWikitext( '{{abc}}', 'abc' ) ).toBe( '{{abc}}' );
		expect( toolbar.extractTagFromWikitext( '{{abc}} {{bcd}}', 'abc' ) ).toBe( '{{abc}}' );
		expect( toolbar.extractTagFromWikitext( '{{abc|{{bcd}}}}', 'abc' ) ).toBe( '{{abc|{{bcd}}}}' );
		expect( toolbar.extractTagFromWikitext( '{{abc|{{target}}{{subst:REVISIONUSER}}}}', 'target' ) ).toBe( '{{target}}' );
	} );

	test( 'addToExistingTags', () => {
		// setup our toolbar
		const model = new Article( {
			pageId: 5,
			includeHistory: true
		} );
		const toolbar = new TagToolView( { tagsOptions: {}, model } );

		// actually test the function
		expect( toolbar.addToExistingTags(
			`
{{Multiple issues|
{{notability}}
{{should be deleted}}
}}

PageTriage is the best.
			`, 'Multiple issues',
			`
{{advert}}
{{peacock}}`, 'top', true ) ).toBe(
			`
{{Multiple issues|
{{notability}}
{{should be deleted}}
{{advert}}
{{peacock}}
}}

PageTriage is the best.
			` );

		expect( toolbar.addToExistingTags( '', 'Multiple issues', '{{advert}}', 'top', true ) ).toBe( '{{Multiple issues|{{advert}}\n}}\n' );
		expect( toolbar.addToExistingTags( 'Txt', 'Multiple issues', '{{advert}}', 'top', false ) ).toBe( '{{advert}}\nTxt' );
		expect( toolbar.addToExistingTags( 'Text', 'mu', '{{advert}}', 'bottom', true ) ).toBe( 'Text\n{{mu|{{advert}}\n}}' );
	} );

	test( 'redirects should be wrapped', () => {
		const model = new Article( {
			pageId: 5,
			includeHistory: true
		} );

		const tagsOptions = {
			redirects: {
				tags: {
					'R-from-initialism': {
						label: '{{R from initialism}}',
						tag: 'R from initialism',
						desc: 'redirect from an initialism (e.g. AGF) to its expanded form',
						position: 'redirectTag',
						multiple: true
					}
				}
			}
		};

		const selectedTags = {
			redirects: tagsOptions.redirects.tags
		};

		$.pageTriageTagsRedirectCategoryShell = 'Redirect category shell';

		jest.spyOn( TagToolView.prototype, 'fetchArticleContent' ).mockImplementation( () => Promise.resolve( '#REDIRECT [[Hello]]' ) );

		const applyTags = jest.spyOn( TagToolView.prototype, 'applyTags' ).mockImplementation( () => {
		} );

		const toolbar = new TagToolView( { tagsOptions, model } );

		toolbar.selectedTag = selectedTags;

		return toolbar.submit().then( () => {
			expect( applyTags ).toBeCalledWith( '#REDIRECT [[Hello]]\n{{Redirect category shell|\n{{R from initialism}}\n}}', [ 'r from initialism' ] );
		} );
	} );

	test( 'selecting from the all category should not cause duplications', () => {
		const model = new Article( {
			pageId: 5,
			includeHistory: true
		} );

		const tagsOptions = {
			sources: {
				tags: {
					disputed: {
						label: 'Accuracy issues',
						tag: 'disputed',
						desc: 'This page has questionable factual accuracy.',
						params: {
							date: {
								label: 'date',
								input: 'required',
								type: 'textarea',
								value: 'today'
							}
						},
						position: 'top',
						multiple: true
					},
					linkrot: {
						label: 'Link rot',
						tag: 'linkrot',
						desc: 'This page has issues with Link rot',
						params: {
							date: {
								label: 'date',
								input: 'required',
								type: 'textarea',
								value: 'today'
							}
						},
						position: 'top',
						multiple: true
					}
				}
			}
		};

		$.pageTriageTagsMultiple = 'Multiple issues';

		jest.spyOn( TagToolView.prototype, 'fetchArticleContent' ).mockImplementation( () => Promise.resolve( 'This is a page.' ) );

		const applyTags = jest.spyOn( TagToolView.prototype, 'applyTags' ).mockImplementation( () => {
		} );

		const toolbar = new TagToolView( { tagsOptions: JSON.parse( JSON.stringify( tagsOptions ) ), model } );

		toolbar.selectedTag = {
			all: tagsOptions.sources.tags
		};

		return toolbar.submit().then( () => {
			expect( applyTags ).toBeCalledWith( '{{Multiple issues|\n{{disputed|date=today}}\n{{linkrot|date=today}}\n}}\nThis is a page.', [ 'disputed', 'linkrot' ] );
		} );
	} );
} );
