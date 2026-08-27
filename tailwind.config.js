/** @type {import('tailwindcss').Config} */
// Paleta e tipografia: docs/product/identidade-visual.md
module.exports = {
  content: ['./src/Views/**/*.php', './public/**/*.php'],
  theme: {
    extend: {
      colors: {
        base: '#050B0F',
        surface: '#101B2C',
        'surface-2': '#16212A',
        border: '#24374D',
        cyan: {
          DEFAULT: '#0AFFEF',
          light: '#5CFFF3',
          dark: '#00B8AE',
        },
        text: {
          primary: '#F2FAFB',
          secondary: '#8CA3AC',
          muted: '#7C929C',
        },
        success: '#34D399',
        warning: '#FBBF24',
        danger: '#F87171',
        info: '#818CF8',
      },
      fontFamily: {
        sans: ['Manrope', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
    },
  },
  plugins: [],
};
