const postcss = require('gulp-postcss')
const gulp = require('gulp')
const concat = require('gulp-concat')
const browserSync = require('browser-sync').create()

gulp.task('css', function () {
  return gulp
    .src('./public/css/*.css')
    .pipe(postcss())
    .pipe(concat('bundle.min.css'))
    .pipe(gulp.dest('./public/dist'))
})

gulp.task('build', gulp.series('css'))

gulp.task('watch', function () {
  return gulp.watch('./public/css/*.css', gulp.series('css', 'browser-sync-reload'))
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

gulp.task('default', gulp.series('css', gulp.parallel('browser-sync', 'watch')))
