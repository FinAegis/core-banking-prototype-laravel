/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/views/app.blade.php'],
  theme: {
    extend: {
      fontFamily: {
        heading: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
        body: ['"DM Sans"', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      colors: {
        obsidian: '#0a0a0a',
        acid: '#ccff00',
        mint: '#a8f0c4',
        'mint-mid': '#c0f5d6',
        lavender: '#c8a8f0',
        'z-blue': '#a8c8f0',
        'z-purple': '#7000ff',
        'z-green': '#10b981',
        'z-gold': '#f59e0b',
        'z-pink': '#f9a8d4',
        'z-red': '#ef4444',
        'text-sec': '#444444',
        'text-muted': '#888888',
        'bg-tertiary': '#f0f0f0',
        'border-subtle': '#d1d5db',
      },
    },
  },
  plugins: [],
}
