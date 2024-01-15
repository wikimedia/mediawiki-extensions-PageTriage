'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	LocalSettingsSetup = require( __dirname + '/LocalSettingsSetup.cjs' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	maxInstances: 4,
	onPrepare: async function () {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	},
	onComplete: async function () {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
