#!/usr/bin/env node
/**
 * Fitness OS — design token build.
 * Zero dependencies. Reads tokens.json (source of truth) and emits:
 *   dist/tokens.css          CSS custom properties (dark default + .theme-light)
 *   dist/tailwind-preset.cjs Tailwind preset consuming the CSS vars
 *   dist/app_tokens.dart     Flutter color/space/radius constants
 * Run:  node build.mjs
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const t = JSON.parse(readFileSync(resolve(here, 'tokens.json'), 'utf8'));
mkdirSync(resolve(here, 'dist'), { recursive: true });

const kebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();

/* ---------- tokens.css ---------- */
const cssVars = (obj, prefix) =>
  Object.entries(obj).map(([k, v]) => `  --${prefix}-${k}: ${v};`).join('\n');

const css = `:root, .theme-dark {
${cssVars(t.color.dark, 'color')}
${Object.entries(t.color.data).map(([k, v]) => `  --color-data-${k}: ${v};`).join('\n')}
${Object.entries(t.space).map(([k, v]) => `  --space-${k}: ${v}px;`).join('\n')}
${Object.entries(t.radius).map(([k, v]) => `  --radius-${k}: ${typeof v === 'number' ? v + 'px' : v};`).join('\n')}
  --font-latin: ${t.font['family-latin']};
  --font-arabic: ${t.font['family-arabic']};
}
.theme-light {
${cssVars(t.color.light, 'color')}
}
`;
writeFileSync(resolve(here, 'dist/tokens.css'), css);

/* ---------- tailwind-preset.cjs ---------- */
const twColors = Object.keys(t.color.dark)
  .map((k) => `        '${k}': 'var(--color-${k})',`).join('\n');
const twSpace = Object.keys(t.space).map((k) => `        '${k}': 'var(--space-${k})',`).join('\n');
const twRadius = Object.keys(t.radius).map((k) => `        '${k}': 'var(--radius-${k})',`).join('\n');
const preset = `/* AUTO-GENERATED from tokens.json — do not edit by hand. */
module.exports = {
  theme: {
    extend: {
      colors: {
${twColors}
      },
      spacing: {
${twSpace}
      },
      borderRadius: {
${twRadius}
      },
      fontFamily: {
        latin: 'var(--font-latin)'.split(','),
        arabic: 'var(--font-arabic)'.split(','),
      },
    },
  },
};
`;
writeFileSync(resolve(here, 'dist/tailwind-preset.cjs'), preset);

/* ---------- app_tokens.dart ---------- */
const dartColor = (hex) => `Color(0xFF${hex.replace('#', '')})`;
const dartDark = Object.entries(t.color.dark)
  .map(([k, v]) => `  static const ${camel(k)} = ${dartColor(v)};`).join('\n');
const dartLight = Object.entries(t.color.light)
  .map(([k, v]) => `  static const ${camel(k)} = ${dartColor(v)};`).join('\n');
const dartSpace = Object.entries(t.space)
  .map(([k, v]) => `  static const s${k} = ${v}.0;`).join('\n');
const dartRadius = Object.entries(t.radius)
  .map(([k, v]) => `  static const ${k} = ${typeof v === 'number' ? v : 9999}.0;`).join('\n');
function camel(s){return s.replace(/-([a-z0-9])/g,(_,c)=>c.toUpperCase());}

const dart = `// AUTO-GENERATED from tokens.json — do not edit by hand.
import 'package:flutter/material.dart';

class DarkColors {
${dartDark}
}

class LightColors {
${dartLight}
}

class Space {
${dartSpace}
}

class Radii {
${dartRadius}
}
`;
writeFileSync(resolve(here, 'dist/app_tokens.dart'), dart);

console.log('✓ design-tokens built → dist/{tokens.css, tailwind-preset.cjs, app_tokens.dart}');
