const utils = require( '@vue/test-utils' );
const CreatorByLine = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/CreatorByline.vue' );
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
} );
