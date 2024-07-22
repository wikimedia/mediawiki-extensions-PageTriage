<template>
	<div id="mwe-pt-next" class="mwe-pt-tool">
		<a :href="uri">
			<tool-icon
				:title="disabled ? $i18n( 'pagetriage-next-tooltip-disabled' ).text() : $i18n( 'pagetriage-next-tooltip' ).text()"
				:disabled="disabled"
				file="icon_skip.png"
			></tool-icon>
		</a>
	</div>
</template>

<script>
/**
 * Navigate to the next article in the queue
 */
const { watch } = require( 'vue' );
const ToolIcon = require( './ToolIcon.vue' );

const client = new mw.Api( {
		// specifying url allows for requests from jsdom
		ajax: { url: `${ mw.config.get( 'wgScriptPath' ) }/api.php` }
	}, { timeout: 1 } ),
	defaultParams = {
		action: 'pagetriagelist',
		format: 'json',
		formatversion: 2
	};

// @vue/component
module.exports = {
	name: 'ToolNext',
	components: {
		ToolIcon
	},
	props: {
		page: {
			type: Object,
			required: true
		},
		pageTriageUi: {
			type: String,
			default: null
		}
	},
	data: function () {
		return {
			// Updated asynchronously from api
			uri: undefined,
			// Set to true if there is no next article
			disabled: false
		};
	},
	methods: {
		getNext: function ( page ) {
			if ( !page.creation_date_utc ) {
				return;
			}
			// make a copy
			const initParams = JSON.parse( mw.user.options.get( 'userjs-NewPagesFeedFilterOptions' ) );
			const params = Object.assign( {}, defaultParams, initParams );
			delete params.mode;
			params.limit = 1;
			params.offset = page.creation_date_utc;
			params.pageoffset = page.pageid;
			return client.get( params ).then( ( res ) => {
				// If API doesn't return content for next page
				// then user cannot advance to next page
				if ( !res.pagetriagelist || !res.pagetriagelist.pages || !res.pagetriagelist.pages[ 0 ] || !res.pagetriagelist.pages[ 0 ].title ) {
					return;
				}
				const nextPage = res.pagetriagelist.pages[ 0 ];
				const uri = new mw.Uri( mw.config.get( 'wgArticlePath' ).replace(
					'$1', mw.util.wikiUrlencode( nextPage.title )
				) );
				if ( nextPage.is_redirect === '1' ) {
					uri.query.redirect = 'no';
				}
				if ( this.pageTriageUi ) {
					// eslint-disable-next-line camelcase
					uri.query.pagetriage_ui = this.pageTriageUi;
				}
				return uri.toString();
			} )
				.then( ( uri ) => {
					this.uri = uri;
					this.disabled = typeof uri === 'undefined';
				} );
		}
	},
	mounted: function () {
		watch( this.page, this.getNext );
	}
};
</script>
