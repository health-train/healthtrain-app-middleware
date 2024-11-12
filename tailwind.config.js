/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  theme: {
    extend: {
      colors: {
        'ht-green': {
          20: '#EFFCF7',
          40: '#CBF2EB',
          60: '#5AD2B4',
          80: '#0DA190',
        },
        'ht-blue': {
          20: '#F4FBFF',
          40: '#D6EEFF',
          60: '#32A0FA',
          80: '#32A0FA',
        },
        'ht-orange': {
          20: '#FFF8EA',
          40: '#FFE6C1',
          60: '#FF9E3C',
          80: '#FF9E3C',
        },
        'ht-purple': {
          20: '#FBF5FF',
          40: '#E9E1FF',
          60: '#A582FF',
          80: '#A582FF',
        },
        'ht-text': '#1E2869'
      },
    },
  },
  plugins: [],
}