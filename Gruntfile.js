module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'mpdebugmode.zip'
                },
                files: [
                    {src: ['controllers/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['classes/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'mpdebugmode/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'mpdebugmode/'},
                    {src: 'index.php', dest: 'mpdebugmode/'},
                    {src: 'mpdebugmode.php', dest: 'mpdebugmode/'},
                    {src: 'logo.png', dest: 'mpdebugmode/'},
                    {src: 'logo.gif', dest: 'mpdebugmode/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};