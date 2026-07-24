/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        maroon: {
          700: '#7a1f1f',
          800: '#5c1717',
          900: '#3d0f0f',
        },
        ispscgreen: '#1f5c1f',
        ispscgold: '#f4c430',
      },
    },
  },
  plugins: [],
}