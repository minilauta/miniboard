const env = process.env.NODE_ENV
const path = require('path');

module.exports = {
  mode: env || 'development',
  entry: './public/js/index.js',
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'public', 'dist'),
    publicPath: '/dist/'
  },
  module: {
    rules: [
      {
        test: /\.m?js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {
                targets: [
                  'ie >= 6',
                  'firefox >= 12'
                ],
                debug: ['development', 'none'].includes(env) ? true : false,
                useBuiltIns: 'usage',
                corejs: '3.26'
              }]
            ]
          }
        }
      }
    ]
  },
  target: ['development', 'none'].includes(env) ? ['web'] : ['web', 'es5'],
  devServer: {
    proxy: {
      '/': 'http://127.0.0.1'
    },
    watchFiles: [
      'public/**/*.php',
      'public/**/*.phtml',
      'public/**/*.js',
      'public/**/*.css'
    ],
    open: true,
    compress: true,
    port: 9000
  }
}
