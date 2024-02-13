<template>
	<cdx-message
		v-show="apiError"
		type="error"
		class="mwe-vue-pt-feed-items-error"
	>
		{{ $i18n( 'pagetriage-api-error' ).text() }}
	</cdx-message>
	<list-item
		v-for="feedEntry in feedEntries"
		v-bind="feedEntry"
		:key="feedEntry.position"
		:tb-version="tbVersion"
	></list-item>
	<cdx-message
		v-if="!feedEntries.length && !haveMoreToLoad"
		type="error"
		class="mwe-vue-pt-feed-items-error"
	>
		{{ $i18n( 'pagetriage-no-pages' ).text() }}
	</cdx-message>
	<load-more-bar :have-more="haveMoreToLoad" @trigger-load="loadFromFilters"></load-more-bar>
	<stats-bar
		:api-result="feedStats"
		:queue-mode="immediate.queueMode"
		@refresh-feed="refreshFeed"
	></stats-bar>
</template>

<script>
/**
 * @author DannyS712
 * Overall list contents.
 * Is given the api properties to query with and generates the items to show.
 */

/**
 * Convert the page information retrieved from the api into the properties
 * that listItemComponent expects.
 *
 * @param {Object} pageInfo
 *
 * @return {Object} listItemProps
 */
const listItemPropFormatter = ( pageInfo ) => {
	// the `position` prop is handled by the list
	const listItemProps = {};
	listItemProps.afdStatus = pageInfo.afd_status === '1';
	listItemProps.blpProdStatus = pageInfo.blp_prod_status === '1';
	listItemProps.csdStatus = pageInfo.csd_status === '1';
	listItemProps.prodStatus = pageInfo.prod_status === '1';
	listItemProps.patrolStatus = parseInt( pageInfo.patrol_status );
	listItemProps.title = pageInfo.title;
	listItemProps.isRedirect = pageInfo.is_redirect === '1';
	listItemProps.redirectTarget = pageInfo.redirect_target;
	listItemProps.categoryCount = parseInt( pageInfo.category_count );
	listItemProps.linkCount = parseInt( pageInfo.linkcount );
	listItemProps.referenceCount = parseInt( pageInfo.reference );
	listItemProps.recreated = !!pageInfo.recreated;
	listItemProps.pageLen = parseInt( pageInfo.page_len );
	listItemProps.revCount = parseInt( pageInfo.rev_count );
	listItemProps.creationDateUTC = pageInfo.creation_date_utc;
	listItemProps.creatorName = pageInfo.user_name;
	listItemProps.creatorHidden = pageInfo.creator_hidden;
	listItemProps.creatorAutoConfirmed = pageInfo.user_autoconfirmed === '1';
	listItemProps.creatorRegistrationUTC = pageInfo.user_creation_date;
	listItemProps.creatorUserId = parseInt( pageInfo.user_id );
	listItemProps.creatorEditCount = parseInt( pageInfo.user_editcount );
	listItemProps.creatorIsBot = pageInfo.user_bot === '1';
	listItemProps.creatorBlocked = pageInfo.user_block_status === '1';
	listItemProps.creatorUserPageExists = pageInfo.creator_user_page_exist;
	listItemProps.creatorTalkPageExists = pageInfo.creator_user_talk_page_exist;
	listItemProps.afcState = parseInt( pageInfo.afc_state );
	listItemProps.reviewedUpdatedUTC = pageInfo.ptrp_reviewed_updated;
	listItemProps.snippet = pageInfo.snippet;
	listItemProps.oresArticleQuality = pageInfo.ores_articlequality;
	listItemProps.oresDraftQuality = pageInfo.ores_draftquality;
	listItemProps.copyvio = pageInfo.copyvio || 0;
	return listItemProps;
};

const { ref, watch } = require( 'vue' );
const { CdxMessage } = require( '@wikimedia/codex' );
const ListItem = require( './ListItem.vue' );
const LoadMoreBar = require( './LoadMoreBar.vue' );
const StatsBar = require( './ListStatsNav.vue' );
const { useSettingsStore } = require( '../stores/settings.js' );
const { storeToRefs } = require( 'pinia' );
// @vue/component
module.exports = {
	name: 'ListContent',
	components: {
		ListItem,
		LoadMoreBar,
		StatsBar,
		CdxMessage
	},
	props: {
		// optional toolbar feature flag
		tbVersion: { type: String, default: null }
	},
	setup() {
		const settings = useSettingsStore();
		const API_PAGE_LIMIT = 20;
		const api = new mw.Api( {
			// specifying url allows for requests from jsdom
			ajax: { url: `${ mw.config.get( 'wgScriptPath' ) }/api.php` }
		} );
		const apiError = ref( false );
		const feedEntries = ref( [] );
		// incremented before being used
		const latestPosition = ref( 0 );
		// 0 is ignored; `offset` and `pageoffset` parameters
		const apiOffsets = ref( { normal: 0, page: 0 } );
		const haveMoreToLoad = ref( true );

		const alreadyLoading = ref( false );
		const onApiFailure = function ( _res, shouldRender ) {
			if ( shouldRender ) {
				apiError.value = true;
			}
			alreadyLoading.value = false;
			haveMoreToLoad.value = false;
		};
		const addPageToFeed = function ( pageInfo ) {
			const propData = listItemPropFormatter( pageInfo );
			propData.position = ( ++latestPosition.value );
			feedEntries.value.push( propData );
		};
		const processResult = function ( res ) {
			apiError.value = false;
			alreadyLoading.value = false;
			// Unexpected response
			if ( !res || !res.pagetriagelist || !res.pagetriagelist.pages ) {
				onApiFailure( res, true );
				return;
			// Recieved response with no results
			} else if ( !res.pagetriagelist.pages[ 0 ] ) {
				haveMoreToLoad.value = false;
				return;
			}
			haveMoreToLoad.value = false;

			const allPages = res.pagetriagelist.pages;
			if ( allPages.length > API_PAGE_LIMIT ) {
				// Have more to load
				allPages.pop();
				haveMoreToLoad.value = true;
			}
			for ( let iii = 0; iii < allPages.length; iii++ ) {
				addPageToFeed( allPages[ iii ] );
			}
			// offset with the last
			const lastPage = allPages[ allPages.length - 1 ];
			apiOffsets.value.normal = lastPage.creation_date_utc;
			apiOffsets.value.page = lastPage.pageid;
		};
		const addFromApi = function ( apiParams ) {
			apiParams.action = 'pagetriagelist';
			apiParams.offset = apiOffsets.value.normal;
			apiParams.pageoffset = apiOffsets.value.page;
			api.get( apiParams ).then(
				( res ) => processResult( res ),
				( res ) => onApiFailure( res, true )
			);
		};

		const feedStats = ref( {} );
		const processNewStats = function ( newStats ) {
			feedStats.value = newStats.pagetriagestats;
			// hack - the number of pages in the filtered list is used in a
			// different component (the menu bar at the top) and its easier
			// to fetch the stats here than to fetch in the parent, send the
			// data up via events
			settings.updateFilteredCount( newStats.pagetriagestats.stats.filteredarticle );
		};
		const updateStats = function ( params ) {
			// make a copy, and remove unknown params
			const apiParams = Object.assign( {}, params );
			delete apiParams.dir;
			delete apiParams.limit;
			delete apiParams.offset;
			delete apiParams.pageoffset;
			apiParams.action = 'pagetriagestats';
			api.get( apiParams ).then(
				( res ) => processNewStats( res ),
				( res ) => onApiFailure( res, false )
			);
		};
		const loadFromFilters = function () {
			if ( alreadyLoading.value === true ) {
				// race condition
				return;
			}
			alreadyLoading.value = true;
			// make a copy, and remove unknown params
			const paramsFromStore = Object.assign( {}, settings.params );
			delete paramsFromStore.mode;
			delete paramsFromStore.nppDir;
			delete paramsFromStore.version;
			addFromApi( paramsFromStore );
			updateStats( paramsFromStore );
		};
		const clearCurrentData = function () {
			feedEntries.value = [];
			latestPosition.value = 0;
			haveMoreToLoad.value = true;
			apiOffsets.value.normal = 0;
			apiOffsets.value.page = 0;
		};
		const refreshFeed = function () {
			clearCurrentData();
			loadFromFilters();
		};
		const { applied, immediate } = storeToRefs( settings );
		watch(
			applied,
			refreshFeed
		);

		return {
			apiError,
			feedEntries,
			haveMoreToLoad,
			immediate,
			loadFromFilters,
			refreshFeed,
			feedStats
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-feed-items-error {
	margin: @spacing-50;
}
</style>
