/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Semantic tokens bound to CSS variables → supports light/dark switching
        bg:       'rgb(var(--bg) / <alpha-value>)',
        surface:  'rgb(var(--surface) / <alpha-value>)',
        surface2: 'rgb(var(--surface2) / <alpha-value>)',
        border:   'rgb(var(--border) / <alpha-value>)',
        text:     'rgb(var(--text) / <alpha-value>)',
        'text-muted': 'rgb(var(--text-muted) / <alpha-value>)',

        // Brand colors (constant across themes)
        violet:   '#7c3aed',
        'violet-light': '#a78bfa',
        'violet-dark':  '#6d28d9',
        cyan:     '#06b6d4',
        amber:    '#f59e0b',
        success:  '#10b981',
        danger:   '#ef4444',
        warning:  '#f59e0b',
        info:     '#06b6d4',
        muted:    '#6b7280',
      },
      fontFamily: {
        sans:  ['DM Sans', 'sans-serif'],
        title: ['Syne', 'sans-serif'],
        mono:  ['DM Mono', 'monospace'],
      },
      spacing: {
        // 4px grid for consistency
        '4.5': '1.125rem',
        '18':  '4.5rem',
        '22':  '5.5rem',
      },
      borderRadius: {
        'xs': '2px',
        'sm': '4px',
        'md': '6px',
        'lg': '8px',
        'xl': '12px',
        '2xl': '16px',
      },
      boxShadow: {
        // Refined soft shadows with slight violet tint for premium feel
        'xs':  '0 1px 2px 0 rgb(0 0 0 / 0.12)',
        'sm':  '0 1px 3px 0 rgb(0 0 0 / 0.18), 0 1px 2px -1px rgb(0 0 0 / 0.12)',
        'md':  '0 4px 12px -2px rgb(0 0 0 / 0.22), 0 2px 6px -2px rgb(0 0 0 / 0.14)',
        'lg':  '0 12px 28px -6px rgb(0 0 0 / 0.32), 0 6px 12px -4px rgb(0 0 0 / 0.18)',
        'xl':  '0 20px 40px -12px rgb(0 0 0 / 0.42), 0 8px 16px -6px rgb(0 0 0 / 0.22)',
        '2xl': '0 32px 64px -16px rgb(0 0 0 / 0.50)',
        'glow-violet': '0 0 0 1px rgb(124 58 237 / 0.35), 0 8px 24px -4px rgb(124 58 237 / 0.45)',
        'glow-cyan':   '0 0 0 1px rgb(6 182 212 / 0.35), 0 8px 24px -4px rgb(6 182 212 / 0.35)',
        'glow-danger': '0 0 0 1px rgb(239 68 68 / 0.35), 0 8px 24px -4px rgb(239 68 68 / 0.35)',
        'inner-sm':    'inset 0 1px 0 0 rgb(255 255 255 / 0.04)',
      },
      backgroundImage: {
        'gradient-violet':  'linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #c084fc 100%)',
        'gradient-violet-subtle': 'linear-gradient(135deg, rgba(124, 58, 237, 0.14) 0%, rgba(168, 85, 247, 0.08) 100%)',
        'gradient-cyan':    'linear-gradient(135deg, #06b6d4 0%, #22d3ee 100%)',
        'gradient-success': 'linear-gradient(135deg, #10b981 0%, #34d399 100%)',
        'gradient-danger':  'linear-gradient(135deg, #ef4444 0%, #f87171 100%)',
        'gradient-amber':   'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)',
        'gradient-surface': 'linear-gradient(180deg, rgb(var(--surface)) 0%, rgb(var(--bg)) 100%)',
      },
      animation: {
        'fade-in':       'fadeIn 200ms ease-out',
        'slide-up':      'slideUp 200ms ease-out',
        'slide-down':    'slideDown 200ms ease-out',
        'slide-in-right':'slideInRight 250ms cubic-bezier(0.22, 1, 0.36, 1)',
        'scale-in':      'scaleIn 180ms cubic-bezier(0.22, 1, 0.36, 1)',
        'pulse-slow':    'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn:       { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        slideUp:      { '0%': { transform: 'translateY(10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
        slideDown:    { '0%': { transform: 'translateY(-10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
        slideInRight: { '0%': { transform: 'translateX(100%)', opacity: '0' }, '100%': { transform: 'translateX(0)', opacity: '1' } },
        scaleIn:      { '0%': { transform: 'scale(0.96)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
      },
      minHeight: {
        'touch': '44px', // WCAG AA touch target
      },
      minWidth: {
        'touch': '44px',
      },
    },
  },
  plugins: [],
}
