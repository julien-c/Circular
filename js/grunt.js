/*global module:false*/
module.exports = function(grunt) {
	
	// Project configuration.
	grunt.initConfig({
		meta: {
			version: '1.1',
			banner: '/*! Circular - v<%= meta.version %> - ' +
			'<%= grunt.template.today("yyyy-mm-dd") %>\n' +
			' * http://circular.io/\n' +
			' * Copyright (c) <%= grunt.template.today("yyyy") %> ' +
			'Julien Chaumond; Licensed MIT \n */\n\n'
		},
		concat: {
			dist: {
				src: [
					'vendor/jquery-1.7.2.min.js',
					'../bootstrap/js/bootstrap.js',
					'vendor/jquery-ui-1.8.22.custom.min.js',
					'vendor/jquery.hotkeys.js',
					'vendor/jquery.filedrop.js',
					'vendor/spin.min.js',
					'vendor/jstz.min.js',
					'vendor/mustache.js',
					'vendor/underscore.js',
					'vendor/backbone.js',
					'vendor/twitter-text-1.11.0.min.js',
					'src/bootstrap.js',
					'src/utils.js',
					'src/models/*.js',
					'src/collections/*.js',
					'src/views/*.js',
					'src/app.js',
					'src/start.js',
				],
				dest: 'circular.js'
			}
		},
		min: {
			dist: {
				src: ['<banner:meta.banner>', '<config:concat.dist.dest>'],
				dest: 'circular.min.js'
			}
		},
		watch: {
			files: '<config:concat.dist.src>',
			tasks: 'concat min'
		},
		uglify: {}
	});
	
	// Default task.
	grunt.registerTask('default', 'concat min');
	
};
