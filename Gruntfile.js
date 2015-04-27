module.exports = function(grunt) {
  grunt.initConfig({
    sass: {
      options: {
        sourceMap: false
      },
      dist: {
        files: {
          'src/DrupalPackagist/Bundle/Resources/public/css/main.css': 'src/DrupalPackagist/Bundle/Resources/source/sass/main.scss'
        }
      },
    },
    watch: {
      sass: {
        files: ['src/DrupalPackagist/Bundle/Resources/source/sass/**/*.scss'],
        tasks: ['sass:dist']
      }
    }
  });

  grunt.loadNpmTasks('grunt-sass');
  grunt.loadNpmTasks('grunt-contrib-watch');

  grunt.registerTask('default', ['watch:sass']);
};
