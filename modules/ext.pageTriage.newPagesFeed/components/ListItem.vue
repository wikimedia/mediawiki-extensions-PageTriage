<template>
	<div class="mwe-vue-pt-article-row" :class="oddEvenClass">
		<div class="mwe-vue-pt-status-icon">
			<cdx-icon
				:icon="statusIcon.icon"
				:title="statusIcon.title"
				:class="statusIcon.class"
			></cdx-icon>
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
						<span class="mwe-vue-pt-article-stats">
							{{ $i18n( 'pagetriage-dot-separator' ).text() }}
							{{ $i18n( 'pagetriage-bytes', pageLen ).text() }}
							{{ $i18n( 'pagetriage-dot-separator' ).text() }}
							{{ $i18n( 'pagetriage-edits', revCount ).text() }}
							<span v-if="categoryCount !== 0">
								{{ $i18n( 'pagetriage-dot-separator' ).text() }}
								{{ $i18n( 'pagetriage-categories', categoryCount ).text() }}
							</span>
						</span>
						<span class="mwe-vue-pt-problem-chips">
							<span v-if="!isDraft">
								<cdx-info-chip v-if="categoryCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
									{{ $i18n( 'pagetriage-no-categories' ).text() }}
								</cdx-info-chip>
								<cdx-info-chip v-if="linkCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
									<a :href="whatLinksHereLink">{{ $i18n( 'pagetriage-orphan' ).text() }}</a>
								</cdx-info-chip>
								<cdx-info-chip v-if="recreated" class="mwe-vue-pt-metadata-warning">
									<a :href="previouslyDeletedLogLink">{{ $i18n( 'pagetriage-recreated' ).text() }}</a>
								</cdx-info-chip>
							</span>
							<cdx-info-chip v-if="referenceCount === 0 && !isRedirect" class="mwe-vue-pt-metadata-warning">
								{{ $i18n( 'pagetriage-no-reference' ).text() }}
							</cdx-info-chip>
							<cdx-info-chip v-if="creatorBlocked" class="mwe-vue-pt-metadata-warning">
								<a :href="blockLogLink">{{ $i18n( 'pagetriage-author-blocked' ).text() }}</a>
							</cdx-info-chip>
							<cdx-info-chip v-if="oresDraftQuality" class="mwe-vue-pt-issue">
								{{ oresDraftQuality }}
							</cdx-info-chip>
							<span v-if="copyvio && showCopyvio">
								<cdx-info-chip class="mw-parser-output mwe-vue-pt-issue">
									<a
										:href="copyvioLink"
										target="_blank"
										class="external"
									>
										{{ $i18n( 'pagetriage-filter-stat-predicted-issues-copyvio' ).text() }}
									</a>
								</cdx-info-chip>
							</span>
						</span>
					</span>
				</div>
				<div class="mwe-vue-pt-article-col-right mwe-vue-pt-bold">
					<cdx-info-chip
						v-if="newArticleWarning"
						class="mwe-vue-pt-new-article-warning"
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
				<div class="mwe-vue-pt-info-row-block-left">
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
						</span>
						<span v-else>
							{{ $i18n( 'pagetriage-no-author' ).text() }}
						</span>
					</div>
					<div class="mwe-vue-pt-snippet">
						<span v-if="isRedirect">
							<cdx-icon
								:icon="redirectIcon.icon"
								dir="rtl"
								class="mwe-vue-pt-redirect-icon"></cdx-icon>
							<a :href="redirectTargetUrl" target="_blank">{{ redirectTarget }}</a>
						</span>
						<span v-else>
							{{ snippet }}
						</span>
					</div>
				</div>
				<div class="mwe-vue-pt-info-row-block-right">
					<div v-if="lastAfcActionLabel" class="mwe-vue-pt-article-col-right">
						<span>
							{{ lastAfcActionLabel }}
						</span>
						<span>{{ reviewedUpdatedPretty }}</span>
					</div>
					<div class="mwe-vue-pt-article-col-right review-button">
						<a
							:href="titleUrl"
							target="_blank"
						>
							<cdx-button>
								{{ $i18n( 'pagetriage-triage' ).text() }}
							</cdx-button>
						</a>
					</div>
				</div>
			</div>
			<div v-if="showOres" class="mwe-vue-pt-info-row">
				<div>
					<span>{{ $i18n( 'pagetriage-filter-predicted-class-heading' ).text() }}</span>
					<span>{{ oresArticleQuality }}</span>
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
const { cdxIconError, cdxIconSuccess, cdxIconTrash, cdxIconNewline } = require( './icons.json' );
const { CdxButton, CdxIcon, CdxInfoChip } = require( '@wikimedia/codex' );
const CreatorByline = require( './CreatorByline.vue' );
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
		CdxIcon,
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
		redirectTarget: { type: String, required: false, default: null },
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
			const img = {
				icon: cdxIconError,
				title: this.$i18n( 'pagetriage-page-status-unreviewed' ).text(),
				class: 'mwe-vue-pt-page-status-unreviewed'
			};
			if ( !this.isDraft ) {
				if ( mw.config.get( 'wgPageTriageEnableExtendedFeatures' ) && ( this.afdStatus || this.blpProdStatus || this.csdStatus || this.prodStatus ) ) {
					img.icon = cdxIconTrash;
					img.title = this.$i18n( 'pagetriage-page-status-delete' ).text();
					img.class = 'mwe-vue-pt-page-status-delete';
				} else if ( this.patrolStatus === 3 ) {
					img.icon = cdxIconSuccess;
					img.title = this.$i18n( 'pagetriage-page-status-autoreviewed' ).text();
					img.class = 'mwe-vue-pt-page-status-autoreviewed';
				} else if ( this.patrolStatus !== 0 ) {
					img.icon = cdxIconSuccess;
					img.title = this.$i18n( 'pagetriage-page-status-reviewed-anonymous' ).text();
					img.class = 'mwe-vue-pt-page-status-reviewed';
				}
			}
			return img;
		},
		redirectIcon: function () {
			return { icon: cdxIconNewline };
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
		redirectTargetUrl: function () {
			return mw.util.getUrl( this.redirectTarget );
		},
		historyUrl: function () {
			return mw.util.getUrl( this.title, { action: 'history' } );
		},
		creationDatePretty: function () {
			return this.prettyTimestamp( this.creationDateUTC, true );
		},
		articleAge: function () {
			const creationDateParsed = moment.utc( this.creationDateUTC, 'YYYYMMDDHHmmss' );
			const now = new Date();
			return Math.ceil( ( now - creationDateParsed ) / ( 1000 * 60 ) );
		},
		newArticleWarning: function () {
			return ( ( this.isDraft === undefined || this.isDraft === false ) && ( this.articleAge <= 60 ) );
		},
		creatorRegistrationPretty: function () {
			return this.prettyTimestamp( this.creatorRegistrationUTC, false );
		},
		reviewedUpdatedPretty: function () {
			return this.prettyTimestamp( this.reviewedUpdatedUTC, true );
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
		},
		blockLogLink: function () {
			return mw.util.getUrl( 'Special:Log', { type: 'block', page: this.creatorName } );
		},
		whatLinksHereLink: function () {
			return mw.util.getUrl( 'Special:WhatLinksHere', {
				namespace: 0,
				hideredirs: 1,
				target: this.title
			} );
		}
	},
	methods: {
		parseTimestamp: function ( utcTimestamp ) {
			return moment.utc( utcTimestamp, 'YYYYMMDDHHmmss' );
		},
		prettyTimestamp: function ( utcTimestamp, includeTime ) {
			let format;
			if ( includeTime ) {
				format = this.$i18n( 'pagetriage-creation-dateformat' ).text();
			} else {
				format = this.$i18n( 'pagetriage-info-timestamp-date-format' ).text();
			}
			return this.parseTimestamp( utcTimestamp ).utcOffset( this.timeOffset ).format(
				format
			);
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-info-pane {
	padding: @spacing-50;
	width: 100%;
}

.mwe-vue-pt-info-row {
	position: relative;
	display: flex;
	width: 100%;

	&-block-right {
		margin-left: auto;
	}
}

.mwe-vue-pt-status-icon {
	padding-left: @size-75;
	padding-top: @size-75;
}
/* info about the article */
.mwe-vue-pt-article {
	margin-right: @spacing-50;

	/* Info on the right hand side: creation date, updated date, potential isues, etc. */
	&-col-right {
		margin-left: auto;
		text-align: right;
		white-space: nowrap;
	}

	&-col-right > .cdx-info-chip {
		vertical-align: bottom;
	}

	&-row {
		position: relative;
		border: @border-subtle;
		border-top: 0;
		box-sizing: border-box;
		display: flex;
	}
}

.mwe-vue-pt-bold {
	font-weight: bold;
}

.mwe-vue-pt-article-row-even {
	// darken( @background-color-interactive-subtle, 1% ) = #f5f6f8
	background: #f5f6f8;
}

.mwe-vue-pt-metadata-warning,
.mwe-vue-pt-issue {
	background: @background-color-destructive-subtle;
	border-color: @border-color-destructive;
	margin-left: @spacing-25;
	font-size: @size-75;

	@media screen and ( max-width: @max-width-breakpoint-mobile ) {
		margin-top: @spacing-25;
	}
}

.cdx-icon.mwe-vue-pt-page-status-unreviewed {
	color: @color-progressive;
}

.cdx-icon.mwe-vue-pt-page-status-delete {
	color: @color-base;
}

.cdx-icon.mwe-vue-pt-page-status-reviewed {
	color: @color-success;
}

.cdx-icon.mwe-vue-pt-page-status-autoreviewed {
	color: @color-visited;
}
/* the article snippet */
.mwe-vue-pt-snippet {
	color: @color-subtle;
	margin-right: @spacing-50;
	word-wrap: break-word;
	overflow-wrap: anywhere;
}

.review-button {
	padding: @spacing-25 @spacing-25 @spacing-25 0;
}

.ores-pt-issues {
	height: 0.55em;
}

.mwe-vue-pt-new-article-warning .cdx-icon.cdx-info-chip__icon--warning {
	color: @background-color-warning-subtle;
}

.mwe-vue-pt-new-article-warning.cdx-info-chip {
	background-color: @color-warning;
	border-color: @border-color-warning;
}

.mwe-vue-pt-new-article-warning > .cdx-info-chip--text {
	color: @color-emphasized;
}

.mwe-vue-pt-redirect-icon {
	color: @color-subtle;
}
</style>
