let mix = require('laravel-mix')
let path = require('path')

require('./nova.mix')

mix
  .setPublicPath('dist')
  .js('resources/js/field.js', 'js')
  .vue({ version: 3 })
  .css('resources/css/field.css', 'css')
  .alias({
    '#': path.join(__dirname, 'resources/js/'),
    //'@': path.join(__dirname, 'vendor/laravel/nova/resources/js')
    '@': path.join(__dirname, '../../vendor/laravel/nova/resources/js')
  })
  .nova('formfeed/nova-dependable-panel')
