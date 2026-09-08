/** @type {import('tailwindcss').Config} */
// Paleta e tipografia: docs/product/identidade-visual.md (identidade final, 2026-08-28;
// migrada no código na fase de acabamento, 2026-09-03).
module.exports = {
  // src/Support incluído porque Labels::toneClasses() monta classes Tailwind
  // em PHP fora de src/Views — sem isso, o scanner de conteúdo não as vê e
  // elas somem do CSS compilado sem aviso nenhum (bug real: Icon.php usava
  // classes Tailwind pra tamanho e saíam gigantes, sem CSS nenhum aplicado).
  // Mesmo problema, versão JS: public/assets/js/**/*.js entrou porque
  // select-enhance.js monta className tipo "select-trigger"/"select-listbox"
  // em runtime — sem esse glob, ./public/**/*.php não batia em .js nenhum e
  // as classes de @layer components inteiras (select-shell/trigger/listbox/
  // option, is-active/is-selected/is-disabled) somiam do CSS compilado sem
  // erro nenhum, exatamente como o caso do Icon.php acima.
  content: [
    './src/Views/**/*.php',
    './src/Support/**/*.php',
    './public/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // "void" (não "base") de propósito — Tailwind já tem "base" na escala
        // padrão de font-size (text-base = 1rem). Duas fontes gerando a mesma
        // classe `.text-base` faziam ela carregar font-size E color juntos,
        // aplicando #050B0F sem querer em todo `text-base` do app usado só
        // pra tamanho (achado real: os números do seletor de intensidade em
        // /rules/new ficavam ilegíveis mesmo depois de trocar a cor deles).
        void: '#050B0F',
        surface: '#061830',
        'surface-2': '#0A2647',
        border: '#3D5266',
        'border-strong': '#4A6076',
        cyan: {
          DEFAULT: '#00D0F0',
          bright: '#7FE8FF',
          pressed: '#00A8C4',
          dark: '#0092B0',
        },
        blue: {
          light: '#6FCFFF',
        },
        text: {
          primary: '#F0F8FF',
          secondary: '#8FA6BC',
          muted: '#7B8FA1',
        },
        success: '#3DF07A',
        warning: '#FFC53D',
        danger: '#FF5C7A',
        info: '#6FCFFF',
      },
      fontFamily: {
        display: ['Orbitron', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ['"Chakra Petch"', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      backgroundImage: {
        // Gradiente do símbolo da marca (círculo atrás do ícone) — não usar como cor de texto.
        'brand-grad': 'linear-gradient(135deg, #05A8C6, #06405A)',
      },
    },
  },
  plugins: [],
};
