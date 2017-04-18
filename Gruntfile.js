/*!
 * Grunt file
 *
 * @package PageTriage
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'**/*.js',
				'!node_modules/**',
				'!modules/external/*.js'
			]
		},
		jscs: {
			src: '<%= jshint.all %>'
		},
		stylelint: {
			all: [
				'**/*.css',
				'!node_modules/**'
			]
		},
		banana: {
			all: 'i18n/'
		},
		watch: {
			files: [
				'.{jscsrc,jshintignore,jshintrc}',
				'<%= jshint.all %>',
				'<%= jsonlint.all %>'
			],
			tasks: 'test'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'.stylelintrc',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'lint', [ 'jshint', 'jscs', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
