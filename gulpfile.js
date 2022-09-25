const postcss = require('gulp-postcss')
const gulp = require('gulp')
const concat = require('gulp-concat')
const minify = require('gulp-minify')
const rename = require('gulp-rename')
const browserSync = require('browser-sync').create()

gulp.task('css', function () {
  return gulp
    .src('./public/css/*.css')
    .pipe(postcss())
    .pipe(rename({
      suffix: '.min',
      extname: '.css'
    }))
    .pipe(gulp.dest('./public/dist'))
})

gulp.task('js', function () {
  return gulp
    .src('./public/js/*.js')
    .pipe(minify({noSource: true}))
    .pipe(concat('bundle.min.js'))
    .pipe(gulp.dest('./public/dist'))
})

gulp.task('build', gulp.series('css', 'js'))

gulp.task('watch', function () {
  return gulp.watch([
    './public/css/*.css',
    './public/js/*.js',
    './public/**/*.php',
    './public/**/*.phtml'
  ], gulp.series('css', 'js', 'browser-sync-reload'))
})

gulp.task('browser-sync', function () {
  browserSync.init({
    proxy: '127.0.0.1',
    notify: false,
    cors: true
  })
})

gulp.task('browser-sync-reload', function (done) {
  browserSync.reload()
  done()
})

gulp.task('default', gulp.series('css', 'js', gulp.parallel('browser-sync', 'watch')))
