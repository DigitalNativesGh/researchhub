import forms from '@tailwindcss/forms';
import containerQueries from '@tailwindcss/container-queries';

/*
 | Theme copied verbatim from the ResearchHub design files (Academic Precision System).
 | The designs load Tailwind v3 from the Play CDN with this same inline config; compiling it
 | here produces the same utility classes without the CDN script.
 */
const designConfig = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-bright": "#f8f9fa",
                        "surface-container-high": "#e7e8e9",
                        "surface-tint": "#4f5e7e",
                        "on-surface-variant": "#44474d",
                        "primary-fixed-dim": "#b7c7eb",
                        "on-secondary-fixed": "#021d35",
                        "on-primary-container": "#8393b5",
                        "surface-variant": "#e1e3e4",
                        "secondary-container": "#c8dfff",
                        "secondary-fixed-dim": "#b2c8e8",
                        "secondary": "#4a607c",
                        "surface-dim": "#d9dadb",
                        "error": "#ba1a1a",
                        "primary-fixed": "#d7e2ff",
                        "inverse-surface": "#2e3132",
                        "on-primary-fixed": "#091b37",
                        "on-tertiary-fixed": "#002114",
                        "on-error-container": "#93000a",
                        "secondary-fixed": "#d2e4ff",
                        "error-container": "#ffdad6",
                        "tertiary-container": "#003220",
                        "surface-container-low": "#f3f4f5",
                        "outline": "#75777e",
                        "on-secondary-container": "#4c627e",
                        "surface": "#f8f9fa",
                        "primary": "#041632",
                        "on-surface": "#191c1d",
                        "on-background": "#191c1d",
                        "on-primary-fixed-variant": "#374765",
                        "primary-container": "#1b2b48",
                        "tertiary": "#001b0f",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-highest": "#e1e3e4",
                        "on-primary": "#ffffff",
                        "tertiary-fixed": "#b1f0ce",
                        "inverse-primary": "#b7c7eb",
                        "tertiary-fixed-dim": "#95d4b3",
                        "inverse-on-surface": "#f0f1f2",
                        "background": "#f8f9fa",
                        "on-tertiary-container": "#619e80",
                        "on-tertiary": "#ffffff",
                        "on-error": "#ffffff",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed-variant": "#0e5138",
                        "outline-variant": "#c5c6ce",
                        "surface-container": "#edeeef",
                        "on-secondary-fixed-variant": "#324863"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "stack-lg": "48px",
                        "base": "8px",
                        "stack-sm": "12px",
                        "stack-md": "24px",
                        "margin-mobile": "16px",
                        "margin-desktop": "40px",
                        "gutter": "24px",
                        "container-max": "1280px"
                    },
                    "fontFamily": {
                        "label-sm": ["Inter"],
                        "headline-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "headline-sm": ["Inter"],
                        "body-lg": ["Inter"],
                        "body-sm": ["Inter"],
                        "headline-lg-mobile": ["Inter"]
                    },
                    "fontSize": {
                        "label-sm": ["12px", {"lineHeight": "14px", "fontWeight": "500"}],
                        "headline-md": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "label-md": ["14px", {"lineHeight": "16px", "letterSpacing": "0.01em", "fontWeight": "600"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "700"}]
                    }
                }
            }
        };

/** @type {import('tailwindcss').Config} */
export default {
    ...designConfig,
    content: [
        './resources/views/**/*.blade.php',
        './public/js/**/*.js',
        './app/View/**/*.php',
        './app/Support/Html/**/*.php',
    ],
    plugins: [forms, containerQueries],
};
