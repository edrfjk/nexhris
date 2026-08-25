/** @type {import('tailwindcss').Config} */
export default {
  // Scoped to the directories the app actually renders. The stock
  // welcome.blade.php is unreachable (/ redirects to /login) and inlines a
  // whole Tailwind v4 dump, whose arbitrary-value class names would otherwise
  // be scanned and emitted as dead rules.
  content: [
    "./resources/views/admin/**/*.blade.php",
    "./resources/views/employee/**/*.blade.php",
    "./resources/views/auth/**/*.blade.php",
    "./resources/views/components/**/*.blade.php",
    "./resources/views/layouts/**/*.blade.php",
    // Pages that live outside the role folders. Left off this list they are
    // never scanned, so any class used only there generates no CSS at all and
    // the page renders half-styled — which is how My Profile and My Leave
    // Ledger came to look unlike the rest of the system.
    "./resources/views/announcements/**/*.blade.php",
    "./resources/views/leave/**/*.blade.php",
    "./resources/views/notifications/**/*.blade.php",
    "./resources/views/profile/**/*.blade.php",
    "./resources/views/public/**/*.blade.php",
    "./resources/js/**/*.js",
    "./vendor/blade-ui-kit/blade-heroicons/resources/svg/**/*.svg",
  ],
  theme: {
    extend: {
      colors: {
        // Sampled from the official ISPSC Tagudin seal:
        //   maroon #780000 (34% of the mark), gold #F0DC00 (18%),
        //   forest #145000 (14%), cream #F0F0DC.
        // Each ramp is anchored on the exact seal colour and tapered by hand
        // so the mid-tones stay warm instead of going neon.
        maroon: {
          50:  '#fdf5f5',
          100: '#fbe8e8',
          200: '#f5cccc',
          300: '#eba8a8',
          400: '#db7070',
          500: '#c44545',
          600: '#a82c2c',
          700: '#8f1414',
          800: '#780000',
          900: '#5c0000',
          950: '#3d0000',
        },
        gold: {
          50:  '#fefce8',
          100: '#fdf9c4',
          200: '#fbf28c',
          300: '#f8e94a',
          400: '#f0dc00',
          500: '#d4c200',
          600: '#ad9c00',
          700: '#8a7c00',
          800: '#726600',
          900: '#5f5500',
          950: '#383200',
        },
        forest: {
          50:  '#f2f9ef',
          100: '#e2f2dc',
          200: '#c4e4ba',
          300: '#9bcf8b',
          400: '#6cb356',
          500: '#48952f',
          600: '#33771d',
          700: '#245e12',
          800: '#145000',
          900: '#103d00',
          950: '#0a2600',
        },
        // Warm neutral drawn from the seal's cream, so greys sit beside the
        // maroon without the cold blue cast slate has.
        sand: {
          50:  '#fbfbf7',
          100: '#f5f5ee',
          200: '#eceadf',
          300: '#dbd8c8',
          400: '#b3b0a1',
          500: '#8a8778',
          600: '#6b6859',
          700: '#514f43',
          800: '#3a382f',
          900: '#26251f',
        },
        ispscgreen: '#145000',
        ispscgold: '#f0dc00',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      spacing: {
        // Icons are drawn at 18px throughout. Without this, every w-4.5 in
        // the templates silently produced nothing and those icons rendered
        // at their intrinsic 24px, larger than their neighbours.
        4.5: '1.125rem',
      },
      borderRadius: {
        DEFAULT: '0.5rem',
      },
      boxShadow: {
        // Soft, layered elevation — no hard 1px rings.
        soft:  '0 1px 2px rgb(38 37 31 / 0.04), 0 2px 8px -2px rgb(38 37 31 / 0.06)',
        lift:  '0 2px 4px rgb(38 37 31 / 0.05), 0 8px 20px -6px rgb(38 37 31 / 0.12)',
        inset: 'inset 0 1px 2px rgb(38 37 31 / 0.05)',
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
  plugins: [],
}
