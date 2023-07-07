module.exports = {
	moduleNameMapper: {
		'ext.pageTriage.defaultTagsOptions': '<rootDir>/modules/ext.pageTriage.defaultTagsOptions/main.js',
		'ext.pageTriage.util': '<rootDir>/modules/ext.pageTriage.util/main.js',
		'^./modules/(.+)/ext.pageTriage.(.+).underscore': '<rootDir>/modules/$1/$2.underscore',
		// backbone needs this defined here because of the way it checks for jquery & underscore
		underscore: '<rootDir>/modules/external/underscore.js'
	},
	collectCoverage: true,
	collectCoverageFrom: [
		'modules/**/*.(js|vue)'
	],
	coveragePathIgnorePatterns: [
		'/modules/external/',
		'/node_modules/'
	],
	coverageThreshold: {
		global: {
			branches: 4,
			functions: 6,
			lines: 6,
			statements: 6
		}
	},
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: [
		'<rootDir>/jest.setup.js'
	]
};
