/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/html/**/*.js",
    "./templates/html/**/*.html.twig",
  ],
  theme: {
    fontFamily: {
      'display': ['Oswald',],
      'body': ["'Source Sans 3'",],
    },
    extend: {
      backgroundImage: {
        'body-pattern': "url('../img/body-pattern.svg')",
      }
    },
  },
  plugins: [],
}

