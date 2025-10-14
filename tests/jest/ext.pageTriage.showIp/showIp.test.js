const { showIp, getRevisionId, getIpAddress } = require( '../../../modules/ext.pageTriage.showIp/showIp.js' );
describe( 'showIp.js', () => {
	test( 'getIpAddress', () => {
		const deferred = $.Deferred();
		getIpAddress( '~2025-3939', '', deferred, 5 );

		deferred.promise().then( ( ipResult ) => {
			expect( ipResult ).toEqual( '127.0.0.1' );
		} );
	} );

	test( 'getRevisionById', () => {
		const deferred = $.Deferred();
		class Api {
			get() {
				return Promise.resolve( { query: { pages: [ {
					revisions: [ {
						revid: '1'
					} ]
				} ]
				}
				} );
			}
		}

		global.mw.Api = Api;
		const api = new mw.Api();
		getRevisionId( 'any title', api, deferred );
		deferred.promise().then( ( { revid } ) => {
			expect( revid ).toEqual( 1 );
		} );
	} );

	test( 'getRevisionById when no pages', () => {
		const deferred = $.Deferred();
		class Api {
			get() {
				return Promise.resolve( { query: { pages: [ ] } } );
			}
		}

		global.mw.Api = Api;
		const api = new mw.Api();
		getRevisionId( 'any title', api, deferred );
		deferred.promise().then( ( { revid } ) => {
			expect( revid ).toEqual( undefined );
		} );
	} );

	test( 'getRevisionById when no revisions', () => {
		const deferred = $.Deferred();
		class Api {
			get() {
				return Promise.resolve( { query: { pages: [ {
					revisions: [ ] } ]
				}
				} );
			}
		}

		global.mw.Api = Api;
		const api = new mw.Api();
		getRevisionId( 'any title', api, deferred );
		deferred.promise().then( ( { revid } ) => {
			expect( revid ).toEqual( undefined );
		} );
	} );

	test( 'showIp adds the show ip link when placeholder in the dom', () => {
		const divContent = '<div class="mwe-vue-pt-status-icon"><span class="cdx-icon cdx-icon--medium mwe-vue-pt-page-status-unreviewed" title="This page is still unreviewed."></span></div> <div class="mwe-vue-pt-info-pane"><div class="mwe-vue-pt-info-row"><div class="mwe-vue-pt-article"><span class="mwe-vue-pt-bold"><a href="/wiki/NewPage2" target="_blank">NewPage2</a></span> <span>(<a href="/w/index.php?title=NewPage2&amp;action=history">hist</a>)</span> <span><span class="mwe-vue-pt-article-stats"> ·  7 bytes  ·  2 edits <!--v-if--></span> <span class="mwe-vue-pt-problem-chips-container"><div class="cdx-info-chip cdx-info-chip--notice mwe-vue-pt-problem-chip"><!--v-if--><span class="cdx-info-chip__text">No categories</span></div> <div class="cdx-info-chip cdx-info-chip--notice mwe-vue-pt-problem-chip"><!--v-if--><span class="cdx-info-chip__text"><a href="/w/index.php?title=Special:WhatLinksHere&amp;namespace=0&amp;hideredirs=1&amp;target=NewPage2">Possible orphan</a></span></div> <!--v-if--> <div class="cdx-info-chip cdx-info-chip--notice mwe-vue-pt-problem-chip"><!--v-if--><span class="cdx-info-chip__text">No citations</span></div> <!--v-if--> <!--v-if--> <!--v-if--></span></span></div> <div class="mwe-vue-pt-article-col-right mwe-vue-pt-bold"><span>18:45, 19 February 2025</span></div></div> <div class="mwe-vue-pt-info-row"><div class="mwe-vue-pt-info-row-block-left"><div><span><span>Created by new editor</span> <a href="/w/index.php?title=User:~2025-39&amp;action=edit&amp;redlink=1" class="cdx-link mw-tempuserlink">~2025-39</a> <a class="ext-page-triage-tempaccount-show-ip-link cdx-link"></a> (<a href="/w/index.php?title=User_talk:~2025-39&amp;action=edit&amp;redlink=1" class="cdx-link is-red-link">talk</a>  |  <a href="/wiki/Special:Contributions/~2025-39">contribs</a>) <span> ·  2 edits since 19 February 2025 <!--v-if--></span></span></div> <div class="mwe-vue-pt-snippet"><span></span></div></div> <div class="mwe-vue-pt-info-row-block-right"><!--v-if--> <div class="mwe-vue-pt-article-col-right review-button"><a href="/wiki/NewPage2" target="_blank"><button class="cdx-button cdx-button--action-default cdx-button--weight-normal cdx-button--size-medium cdx-button--framed">Review</button></a></div></div></div> <!--v-if--></div>';
		const $html = $( divContent );
		$html.find( '.ext-page-triage-tempaccount-show-ip-link' ).each( ( i, e ) => {
			expect( $( e ).text() ).toBe( '' );
		} );
		showIp( $html );
		$html.find( '.ext-page-triage-tempaccount-show-ip-link' ).each( ( i, e ) => {
			expect( $( e ).text() ).toBe( 'pagetriage-new-page-feed-show-ip' );
		} );
	} );

	test( 'showIp does not add the show ip link when placeholder not in the dom', () => {
		const divContent = '<div class="mwe-vue-pt-status-icon"><span class="cdx-icon cdx-icon--medium mwe-vue-pt-page-status-unreviewed" title="This page is still unreviewed."></span></div><div class="mwe-vue-pt-info-pane"></div>';
		const $html = $( divContent );
		$html.find( '.ext-page-triage-tempaccount-show-ip-link' ).each( ( i, e ) => {
			expect( $( e ).text() ).toBe( '' );
		} );
		showIp( $html );
		$html.find( '.ext-page-triage-tempaccount-show-ip-link' ).each( ( i, e ) => {
			expect( $( e ).text() ).toBe( '' );
		} );
	} );
} );
