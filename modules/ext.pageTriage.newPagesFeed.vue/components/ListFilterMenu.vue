<template>
	<template v-if="haveDraftNamespace">
		<queue-mode-tab></queue-mode-tab>
	</template>
	<div id="mwe-vue-pt-menu-heading" class="mwe-vue-pt-control-gradient">
		<showing-text></showing-text>
		<div class="mwe-pt-vue-menu-section">
			<div id="mwe-vue-pt-control-menu-toggle">
				<cdx-button
					:aria-pressed="settings.controlMenuOpen"
					action="progressive"
					@click="toggleControlMenu">
					{{ $i18n( 'pagetriage-filter-set-button' ).text() }}
				</cdx-button>
				<!-- Dropdown goes within the toggle with absolute position to overlay the feed -->
				<div
					v-if="settings.controlMenuOpen"
					id="mwe-vue-pt-control-dropdown"
					class="mwe-vue-pt-control-gradient"
				>
					<div v-show="settings.immediate.queueMode === 'npp'" class="mwe-vue-pt-control-section__row1">
						<div class="mwe-vue-pt-control-section__col1">
							<control-section
								v-if="namespaceOptions.length > 1"
								label-msg="pagetriage-filter-namespace-heading"
							>
								<select v-model="settings.unsaved.nppNamespace">
									<option
										v-for="( namespace, i ) in namespaceOptions"
										:key="`pagetriage-filter-namespace-${i}`"
										:value="i"
									>
										{{ namespace }}
									</option>
								</select>
							</control-section>
							<control-section label-msg="pagetriage-filter-show-heading">
								<labeled-checkbox
									v-model:checked="settings.unsaved.nppIncludeUnreviewed"
									label-msg="pagetriage-filter-unreviewed-edits"
								></labeled-checkbox>
								<labeled-checkbox
									v-model:checked="settings.unsaved.nppIncludeReviewed"
									label-msg="pagetriage-filter-reviewed-edits"
								></labeled-checkbox>
							</control-section>
							<control-section label-msg="pagetriage-filter-type-show-heading">
								<labeled-checkbox
									v-model:checked="settings.unsaved.nppIncludeNominated"
									label-msg="pagetriage-filter-nominated-for-deletion"
								></labeled-checkbox>
								<labeled-checkbox
									v-model:checked="settings.unsaved.nppIncludeRedirects"
									label-msg="pagetriage-filter-redirects"
								></labeled-checkbox>
								<labeled-checkbox
									v-model:checked="settings.unsaved.nppIncludeOthers"
									label-msg="pagetriage-filter-others"
								></labeled-checkbox>
							</control-section>
							<date-control-section
								v-model:from="settings.unsaved.nppDate.from"
								v-model:to="settings.unsaved.nppDate.to"
								type="npp"
							></date-control-section>
						</div>
						<control-section label-msg="pagetriage-filter-second-show-heading">
							<npp-filter-radio
								v-model:filter="settings.unsaved.nppFilter"
								v-model:user="settings.unsaved.nppFilterUser"
							>
							</npp-filter-radio>
						</control-section>
						<div v-if="showOresFilters" class="mwe-vue-pt-control-section__col2">
							<control-section label-msg="pagetriage-filter-predicted-class-heading">
								<labeled-checkbox
									v-for="( _, rating ) in settings.unsaved.nppPredictedRating"
									:key="`${rating}-${settings.unsaved.nppPredictedRating[ rating ]}`"
									v-model:checked="settings.unsaved.nppPredictedRating[ rating ]"
									:label-msg="'pagetriage-filter-predicted-class-' + rating"
								>
								</labeled-checkbox>
							</control-section>
							<control-section label-msg="pagetriage-filter-predicted-issues-heading">
								<labeled-checkbox
									v-for="( _, issue ) in settings.unsaved.nppPossibleIssues"
									:key="`${issue}-${settings.unsaved.nppPossibleIssues[ issue ]}`"
									v-model:checked="settings.unsaved.nppPossibleIssues[ issue ]"
									:label-msg="'pagetriage-filter-predicted-issues-' + issue"
								>
								</labeled-checkbox>
							</control-section>
						</div>
					</div>
					<div v-show="settings.immediate.queueMode === 'afc'" class="mwe-vue-pt-control-section__row1">
						<div class="mwe-vue-pt-control-section__col1">
							<control-section label-msg="pagetriage-filter-show-heading">
								<afc-state-radio v-model:state="settings.unsaved.afcSubmissionState"
								></afc-state-radio>
							</control-section>
							<date-control-section
								v-if="showOresFilters"
								v-model:from="settings.unsaved.afcDate.from"
								v-model:to="settings.unsaved.afcDate.to"
								type="afc"
							></date-control-section>
						</div>
						<template v-if="showOresFilters">
							<div class="mwe-vue-pt-control-section__col2">
								<control-section label-msg="pagetriage-filter-predicted-class-heading">
									<labeled-checkbox
										v-for="( _, rating ) in settings.unsaved.afcPredictedRating"
										:key="`${rating}-${settings.unsaved.afcPredictedRating[ rating ]}`"
										v-model:checked="settings.unsaved.afcPredictedRating[ rating ]"
										:label-msg="'pagetriage-filter-predicted-class-' + rating"
									>
									</labeled-checkbox>
								</control-section>
							</div>
							<div class="mwe-vue-pt-control-section__col3">
								<control-section label-msg="pagetriage-filter-predicted-issues-heading">
									<labeled-checkbox
										v-for="( _, issue ) in settings.unsaved.afcPossibleIssues"
										:key="`${issue}-${settings.unsaved.afcPossibleIssues[ issue ]}`"
										v-model:checked="settings.unsaved.afcPossibleIssues[ issue ]"
										:label-msg="'pagetriage-filter-predicted-issues-' + issue"
									>
									</labeled-checkbox>
								</control-section>
							</div>
						</template>
						<div v-else class="mwe-vue-pt-control-section__col2">
							<date-control-section
								v-model:from="settings.unsaved.afcDate.from"
								v-model:to="settings.unsaved.afcDate.to"
								type="afc"
							></date-control-section>
						</div>
					</div>

					<div class="mwe-vue-pt-control-buttons">
						<cdx-button
							action="progressive"
							weight="primary"
							:disabled="!canSaveSettings"
							@click="doSaveSettings"
						>
							{{ $i18n( 'pagetriage-filter-set-button' ).text() }}
						</cdx-button>
						<cdx-button
							action="destructive"
							weight="quiet"
							@click="settings.reset"
						>
							{{ $i18n( 'pagetriage-filter-reset-button' ).text() }}
						</cdx-button>
					</div>
				</div>
			</div>
			<div v-show="settings.immediate.queueMode === 'npp'" class="mwe-vue-pt-control-label-right mwe-vue-pt-sort-section">
				<npp-sort-dir-radio></npp-sort-dir-radio>
			</div>
			<div v-show="settings.immediate.queueMode === 'afc'" class="mwe-vue-pt-control-label-right mwe-vue-pt-sort-section">
				<afc-sort-select></afc-sort-select>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Controls for filtering feed content
 */

const { computed } = require( 'vue' );
const ControlSection = require( './ControlSection.vue' );
const DateControlSection = require( './DateControlSection.vue' );
const LabeledCheckbox = require( './LabeledCheckbox.vue' );
const QueueModeTab = require( './QueueModeTab.vue' );
const AfcSortSelect = require( './AfcSortSelect.vue' );
const AfcStateRadio = require( './AfcStateRadio.vue' );
const NppSortDirRadio = require( './NppSortDirRadio.vue' );
const NppFilterRadio = require( './NppFilterRadio.vue' );
const ShowingText = require( './ShowingText.vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( '../stores/settings.js' );
const { getNamespaceOptions } = require( '../namespaces.js' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ListFilterMenu',
	components: {
		ControlSection,
		DateControlSection,
		LabeledCheckbox,
		QueueModeTab,
		NppSortDirRadio,
		NppFilterRadio,
		AfcSortSelect,
		AfcStateRadio,
		ShowingText,
		CdxButton
	},
	setup() {
		const settings = useSettingsStore();
		settings.loadApiParams();
		// Need to include at least one of reviewed/unreviewed, and at least
		// one of nominated for deletion/redirects/normal articles
		const canSaveSettings = computed( () => {
			return (
				( settings.unsaved.nppIncludeUnreviewed || settings.unsaved.nppIncludeReviewed ) &&
				(
					settings.unsaved.nppIncludeNominated ||
					settings.unsaved.nppIncludeRedirects ||
					settings.unsaved.nppIncludeOthers
				)
			);
		} );
		const doSaveSettings = function () {
			settings.update( settings.unsaved );
			settings.controlMenuOpen = false;
		};
		const toggleControlMenu = () => {
			settings.controlMenuOpen = !settings.controlMenuOpen;
		};
		return {
			// settings
			settings,
			// housekeeping
			toggleControlMenu,
			canSaveSettings,
			doSaveSettings
		};
	},
	data: function () {
		return {
			// pure data, not needed in setup()
			haveDraftNamespace: !!mw.config.get( 'wgPageTriageDraftNamespaceId', true ),
			showOresFilters: mw.config.get( 'wgShowOresFilters', true ),
			namespaceOptions: getNamespaceOptions()
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

#mwe-vue-pt-menu-heading {
	padding: @spacing-75 @spacing-100 @spacing-100 @spacing-100;
	top: 0;
}

.mwe-vue-pt-control-options {
	margin-left: @spacing-50;
}

.mwe-vue-pt-control-label {
	white-space: nowrap;
	padding: @spacing-25;
}

.mwe-vue-pt-control-label-right {
	float: right;
}

.mwe-vue-pt-sort-section {
	flex: 0 0 50%;
	display: inline-flex;
	justify-content: end;

	.cdx-label {
		padding-bottom: 0;
	}
}

.mwe-vue-pt-control-section {
	padding-bottom: @spacing-35;
}

.mwe-vue-pt-control-buttons {
	margin: @spacing-25 0 0 0;

	.cdx-button {
		float: right;
		margin-left: @spacing-50;
	}
}

#mwe-vue-pt-control-dropdown {
	background-color: @background-color-interactive-subtle;
	position: absolute;
	z-index: @z-index-dropdown;
	border: @border-subtle;
	padding: @spacing-100;
	border-radius: @border-radius-base;
	cursor: default;
	box-shadow: @box-shadow-drop-medium;
	width: min-content;
}

#mwe-vue-pt-control-dropdown fieldset {
	padding-top: 0;
	padding-bottom: @spacing-25;
	padding-left: @spacing-75;
	margin-top: @spacing-12;
	margin-bottom: @spacing-12;
}

.mwe-pt-vue-menu-section {
	display: flex;
	width: 100%;
}

#mwe-vue-pt-control-menu-toggle {
	flex: 0 0 50%;
}

.mwe-vue-pt-control-section__row1 {
	display: flex;
	flex-direction: row;
}

.mwe-vue-pt-control-options input:hover {
	cursor: @cursor-base--hover;
}

.mwe-vue-pt-control-options .cdx-radio {
	margin-bottom: @spacing-50;
}
</style>
