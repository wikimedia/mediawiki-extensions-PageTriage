module.exports = {
	moduleNameMapper: {
		'^./modules/(.+)/ext.pageTriage.(.+).underscore': '<rootDir>/modules/$1/$2.underscore',
		// backbone needs this defined here because of the way it checks for jquery & underscore
		underscore: '<rootDir>/modules/external/underscore.js'
	},
	collectCoverage: true,
	collectCoverageFrom: [
		'modules/**/*.(js|vue)'
	],
	coveragePathIgnorePatterns: [
		'/node_modules/'
	],
	coverageThreshold: {
		global: {
			branches: 4,
			functions: 6,
			lines: 9,
			statements: 9
		}
	},
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: [
		'<rootDir>/jest.setup.js'
	]
};
