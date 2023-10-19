<template>
	<div class="mwe-vue-pt-article-row" :class="oddEvenClass">
		<div class="mwe-vue-pt-status-icon">
			<img
				:src="statusIcon.src"
				:title="statusIcon.title"
				alt=""
			>
		</div>
		<div class="mwe-vue-pt-info-pane">
			<div class="mwe-vue-pt-info-row">
				<div class="mwe-vue-pt-article">
					<span class="mwe-vue-pt-bold">
						<a :href="titleUrl" target="_blank">{{ title }}</a>
					</span>
					<span>
						(<a :href="historyUrl">{{ $i18n( 'pagetriage-hist' ).text() }}</a>)
					</span>
					<span>
						{{ $i18n( 'pagetriage-dot-separator' ).text() }}
						{{ $i18n( 'pagetriage-bytes', pageLen ).text() }}
						{{ $i18n( 'pagetriage-dot-separator' ).text() }}
						{{ $i18n( 'pagetriage-edits', revCount ).text() }}
						<span v-if="!isDraft">
							<span v-if="categoryCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
								{{ $i18n( 'pagetriage-no-categories' ).text() }}
							</span>
							<span v-if="categoryCount !== 0">
								{{ $i18n( 'pagetriage-dot-separator' ).text() }}
								{{ $i18n( 'pagetriage-categories', categoryCount ).text() }}
							</span>
							<span v-if="linkCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
								{{ $i18n( 'pagetriage-orphan' ).text() }}
							</span>
							<span v-if="recreated" class="mwe-vue-pt-metadata-warning">
								<a :href="previouslyDeletedLogLink">{{ $i18n( 'pagetriage-recreated' ).text() }}</a>
							</span>
						</span>
						<span v-if="referenceCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
							{{ $i18n( 'pagetriage-no-reference' ).text() }}
						</span>
					</span>
				</div>
				<div class="mwe-vue-pt-article-col-right mwe-vue-pt-bold">
					<cdx-info-chip
						v-if="newArticleWarning"
						status="warning"
						:title="$i18n( 'pagetriage-tag-warning-notice', articleAge ).text()"
					>
						{{ creationDatePretty }}
					</cdx-info-chip>
					<span v-else>
						{{ creationDatePretty }}
					</span>
				</div>
			</div>
			<div class="mwe-vue-pt-info-row">
				<div>
					<!-- if the username is suppressed, present it the same way as in core changelists -->
					<span v-if="creatorHidden" class="history-deleted mw-history-suppressed mw-userlink">
						{{ $i18n( 'rev-deleted-user' ).text() }}
					</span>
					<span v-else-if="creatorName">
						<creator-byline
							v-if="creatorName"
							:creator-name="creatorName"
							:creator-user-id="creatorUserId"
							:creator-auto-confirmed="creatorAutoConfirmed"
							:creator-user-page-exists="creatorUserPageExists"
							:creator-talk-page-exists="creatorTalkPageExists"
						></creator-byline>
						<span v-if="creatorUserId > 0">
							{{ $i18n( 'pagetriage-dot-separator' ).text() }}
							{{ $i18n( 'pagetriage-editcount', creatorEditCount, creatorRegistrationPretty ).text() }}
							<span v-if="creatorIsBot">
								{{ $i18n( 'pagetriage-dot-separator' ).text() }}
								{{ $i18n( 'pagetriage-author-bot' ).text() }}
							</span>
						</span>
						<span v-if="creatorBlocked" class="mwe-vue-pt-metadata-warning">
							{{ $i18n( 'pagetriage-author-blocked' ).text() }}
						</span>
					</span>
					<span v-else>
						{{ $i18n( 'pagetriage-no-author' ).text() }}
					</span>
				</div>
				<div class="mwe-vue-pt-article-col-right">
					<span v-if="lastAfcActionLabel">
						<span>
							{{ lastAfcActionLabel }}
						</span>
						<span>{{ reviewedUpdatedPretty }}</span>
					</span>
				</div>
			</div>
			<div class="mwe-vue-pt-info-row">
				<div class="mwe-vue-pt-snippet">
					{{ snippet }}
				</div>
				<div class="mwe-vue-pt-article-col-right review-button">
					<a
						:href="titleUrl"
						target="_blank"
					>
						<cdx-button action="progressive" weight="primary">
							{{ $i18n( 'pagetriage-triage' ).text() }}
						</cdx-button>
					</a>
				</div>
			</div>
			<div v-if="showOres" class="mwe-vue-pt-info-row">
				<div>
					<span>{{ $i18n( 'pagetriage-filter-predicted-class-heading' ).text() }}</span>
					<span>{{ oresArticleQuality }}</span>
				</div>
				<div class="mwe-vue-pt-article-col-right ores-pt-issues">
					<span>{{ $i18n( 'pagetriage-filter-predicted-issues-heading' ).text() }}</span>
					<span v-if="!oresDraftQuality && !( copyvio && showCopyvio )">
						{{ $i18n( 'pagetriage-filter-stat-predicted-issues-none' ).text() }}
					</span>
					<span v-if="oresDraftQuality" class="mwe-vue-pt-issue">
						{{ oresDraftQuality }}
					</span>
					<span v-if="copyvio && showCopyvio">
						<span v-if="oresDraftQuality">
							{{ $i18n( 'pagetriage-dot-separator' ).text() }}
						</span>
						<span class="mw-parser-output mwe-vue-pt-issue">
							<a
								:href="copyvioLink"
								target="_blank"
								class="external"
							>
								{{ $i18n( 'pagetriage-filter-stat-predicted-issues-copyvio' ).text() }}
							</a>
						</span>
					</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * An individual list item in the feed.
 */

const { CdxButton, CdxInfoChip } = require( '@wikimedia/codex' );
const CreatorByline = require( './CreatorByline.vue' );
const now = new Date();
// Basic validation for 'YYYYMMDDHHmmss' timestamps
const timestampValidator = ( value ) => {
	if ( typeof value !== 'string' ) {
		return false;
	}
	// allow empty values
	if ( value.length === 0 ) {
		return true;
	}
	// otherwise should be a 14 digit integer
	return !( value.length !== 14 ||
		isNaN( value ) ||
		isNaN( parseInt( value ) )
	);
};
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ListItem',
	components: {
		CdxButton,
		CdxInfoChip,
		CreatorByline
	},
	props: {
		position: { type: Number, required: true },
		/*
		 * Info from pagetriage_page_tags
		 * see: https://www.mediawiki.org/wiki/Extension:PageTriage#List_of_tags
         */
		// Creator information tags
		creatorUserId: { type: Number, required: true },
		creatorHidden: { type: Boolean, required: true },
		creatorName: { type: String, required: true },
		creatorEditCount: { type: Number, required: true },
		creatorRegistrationUTC: {
			type: String,
			required: false,
			default: '',
			validator( value ) { return timestampValidator( value ); }
		},
		creatorAutoConfirmed: { type: Boolean, required: true },
		creatorIsBot: { type: Boolean, required: true },
		creatorBlocked: { type: Boolean, required: true },
		// Deletion tags
		afdStatus: { type: Boolean, required: true },
		blpProdStatus: { type: Boolean, required: true },
		csdStatus: { type: Boolean, required: true },
		prodStatus: { type: Boolean, required: true },
		// Warning tags
		categoryCount: { type: Number, required: true },
		linkCount: { type: Number, required: true },
		referenceCount: { type: Number, required: true },
		recreated: { type: Boolean, required: true },
		// Page information tags
		pageLen: { type: Number, required: true },
		revCount: { type: Number, required: true },
		snippet: { type: String, required: true },
		// afc state tag
		afcState: {
			type: Number,
			required: true,
			validator( value ) {
				return [
					1, // unsubmitted
					2, // pending
					3, // under review
					4 // declined
				].indexOf( value ) !== -1;
			}
		},
		// copyvio tag; latest revision ID that has been tagged as a likely copyright violation. 0 if not tagged.
		copyvio: { type: Number, required: true },
		// patrol status codes
		// see: https://www.mediawiki.org/wiki/Extension:PageTriage#Status_codes
		patrolStatus: {
			type: Number,
			required: true,
			validator( value ) {
				return [
					0, // unreviewed
					1, // reviewed
					2, // patrolled
					3 // autopatrolled
				].indexOf( value ) !== -1;
			}
		},
		/*
		 * Other info from API
		 */
		title: { type: String, required: true },
		isRedirect: { type: Boolean, required: true },
		creatorUserPageExists: { type: Boolean, required: true },
		creatorTalkPageExists: { type: Boolean, required: true },
		creationDateUTC: {
			type: String,
			required: true,
			validator( value ) { return timestampValidator( value ); }
		},
		reviewedUpdatedUTC: {
			type: String,
			required: true,
			validator( value ) { return timestampValidator( value ); }
		},
		/*
		 * ORES data may be undefined if extension is not available
		 * a quick check of the results returned by the PageTriageList API
		 * shows results that align with this apps predicted class & ratings values
		 * such as: 'Start', 'Stub', 'Spam', C-class', along with other values
		 * such as: 'N/A', and ''
		 */
		oresArticleQuality: { type: String, default: undefined },
		oresDraftQuality: { type: String, default: undefined },
		// optional toolbar feature flag
		tbVersion: { type: String, default: null }
	},
	data: function () {
		return {
			showOres: mw.config.get( 'wgShowOresFilters' ),
			showCopyvio: mw.config.get( 'wgShowCopyvio' ),
			draftNamespaceId: mw.config.get( 'wgPageTriageDraftNamespaceId' ),
			timeOffset: parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] )
		};
	},
	computed: {
		statusIcon: function () {
			const imageBase = mw.config.get( 'wgExtensionAssetsPath' ) + '/PageTriage/modules/ext.pageTriage.views.newPagesFeed/images/';
			const img = {
				src: `${imageBase}icon_not_reviewed.png`,
				title: this.$i18n( 'pagetriage-page-status-unreviewed' ).text()
			};
			if ( !this.isDraft ) {
				if ( mw.config.get( 'wgPageTriageEnableEnglishWikipediaFeatures' ) && ( this.afdStatus || this.blpProdStatus || this.csdStatus || this.prodStatus ) ) {
					img.src = `${imageBase}icon_marked_for_deletion.png`;
					img.title = this.$i18n( 'pagetriage-page-status-delete' ).text();
				} else if ( this.patrolStatus === 3 ) {
					img.src = `${imageBase}icon_autopatrolled.png`;
					img.title = this.$i18n( 'pagetriage-page-status-autoreviewed' ).text();
				} else if ( this.patrolStatus !== 0 ) {
					img.src = `${imageBase}icon_reviewed.png`;
					img.title = this.$i18n( 'pagetriage-page-status-reviewed-anonymous' ).text();
				}
			}
			return img;
		},
		oddEvenClass: function () { return this.position % 2 === 0 ? 'mwe-vue-pt-article-row-even' : 'mwe-vue-pt-article-row-odd'; },
		isDraft: function () {
			const pageNamespaceId = ( new mw.Title( this.title ) ).getNamespaceId();
			return pageNamespaceId === this.draftNamespaceId;
		},
		titleUrl: function () {
			const params = {};
			// open feature flagged version of toolbar
			if ( this.tbVersion ) {
				// eslint-disable-next-line camelcase
				params.pagetriage_tb = this.tbVersion;
			}
			if ( this.isRedirect ) {
				params.redirect = 'no';
			}
			return mw.util.getUrl( this.title, params );
		},
		historyUrl: function () {
			return mw.util.getUrl( this.title, { action: 'history' } );
		},
		creationDatePretty: function () {
			return this.prettyTimestamp( this.creationDateUTC );
		},
		articleAge: function () {
			const creationDateParsed = moment.utc( this.creationDateUTC, 'YYYYMMDDHHmmss' );
			return Math.ceil( ( now - creationDateParsed ) / ( 1000 * 60 ) );
		},
		newArticleWarning: function () {
			return ( ( this.isDraft === undefined || this.isDraft === false ) && ( this.articleAge <= 60 ) );
		},
		creatorRegistrationPretty: function () {
			return this.prettyTimestamp( this.creatorRegistrationUTC );
		},
		reviewedUpdatedPretty: function () {
			return this.prettyTimestamp( this.reviewedUpdatedUTC );
		},
		lastAfcActionLabel: function () {
			if ( this.afcState === 2 ) {
				return this.$i18n( 'pagetriage-afc-date-label-submission' ).text();
			} else if ( this.afcState === 3 ) {
				return this.$i18n( 'pagetriage-afc-date-label-review' ).text();
			} else if ( this.afcState === 4 ) {
				return this.$i18n( 'pagetriage-afc-date-label-declined' ).text();
			}
			return '';
		},
		copyvioLink: function () {
			if ( this.copyvio === 0 ) {
				// Shouldn't be used
				return '';
			}

			// As of 2023, the valid values for this on the CopyPatrol side are: en, es, ar,
			// fr, simple. Splitting the wgServerName ensures that Simple English Wikipedia
			// correctly renders as "simple".
			const wikiLanguageCodeForCopyPatrolURL = mw.config.get( 'wgServerName' ).split( '.' )[ 0 ];

			return 'https://copypatrol.toolforge.org/' + wikiLanguageCodeForCopyPatrolURL +
				'?filter=all' +
				'&filterPage=' + ( new mw.Title( this.title ) ).getMainText() +
				'&drafts=' + ( this.isDraft ? '1' : '0' ) +
				'&revision=' + this.copyvio;
		},
		previouslyDeletedLogLink: function () {
			return mw.util.getUrl( 'Special:Log', { type: 'delete', page: this.title } );
		}
	},
	methods: {
		parseTimestamp: function ( utcTimestamp ) {
			return moment.utc( utcTimestamp, 'YYYYMMDDHHmmss' );
		},
		prettyTimestamp: function ( utcTimestamp ) {
			return this.parseTimestamp( utcTimestamp ).utcOffset( this.timeOffset ).format(
				this.$i18n( 'pagetriage-creation-dateformat' ).text()
			);
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
.mwe-vue-pt-metadata-warning::before {
	color: initial;
	content: ' · ';
}
.mwe-vue-pt-info-pane {
	padding: 0.6em;
	width: 100%;
}
.mwe-vue-pt-info-row {
	position: relative;
	& > div {
		display: table-cell;
		width: 100%;
	}
}
.mwe-vue-pt-status-icon {
	padding-left: 0.6em;
}
/* info about the article */
.mwe-vue-pt-article {
	font-size: 1.1em;
	line-height: 1.6em;
	/* Info on the right hand side: creation date, updated date, potential isues, etc. */
	&-col-right {
		text-align: right;
		white-space: nowrap;
	}
	&-col-right > .cdx-info-chip {
		vertical-align: bottom;
	}
	&-row {
		position: relative;
		border: 1px solid #ccc;
		border-top: 0;
		box-sizing: border-box;
		& > div {
			display: table-cell;
		}
		&-even {
			background-color: #f1f1f1;
		}
		&-odd {
			background-color: @background-color-base;
		}
	}
}
.mwe-vue-pt-bold {
	font-weight: bold;
}
.mwe-vue-pt-metadata-warning,
.mwe-vue-pt-metadata-warning > a,
.mwe-vue-pt-issue {
	color: #c00;
	font-weight: bold;
}
/* the article snippet */
.mwe-vue-pt-snippet {
	color: #808080;
	padding-right: 14em;
}
.review-button {
	position: absolute;
	bottom: 0.2em;
}
.ores-pt-issues {
	height: 0.55em;
}
.cdx-icon.cdx-info-chip__icon--warning {
	color: @color-warning;
}
</style>
