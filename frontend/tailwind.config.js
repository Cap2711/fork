/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        'duo-green': 'var(--duo-green)',
        'duo-green-hover': 'var(--duo-green-hover)',
        'duo-blue': 'var(--duo-blue)',
        'duo-blue-hover': 'var(--duo-blue-hover)',
        'duo-red': 'var(--duo-red)',
        'duo-yellow': 'var(--duo-yellow)',
        'duo-purple': 'var(--duo-purple)',
      },
    },
  },
  plugins: [],
}
