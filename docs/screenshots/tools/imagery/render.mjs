// Render the showcase imagery: one SVG composition per plan template, competency and new course,
// rasterised to JPEG by headless Chrome over the DevTools protocol.
//
// Usage (from any directory, Node 22 or later, Google Chrome at its macOS path or CHROME_BIN):
//   node docs/screenshots/tools/imagery/render.mjs [--force] [--svg] [--only=PREFIX]
//
// Reads ../showcase-data.json (the same file seed_showcase.php reads) and writes imagery/out/:
//   tpl-<idnumber>-card.jpg   1200x630, the template's card image (block_dimensions plan cards)
//   tpl-<idnumber>-bg.jpg     1920x560, the template's background image (the plan hero)
//   comp-<idnumber>-card.jpg  1200x630, the competency's card image
//   comp-<idnumber>-bg.jpg    1920x560, the competency's background image (the tracker hero)
//   course-<idnumber>.jpg     1200x630, the cover of a course the seed creates
// Existing files are kept unless --force. --svg also writes each composition as .svg next to it.
//
// Every picture is procedural: a two-stop gradient from the category palette, one abstract motif
// per category, a soft halo and a Font Awesome solid glyph that names the topic. The glyph font is
// the one Moodle ships (lib/bundles/fontawesome); MOODLE_DIR points at a Moodle 5.x checkout
// (default ~/dev/moodle-503). Nothing is fetched from the network.

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const force = args.includes('--force');
const dumpsvg = args.includes('--svg');
const only = (args.find((a) => a.startsWith('--only=')) || '').slice(7);

const data = JSON.parse(readFileSync(join(here, '..', 'showcase-data.json'), 'utf8'));
const moodledir = process.env.MOODLE_DIR || join(homedir(), 'dev', 'moodle-503');
const fadir = join(moodledir, 'lib', 'bundles', 'fontawesome');
const fontfile = join(fadir, 'webfonts', 'fa-solid-900.woff2');
if (!existsSync(fontfile)) {
  console.error(`Font Awesome not found at ${fontfile}; set MOODLE_DIR to a Moodle 5.x checkout.`);
  process.exit(1);
}
const variables = readFileSync(join(fadir, 'scss', '_variables.scss'), 'utf8');
const glyph = (name) => {
  const m = variables.match(new RegExp(`\\$fa-var-${name}: \\\\([0-9a-f]+)`));
  if (!m) {
    throw new Error(`Unknown Font Awesome icon: ${name}`);
  }
  return `&#x${m[1]};`;
};

const outdir = join(here, 'out');
mkdirSync(outdir, { recursive: true });

// Deterministic randomness: the same idnumber always gives the same picture.
const hash = (s) => {
  let h = 2166136261;
  for (const c of s) {
    h = Math.imul(h ^ c.charCodeAt(0), 16777619);
  }
  return h >>> 0;
};
const rng = (seed) => {
  let a = seed;
  return () => {
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
};

// Colour helpers: shift a palette colour's lightness so siblings differ but stay in the family.
const hex2rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const rgb2hex = (c) => '#' + c.map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('');
const mix = (a, b, t) => rgb2hex(hex2rgb(a).map((v, i) => v + (hex2rgb(b)[i] - v) * t));

const motifs = {
  waves(r, w, h, col, op) {
    let s = '';
    for (let i = 0; i < 9; i++) {
      const y = h * (0.15 + i * 0.1) + r() * 20;
      const a = 18 + r() * 30;
      const k = 0.004 + r() * 0.004;
      let d = `M0 ${y.toFixed(1)}`;
      for (let x = 0; x <= w; x += 24) {
        d += ` L${x} ${(y + Math.sin(x * k + i) * a).toFixed(1)}`;
      }
      s += `<path d="${d}" fill="none" stroke="${col}" stroke-width="${2 + (i % 3)}" opacity="${op}"/>`;
    }
    return s;
  },
  grid(r, w, h, col, op) {
    const step = 60;
    let s = '';
    for (let x = 0; x <= w; x += step) {
      s += `<line x1="${x}" y1="0" x2="${x}" y2="${h}" stroke="${col}" stroke-width="1" opacity="${op * 0.7}"/>`;
    }
    for (let y = 0; y <= h; y += step) {
      s += `<line x1="0" y1="${y}" x2="${w}" y2="${y}" stroke="${col}" stroke-width="1" opacity="${op * 0.7}"/>`;
    }
    for (let i = 0; i < 14; i++) {
      const x = Math.floor(r() * (w / step)) * step;
      const y = Math.floor(r() * (h / step)) * step;
      s += `<rect x="${x + 6}" y="${y + 6}" width="${step - 12}" height="${step - 12}" rx="6" fill="${col}" opacity="${op * (0.6 + r())}"/>`;
    }
    return s;
  },
  circuit(r, w, h, col, op) {
    let s = '';
    for (let i = 0; i < 16; i++) {
      let x = r() * w;
      let y = r() * h;
      let d = `M${x.toFixed(0)} ${y.toFixed(0)}`;
      for (let j = 0; j < 4; j++) {
        if (j % 2 === 0) {
          x += (r() - 0.5) * 360;
        } else {
          y += (r() - 0.5) * 220;
        }
        d += ` L${x.toFixed(0)} ${y.toFixed(0)}`;
      }
      s += `<path d="${d}" fill="none" stroke="${col}" stroke-width="3" stroke-linejoin="round" opacity="${op}"/>`;
      s += `<circle cx="${x.toFixed(0)}" cy="${y.toFixed(0)}" r="8" fill="none" stroke="${col}" stroke-width="3" opacity="${op}"/>`;
    }
    return s;
  },
  rings(r, w, h, col, op) {
    const cx = w * (0.15 + r() * 0.2);
    const cy = h * (0.3 + r() * 0.5);
    let s = '';
    for (let i = 1; i < 16; i++) {
      s += `<circle cx="${cx.toFixed(0)}" cy="${cy.toFixed(0)}" r="${i * 70}" fill="none" stroke="${col}" stroke-width="${i % 3 === 0 ? 4 : 1.5}" opacity="${op}"/>`;
    }
    return s;
  },
  chevrons(r, w, h, col, op) {
    let s = '';
    const step = 90;
    for (let y = -step; y < h + step; y += step) {
      const off = (y / step) % 2 === 0 ? 0 : step / 2;
      let d = '';
      for (let x = -step + off; x < w + step; x += step) {
        d += `M${x} ${y + 30} L${x + step / 2} ${y} L${x + step} ${y + 30} `;
      }
      s += `<path d="${d}" fill="none" stroke="${col}" stroke-width="5" opacity="${op * (0.5 + r() * 0.8)}"/>`;
    }
    return s;
  },
  arcs(r, w, h, col, op) {
    let s = '';
    const corners = [[0, h], [w, 0]];
    for (const [cx, cy] of corners) {
      for (let i = 1; i < 12; i++) {
        s += `<circle cx="${cx}" cy="${cy}" r="${i * 85}" fill="none" stroke="${col}" stroke-width="${i % 2 ? 10 : 3}" opacity="${op * (0.5 + r() * 0.6)}"/>`;
      }
    }
    return s;
  },
  petals(r, w, h, col, op) {
    let s = '';
    for (let k = 0; k < 3; k++) {
      const cx = w * (0.1 + r() * 0.8);
      const cy = h * (0.1 + r() * 0.8);
      const len = 90 + r() * 120;
      for (let i = 0; i < 8; i++) {
        s += `<ellipse cx="${cx.toFixed(0)}" cy="${(cy - len / 2).toFixed(0)}" rx="${(len / 4).toFixed(0)}" ry="${(len / 2).toFixed(0)}" fill="${col}" opacity="${op * 0.55}" transform="rotate(${i * 45} ${cx.toFixed(0)} ${cy.toFixed(0)})"/>`;
      }
    }
    return s;
  },
  hex(r, w, h, col, op) {
    const R = 48;
    const dx = R * Math.sqrt(3);
    let s = '';
    for (let row = -1, y = 0; y < h + R * 2; row++, y = row * R * 1.5) {
      for (let x = (row % 2 ? dx / 2 : 0) - dx; x < w + dx; x += dx) {
        const pts = [...Array(6)].map((_, i) => {
          const a = Math.PI / 6 + (i * Math.PI) / 3;
          return `${(x + R * Math.cos(a)).toFixed(1)},${(y + R * Math.sin(a)).toFixed(1)}`;
        }).join(' ');
        const fill = r() < 0.12 ? col : 'none';
        s += `<polygon points="${pts}" fill="${fill}" stroke="${col}" stroke-width="2" opacity="${op * (fill === 'none' ? 0.8 : 0.9)}"/>`;
      }
    }
    return s;
  },
  sparks(r, w, h, col, op) {
    let s = '';
    for (let i = 0; i < 40; i++) {
      const x = r() * w;
      const y = r() * h;
      const k = 6 + r() * 26;
      if (i % 3 === 0) {
        s += `<path d="M${x} ${y - k} Q${x} ${y} ${x + k} ${y} Q${x} ${y} ${x} ${y + k} Q${x} ${y} ${x - k} ${y} Q${x} ${y} ${x} ${y - k}Z" fill="${col}" opacity="${op * 1.4}"/>`;
      } else {
        s += `<circle cx="${x.toFixed(0)}" cy="${y.toFixed(0)}" r="${(k / 5).toFixed(1)}" fill="${col}" opacity="${op * 1.2}"/>`;
      }
    }
    return s;
  },
  pulse(r, w, h, col, op) {
    let s = '';
    for (let i = 0; i < 4; i++) {
      const y = h * (0.2 + i * 0.22);
      let d = `M0 ${y}`;
      let x = 0;
      while (x < w) {
        x += 60 + r() * 80;
        d += ` L${x.toFixed(0)} ${y}`;
        const a = 40 + r() * 70;
        d += ` L${(x + 14).toFixed(0)} ${(y - a).toFixed(0)} L${(x + 28).toFixed(0)} ${(y + a * 0.6).toFixed(0)} L${(x + 40).toFixed(0)} ${y}`;
        x += 40;
      }
      s += `<path d="${d}" fill="none" stroke="${col}" stroke-width="3" stroke-linejoin="round" opacity="${op}"/>`;
    }
    return s;
  },
  dots(r, w, h, col, op) {
    let s = '';
    const step = 34;
    const cx = w * (0.2 + r() * 0.6);
    const cy = h * (0.2 + r() * 0.6);
    for (let x = step / 2; x < w; x += step) {
      for (let y = step / 2; y < h; y += step) {
        const d = Math.hypot(x - cx, y - cy) / Math.hypot(w, h);
        const rad = Math.max(0.6, 7 * (1 - d * 1.6));
        s += `<circle cx="${x}" cy="${y}" r="${rad.toFixed(1)}" fill="${col}" opacity="${op}"/>`;
      }
    }
    return s;
  },
  topo(r, w, h, col, op) {
    let s = '';
    for (let k = 0; k < 2; k++) {
      const cx = w * (0.2 + r() * 0.6);
      const cy = h * (0.2 + r() * 0.6);
      const ph = r() * 6;
      for (let i = 1; i < 14; i++) {
        let d = '';
        for (let a = 0; a <= 64; a++) {
          const t = (a / 64) * Math.PI * 2;
          const rad = i * 38 * (1 + 0.18 * Math.sin(3 * t + ph + i * 0.2) + 0.08 * Math.cos(5 * t));
          d += `${a ? 'L' : 'M'}${(cx + rad * Math.cos(t)).toFixed(1)} ${(cy + rad * 0.8 * Math.sin(t)).toFixed(1)}`;
        }
        s += `<path d="${d}Z" fill="none" stroke="${col}" stroke-width="${i % 4 === 0 ? 3 : 1.4}" opacity="${op}"/>`;
      }
    }
    return s;
  },
};

/**
 * One composition.
 *
 * @param {object} o key, palette, icon, light, shape ('card' | 'bg' | 'cover'), shade (0..1)
 * @returns {{w: number, h: number, svg: string}}
 */
const compose = (o) => {
  const p = data.palettes[o.palette];
  const r = rng(hash(o.key));
  const [w, h] = o.shape === 'bg' ? [1920, 560] : [1200, 630];
  const shade = o.shade ?? 0;
  let from;
  let to;
  let ink;
  let motifcol;
  let motifop;
  if (o.light) {
    from = mix(p.bglight, '#ffffff', 0.35);
    to = mix(p.bglight, p.accent, 0.55 + shade * 0.3);
    ink = p.from;
    motifcol = p.to;
    motifop = 0.12;
  } else {
    from = mix(p.from, '#000000', shade * 0.25);
    to = mix(p.to, p.from, shade * 0.35);
    ink = '#ffffff';
    motifcol = p.accent;
    motifop = 0.2;
  }
  const angle = 20 + r() * 50;
  const gx = (0.55 + r() * 0.35).toFixed(2);
  const gy = (0.2 + r() * 0.5).toFixed(2);
  const motif = motifs[p.motif](r, w, h, motifcol, motifop);
  const g = glyph(o.icon);
  let fg;
  if (o.shape === 'bg') {
    // The hero paints its colour over the top-left; the glyph sits on the right, in the clear.
    fg = `<text x="${w * 0.9}" y="${h * 0.55}" font-size="760" fill="${ink}" opacity="0.07">${g}</text>`
      + `<circle cx="${w * 0.8}" cy="${h * 0.5}" r="170" fill="${ink}" opacity="0.12"/>`
      + `<circle cx="${w * 0.8}" cy="${h * 0.5}" r="215" fill="none" stroke="${ink}" stroke-width="2" opacity="0.25"/>`
      + `<text x="${w * 0.8}" y="${h * 0.5}" font-size="170" fill="${ink}" filter="url(#sh)">${g}</text>`;
  } else {
    // Cards crop to a short band (about 120px high), so the glyph stays centred and compact.
    fg = `<text x="${w * 0.84}" y="${h * 0.62}" font-size="560" fill="${ink}" opacity="0.07">${g}</text>`
      + `<circle cx="${w / 2}" cy="${h / 2}" r="150" fill="${ink}" opacity="0.13"/>`
      + `<circle cx="${w / 2}" cy="${h / 2}" r="190" fill="none" stroke="${ink}" stroke-width="2" opacity="0.28"/>`
      + `<text x="${w / 2}" y="${h / 2}" font-size="150" fill="${ink}" filter="url(#sh)">${g}</text>`;
  }
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
<defs>
<linearGradient id="bg" gradientTransform="rotate(${angle.toFixed(0)} .5 .5)"><stop offset="0" stop-color="${from}"/><stop offset="1" stop-color="${to}"/></linearGradient>
<radialGradient id="glow" cx="${gx}" cy="${gy}" r=".7"><stop offset="0" stop-color="#ffffff" stop-opacity="${o.light ? 0.55 : 0.22}"/><stop offset="1" stop-color="#ffffff" stop-opacity="0"/></radialGradient>
<filter id="sh" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#000000" flood-opacity="${o.light ? 0.12 : 0.3}"/></filter>
<style>text{font-family:FASolid;font-weight:900;text-anchor:middle;dominant-baseline:central}</style>
</defs>
<rect width="${w}" height="${h}" fill="url(#bg)"/>
<g>${motif}</g>
<rect width="${w}" height="${h}" fill="url(#glow)"/>
${fg}
</svg>`;
  return { w, h, svg };
};

// The job list, in the seed's own terms.
const jobs = [];
const add = (file, o) => {
  if (!only || file.startsWith(only)) {
    jobs.push({ file, ...compose(o) });
  }
};
for (const t of data.templates) {
  add(`tpl-${t.idnumber}-card.jpg`, { key: t.idnumber + 'card', palette: t.palette, icon: t.icon, light: t.light, shape: 'card' });
  add(`tpl-${t.idnumber}-bg.jpg`, { key: t.idnumber + 'bg', palette: t.palette, icon: t.icon, light: t.light, shape: 'bg' });
}
for (const f of data.frameworks) {
  f.domains.forEach((d, di) => {
    const light = di % 2 === 1;
    add(`comp-${d.idnumber}-card.jpg`, { key: d.idnumber + 'card', palette: f.palette, icon: d.icon, light: false, shape: 'card', shade: 0.6 });
    add(`comp-${d.idnumber}-bg.jpg`, { key: d.idnumber + 'bg', palette: f.palette, icon: d.icon, light: false, shape: 'bg', shade: 0.6 });
    d.children.forEach(([id, , icon], ci) => {
      const shade = (ci % 3) / 3;
      add(`comp-${id}-card.jpg`, { key: id + 'card', palette: f.palette, icon, light, shape: 'card', shade });
      add(`comp-${id}-bg.jpg`, { key: id + 'bg', palette: f.palette, icon, light, shape: 'bg', shade });
    });
  });
}
for (const c of data.courses.new) {
  add(`course-${c.idnumber}.jpg`, { key: c.idnumber, palette: c.palette, icon: c.icon, light: false, shape: 'cover', shade: 0.2 });
}

const todo = jobs.filter((j) => force || !existsSync(join(outdir, j.file)));
console.log(`${jobs.length} compositions, ${todo.length} to render into ${outdir}`);
if (dumpsvg) {
  for (const j of jobs) {
    writeFileSync(join(outdir, j.file.replace(/\.jpg$/, '.svg')), j.svg);
  }
}
if (!todo.length) {
  process.exit(0);
}

// The canvas page loads the glyph font from the Moodle checkout once; each job swaps the SVG in.
const canvas = join(outdir, '_canvas.html');
writeFileSync(canvas, `<!doctype html><meta charset="utf-8"><style>
@font-face{font-family:FASolid;font-weight:900;src:url('${pathToFileURL(fontfile).href}') format('woff2')}
html,body{margin:0;padding:0;background:#000}svg{display:block}</style><body><span style="font-family:FASolid;font-weight:900">&#xf19d;</span></body>`);

const port = 9300 + (process.pid % 500);
const chromebin = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const chrome = spawn(chromebin, [
  '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), 'scimg-'))}`,
  '--no-first-run', '--hide-scrollbars', '--allow-file-access-from-files', '--window-size=1920,1080',
], { stdio: 'ignore' });
const sleep = (ms) => new Promise((res) => setTimeout(res, ms));
let wsurl;
for (let i = 0; i < 50 && !wsurl; i++) {
  try {
    const res = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
    wsurl = (await res.json()).webSocketDebuggerUrl;
  } catch {
    await sleep(200);
  }
}
const sock = new WebSocket(wsurl);
await new Promise((res) => sock.addEventListener('open', res));
let seq = 0;
const pending = new Map();
sock.addEventListener('message', (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) {
    pending.get(m.id)(m);
    pending.delete(m.id);
  }
});
const send = (method, params = {}) => new Promise((res, rej) => {
  const i = ++seq;
  pending.set(i, (m) => (m.error ? rej(new Error(method + ': ' + JSON.stringify(m.error))) : res(m.result)));
  sock.send(JSON.stringify({ id: i, method, params }));
});
const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result.value;

await send('Page.enable');
await send('Page.navigate', { url: pathToFileURL(canvas).href });
await sleep(800);
const fontok = await evaluate(`document.fonts.load('900 40px FASolid').then(f => f.length > 0)`);
if (!fontok) {
  console.error('The glyph font did not load.');
  chrome.kill();
  process.exit(1);
}
let n = 0;
for (const j of todo) {
  await send('Emulation.setDeviceMetricsOverride', { width: j.w, height: j.h, deviceScaleFactor: 1, mobile: false });
  await evaluate(`(async () => { document.body.innerHTML = ${JSON.stringify(j.svg)}; await document.fonts.ready;
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))); return 1; })()`);
  const shot = await send('Page.captureScreenshot', { format: 'jpeg', quality: 84, clip: { x: 0, y: 0, width: j.w, height: j.h, scale: 1 } });
  writeFileSync(join(outdir, j.file), Buffer.from(shot.data, 'base64'));
  if (++n % 50 === 0) {
    console.log(`  ${n}/${todo.length}`);
  }
}
console.log(`Rendered ${n} images.`);
sock.close();
chrome.kill();
process.exit(0);
