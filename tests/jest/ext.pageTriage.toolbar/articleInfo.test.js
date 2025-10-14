const ArticleInfoView = require( '../../../modules/ext.pageTriage.toolbar/articleInfo.js' );
const { Article } = require( '../../../modules/ext.pageTriage.util/models/ext.pageTriage.article.js' );

describe( 'ArticleInfoToolView', () => {
	let toolbar;

	beforeEach( () => {
		toolbar = new ArticleInfoView( {
			model: new Article( { pageId: 5 } ),
			eventBus: _.extend( {}, Backbone.Events )
		} );
	} );

	const tagUrl = 'https://en.wikipedia.org/wiki/User:Admin';
	const tagText = 'Admin';

	test( 'show user tag for registered users', async () => {
		const tempTag = await toolbar.buildUserTag( tagUrl, tagText, true, false );

		expect( tempTag ).toHaveLength( 1 );
		expect( tempTag[ 0 ].href ).toBe( 'https://en.wikipedia.org/wiki/User:Admin' );
	} );

	test( 'show ip button visible with checkuser rights', async () => {
		mw.user.getRights = () => [ 'checkuser-temporary-account' ];
		const tempTag = await toolbar.buildUserTag( tagUrl, tagText, true, true );

		expect( tempTag ).toHaveLength( 3 );
		expect( tempTag[ 2 ].classList.contains( 'mwe-pt-info-show-ip' ) ).toBe( true );
	} );

	test( 'show ip button hidden without checkuser rights', async () => {
		mw.user.getRights = () => [ 'irrelevant' ];
		const tempTag = await toolbar.buildUserTag( tagUrl, tagText, true, true );

		expect( tempTag ).toHaveLength( 1 );
		expect( tempTag[ 0 ].href ).toBe( 'https://en.wikipedia.org/wiki/User:Admin' );
		expect( tempTag[ 0 ].classList.contains( 'mwe-pt-info-show-ip' ) ).toBe( false );
	} );
} );
