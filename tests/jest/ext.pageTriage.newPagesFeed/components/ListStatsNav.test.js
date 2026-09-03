const { mount } = require( '@vue/test-utils' );
let ListStatsNav;
let wrapper;

describe( 'ListStatsNav.vue', () => {
	beforeEach( () => {
		ListStatsNav = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/ListStatsNav.vue' );
		wrapper = mount( ListStatsNav, {
			global: {
				mocks: {
					$i18n: ( key ) => ( {
						text: () => key === 'pagetriage-stats-not-available' ? 'N/A' : key
					} )
				}
			}
		} );
		wrapper.vm.calculateDiff = jest.fn().mockReturnValue( 42 );
	} );

	it( 'mounts', () => {
		expect( wrapper.exists() ).toBe( true );
		expect( wrapper.vm.showStats ).toBe( false );
	} );

	it( 'shows N/A for the oldest draft when the AFC queue is empty (T381043)', () => {
		wrapper = mount( ListStatsNav, {
			global: {
				mocks: {
					$i18n: ( key ) => ( {
						text: () => key === 'pagetriage-stats-not-available' ? 'N/A' : key
					} )
				}
			},
			props: {
				queueMode: 'afc',
				apiResult: {
					result: 'success',
					stats: {
						unrevieweddraft: {
							count: 0
						}
					}
				}
			}
		} );

		expect( wrapper.vm.unreviewedDraftCount ).toBe( 0 );
		// T381043:
		expect( wrapper.vm.unreviewedOldestDraft ).toBe( 'N/A' );
		expect( wrapper.vm.showDraftStats ).toBe( true );
	} );
} );
