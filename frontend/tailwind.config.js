/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
        },
        tone: {
          neutral: '#6b7280',
          warm: '#10b981',
          cold: '#3b82f6',
          irritated: '#ef4444',
          anxious: '#f59e0b',
        },
      },
      borderRadius: {
        'card': '8px',
      },
    },
  },
  plugins: [],
}

