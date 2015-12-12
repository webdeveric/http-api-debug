module.exports = function( grunt ) {
    'use strict';

    var config = {
        pkg: grunt.file.readJSON('package.json'),
        jshint: {
            files: ['Gruntfile.js', 'js/src/**/*.js', '!js/src/**/*.min.js', '!js/src/modernizr.js'],
            options: {
                globals: {
                    jQuery: true,
                    console: true,
                    module: true,
                    document: true
                }
            }
        },
        uglify: {
            build: {
                options: {
                    banner: '/* <%= pkg.name %> <%= pkg.version %> <%= grunt.template.today("yyyy-mm-dd") %> */',
                    sourceMap: true, //'js/dist/<%= pkg.name %>.js',
                    // mangle: false,
                    preserveComments: 'some',
                    compress: {
                        // drop_console: true
                    }
                },
                files: [ {
                    expand: true,
                    cwd: 'js/src/',
                    src: '**/*.js',
                    dest: 'js/dist',
                    ext: '.min.js'
                } ]
            }
        },

        compass: {
            css: {
                options: {
                    sassDir: 'sass',
                    cssDir: 'css/src'
                }
            }
        },

        sass: {
            dist: {
                files: [ {
                    expand: true,
                    cwd: 'sass',
                    src: ['*.sass', '*.scss'],
                    dest: 'css/src',
                    ext: '.css'
                } ]
            }
        },

        cssmin: {
            options: {
                banner: '/* <%= pkg.name %> <%= pkg.version %> <%= grunt.template.today("yyyy-mm-dd") %> */',
            },
            minify:{
                expand: true,
                cwd: 'css/src/',
                src: ['*.css', '!*.min.css'],
                dest: 'css/dist/',
                ext: '.min.css'
            }
        },
        svgmin: {
            options: {
                plugins: [
                  { removeViewBox: false },
                  { removeUselessStrokeAndFill: false }
                ]
            },
            dist: {
                files: [{
                    expand: true,
                    cwd: 'imgs/src/',
                    src: ['**/*.svg'],
                    dest: 'imgs/dist/',
                    ext: '.min.svg'
                }]
            }
        },
        imagemin: {
            png: {
                options: {
                    optimizationLevel: 7
                },
                files: [ {
                    expand: true,
                    cwd: 'imgs/src/',
                    src: ['**/*.png'],
                    dest: 'imgs/dist/',
                    ext: '.png'
                } ]
            },
            jpg: {
                options: {
                    progressive: true
                },
                files: [ {
                    expand: true,
                    cwd: 'imgs/src/',
                    src: ['**/*.jpg'],
                    dest: 'imgs/dist/',
                    ext: '.jpg'
                } ]
            },
            gif: {
                files: [ {
                    expand: true,
                    cwd: 'imgs/src/',
                    src: ['**/*.gif'],
                    dest: 'imgs/dist/',
                    ext: '.gif'
                } ]
            }
        },
		checkwpversion: {
		    options:{
		        readme: 'readme.txt',
		        plugin: '<%= pkg.name %>.php',
		    },
		    check: {
		        version1: 'plugin',
		        version2: 'readme',
		        compare: '=='
		    },
		    check2: {
		        version1: 'plugin',
		        version2: '<%= pkg.version %>',
		        compare: '==',
		    }
		},
        watch: {
        	/*
            checkwpversion: {
                files: ['readme.txt', 'package.json', '<%= pkg.name %>.php'],
                tasks: ['checkwpversion']
            },
            */
            sass: {
                files: ['sass/**/*.{scss,sass}'],
                tasks: ['compass', 'cssmin']
            },
            js: {
                files: '<%= jshint.files %>',
                tasks: ['jshint', 'uglify']
            },
            svgimgs: {
                files: ['imgs/src/**/*.svg'],
                tasks: ['svgmin'],
                options: {
                    spawn: false
                }
            },
            pngimgs: {
                files: ['imgs/src/**/*.png'],
                tasks: ['imagemin:png'],
                options: {
                    spawn: false
                }
            },
            jpgimgs: {
                files: ['imgs/src/**/*.jpg'],
                tasks: ['imagemin:jpg'],
                options: {
                    spawn: false
                }
            },
            gifimgs: {
                files: ['imgs/src/**/*.gif'],
                tasks: ['imagemin:gif'],
                options: {
                    spawn: false
                }
            },
            livereload: {
                options: {
                    livereload: true
                },
                files: [
                    'imgs/dist/**/*',
                    'css/dist/**/*',
                    'js/dist/**/*',
                ]
            }
        }
    };

    grunt.config.init( config );

    require("load-grunt-tasks")( grunt );

    grunt.registerTask( 'test', [ 'checkwpversion', 'jshint' ] );
    grunt.registerTask( 'default', [ 'compass', 'jshint', 'uglify', 'cssmin' ] );
};
