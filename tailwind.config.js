/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/**/*.php",
    "./assets/js/**/*.js",
    "./assets/components/**/*.css",
    "./assets/tailwind/**/*.css",
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ["Sora", "ui-sans-serif", "system-ui", "sans-serif"],
        body: ["Plus Jakarta Sans", "ui-sans-serif", "system-ui", "sans-serif"],
      },
      colors: {
        brand: {
          50: "#edf6ff",
          100: "#dbeeff",
          200: "#bedfff",
          300: "#91c7ff",
          400: "#58a3ff",
          500: "#2f7fff",
          600: "#1f5ff2",
          700: "#1848df",
          800: "#1a3cb4",
          900: "#1a378e",
        },
      },
      boxShadow: {
        soft: "0 20px 40px rgba(11, 41, 86, 0.10)",
        card: "0 8px 24px rgba(15, 23, 42, 0.08)",
      },
      keyframes: {
        "fade-in-up": {
          "0%": { opacity: "0", transform: "translateY(14px)" },
          "100%": { opacity: "1", transform: "translateY(0)" },
        },
        "pulse-soft": {
          "0%, 100%": { transform: "scale(1)" },
          "50%": { transform: "scale(1.015)" },
        },
        float: {
          "0%,100%": { transform: "translateY(0px)" },
          "50%": { transform: "translateY(-4px)" },
        },
      },
      animation: {
        "fade-in-up": "fade-in-up .45s ease-out both",
        "pulse-soft": "pulse-soft 2.4s ease-in-out infinite",
        float: "float 3.2s ease-in-out infinite",
      },
    },
  },
  plugins: [],
}
