const utils = require( '@vue/test-utils' );
const CreatorByLine = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/CreatorByline.vue' );

const userInfoCardPreference = 'checkuser-userinfocard-enable';
const mountWithUserInfoCard = ( propsData ) => utils.mount( CreatorByLine, {
	propsData: Object.assign( {
		creatorUserId: 1,
		creatorName: 'name',
		creatorAutoConfirmed: true,
		creatorUserPageExists: true,
		creatorTalkPageExists: true
	}, propsData )
} );

let wrapper;
describe( 'CreatorByline.vue', () => {
	it( 'mounts', () => {
		wrapper = utils.mount( CreatorByLine );
		expect( wrapper.exists() ).toBe( true );
	} );

	it( 'mounts and adds mw-tempuserlink class when temp user true', () => {
		wrapper = utils.mount( CreatorByLine, {
			clone: false,
			propsData: {
				creatorUserId: 1,
				creatorName: 'name',
				creatorAutoConfirmed: true,
				creatorUserPageExists: false,
				creatorTalkPageExists: false,
				creatorIsTempAccount: true
			}
		} );
		expect( wrapper.vm.creatorIsTempAccount ).toBe( true );
		expect( wrapper.vm.userPageClass ).toBe( 'mw-tempuserlink' );
		expect( wrapper.vm.userPageTooltip ).toBe( '' );
	} );

	it( 'mounts and adds mw-tempuserlink class for expired temp account', () => {
		wrapper = utils.mount( CreatorByLine, {
			clone: false,
			propsData: {
				creatorUserId: 1,
				creatorName: 'name',
				creatorAutoConfirmed: true,
				creatorUserPageExists: false,
				creatorTalkPageExists: false,
				creatorIsTempAccount: true,
				creatorIsExpiredTempAccount: true
			}
		} );
		expect( wrapper.vm.creatorIsTempAccount ).toBe( true );
		expect( wrapper.vm.userPageClass ).toBe( 'mw-tempuserlink mw-tempuserlink-expired' );
		expect( wrapper.vm.userPageTooltip ).toBe( 'tempuser-expired-link-tooltip' );
	} );

	it( 'mounts and does not add mw-tempuserlink class when temp user false', () => {
		wrapper = utils.mount( CreatorByLine, { propsData: {
			creatorUserId: 1,
			creatorName: 'name',
			creatorAutoConfirmed: true,
			creatorUserPageExists: true,
			creatorTalkPageExists: true,
			creatorIsTempAccount: false } } );
		expect( wrapper.vm.creatorIsTempAccount ).toBe( false );
		expect( wrapper.vm.userPageClass ).toBe( '' );
		expect( wrapper.vm.userPageTooltip ).toBe( '' );
	} );

	it( 'mounts and adds is-red-link class when user page does not exist', () => {
		wrapper = utils.mount( CreatorByLine, { propsData: {
			creatorUserId: 1,
			creatorName: 'name',
			creatorAutoConfirmed: true,
			creatorUserPageExists: false,
			creatorTalkPageExists: true,
			creatorIsTempAccount: false } } );
		expect( wrapper.vm.creatorIsTempAccount ).toBe( false );
		expect( wrapper.vm.userPageClass ).toBe( 'is-red-link' );
		expect( wrapper.vm.userPageTooltip ).toBe( '' );
	} );

	it( 'mounts and does not add is-red-link class when user page does exist', () => {
		wrapper = utils.mount( CreatorByLine, { propsData: {
			creatorUserId: 1,
			creatorName: 'name',
			creatorAutoConfirmed: true,
			creatorUserPageExists: true,
			creatorTalkPageExists: true,
			creatorIsTempAccount: false } } );
		expect( wrapper.vm.creatorIsTempAccount ).toBe( false );
		expect( wrapper.vm.userPageClass ).toBe( '' );
		expect( wrapper.vm.userPageTooltip ).toBe( '' );
	} );

	describe( 'user info card button', () => {
		afterEach( () => {
			mw.user.options.delete( userInfoCardPreference );
		} );

		it( 'shows when the user enabled the user info card', () => {
			mw.user.options.set( userInfoCardPreference, '1' );
			wrapper = mountWithUserInfoCard();
			expect( wrapper.vm.showUserInfoCard ).toBe( true );
		} );

		it( 'does not show when the user did not enable the user info card', () => {
			wrapper = mountWithUserInfoCard();
			expect( wrapper.vm.showUserInfoCard ).toBe( false );
		} );

		it( 'does not show for a creator which is not registered', () => {
			mw.user.options.set( userInfoCardPreference, '1' );
			wrapper = mountWithUserInfoCard( { creatorUserId: 0 } );
			expect( wrapper.vm.showUserInfoCard ).toBe( false );
		} );
	} );
} );
