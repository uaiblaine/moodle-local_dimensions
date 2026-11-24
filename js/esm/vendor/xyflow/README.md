# React Flow, vendored

`xyflow.js` is [React Flow](https://github.com/xyflow/xyflow) (`@xyflow/react`) as one minified ES
module, with its dependencies inlined and React left external. The Competency hub v2's canvas
(`js/esm/src/hubv2/`) imports it, and the plugin's bundle step (`js/esm/bundle.json`, built by
`mdl grunt`) inlines it into `js/esm/build/bundle.js`, which is the only file the page loads.

It lives outside `js/esm/src` on purpose: core's `grunt esm` compiles every `.ts` under `src`,
and its per-file build would treat the library as a plugin source.

| Package | Version | Licence |
|---|---|---|
| @xyflow/react | 13.0.3 | MIT, (c) 2019-2025 webkid GmbH |
| @xyflow/system | 1.0.1 | MIT, (c) 2019-2025 webkid GmbH |
| zustand | 5.1.0 | MIT |
| react-compiler-runtime | 1.0.0 | MIT, (c) Meta |
| classcat | 5.0.5 | MIT |
| d3-zoom, d3-drag, d3-selection, d3-transition, d3-interpolate, d3-color, d3-dispatch, d3-ease, d3-timer | 3.x | ISC, (c) Mike Bostock |

`react`, `react/jsx-runtime` and `react-dom` are external: the module imports them by name, and
Moodle's import map serves core's React (19.2.4 on 5.3; React Flow 13 needs 18 or later).
`LICENSE` beside this file is React Flow's.

The module exports exactly: `ReactFlow`, `ReactFlowProvider`, `Background`, `BackgroundVariant`,
`Controls`, `ControlButton`, `MiniMap`, `Panel`, `Handle`, `Position`, `MarkerType`, `BaseEdge`,
`EdgeLabelRenderer`, `getSmoothStepPath`, `getBezierPath`, `useReactFlow`, `useNodesState`,
`useEdgesState`, `useNodesInitialized`.

`xyflow.d.ts` is the plugin's own, hand-written: narrow declarations of what the canvas uses,
copied from the release's `dist/index.d.ts` (of `@xyflow/react` and `@xyflow/system`). Declare a
name there before the canvas uses it.

React Flow's stylesheet is not vendored: the rules the canvas needs are ported into the plugin's
`styles.css`, scoped under `.local-dimensions-hubv2` and painted with the plugin's colour tokens.
Its `color-scheme` and `light-dark()` rules, through which 13 follows the system's colour scheme,
are left out on purpose: the canvas follows Moodle's `data-bs-theme` through the tokens. The
library itself switches nothing from JavaScript: it adds a `dark` or `light` class only when
`forceColorMode` is set, and the canvas does not set it.

## How it was built

esbuild 0.25.12 (`bundle: true`, `format: 'esm'`, `minify: true`, `legalComments: 'none'`,
`define: {'process.env.NODE_ENV': '"production"'}`, `external: ['react', 'react/*', 'react-dom',
'react-dom/*']`) over an entry that re-exports the names above, with a banner naming the packages.

Two plugins are part of the build. The first: React Flow 13 is compiled with the React Compiler and imports
`react-compiler-runtime`, which is CommonJS and calls `require('react')` (it then uses React 19's
own `__COMPILER_RUNTIME` when React has one). With React external, esbuild turns that call into a
runtime `require()`, which on a Moodle page is RequireJS's global and throws (react is no AMD
module). The plugin answers every `require('react')` with a small ES module re-exporting the
external import, so the output carries static `import ... from "react"` statements only (check:
the file holds no `typeof require` and no `require(` call). zustand 5 imports React as an ES
module and needs no bridge; `react/compiler-runtime` is not an alias for the runtime, because
core's import map serves no such file.

The second rewords one error message. `react-compiler-runtime` throws an error whose text starts
with the marker that development-leftover checkers refuse anywhere in a shipped file, and the
module is inlined into the plugin's bundle, which no checker treats as third-party code. The plugin
replaces that prefix with "Not implemented:" as the package's file is loaded; the code is unchanged
(check: the output holds no such marker). This is the one customisation `thirdpartylibs.xml`
declares besides the bundling itself.

## Rebuilding

```sh
mkdir xyflow-build && cd xyflow-build
npm init -y && npm install @xyflow/react@13.0.3 esbuild@0.25.12
# entry.mjs: export {ReactFlow, ReactFlowProvider, ... the names above} from '@xyflow/react';
# build.mjs:
#   import * as esbuild from 'esbuild';
#   const reactRequireBridge = {name: 'react-require-bridge', setup(build) {
#       build.onResolve({filter: /^react$/}, (args) =>
#           (args.kind === 'require-call' ? {path: 'react-require-bridge', namespace: 'bridge'} : undefined));
#       build.onLoad({filter: /.*/, namespace: 'bridge'}, () => ({
#           contents: 'import * as React from "react"; export default React; export * from "react";',
#           loader: 'js', resolveDir: process.cwd()}));
#   }};
#   const leftoverMarker = {name: 'leftover-marker', setup(build) {
#       build.onLoad({filter: /react-compiler-runtime[\\/]dist[\\/]index\.js$/}, async(args) => {
#           const fs = await import('node:fs/promises');
#           const source = await fs.readFile(args.path, 'utf8');
#           return {contents: source.replace(/TO[D]O: implement/g, 'Not implemented:'), loader: 'js'};
#       });
#   }};
#   await esbuild.build({entryPoints: ['entry.mjs'], outfile: 'out/xyflow.js', bundle: true,
#       format: 'esm', external: ['react', 'react/*', 'react-dom', 'react-dom/*'],
#       plugins: [reactRequireBridge, leftoverMarker], minify: true, legalComments: 'none',
#       define: {'process.env.NODE_ENV': '"production"'},
#       banner: {js: '/*! @xyflow/react 13.0.3 (MIT, (c) 2019-2025 webkid GmbH), bundled with ... */'}});
node build.mjs
rg -c 'typeof require|[^a-zA-Z_$.]require\(' out/xyflow.js   # must print nothing
rg -c 'TO[D]O' out/xyflow.js                                # must print nothing
cp out/xyflow.js <plugin>/js/esm/vendor/xyflow/xyflow.js
```

Then rebuild the plugin's bundle (`mdl grunt m503 local/dimensions`), update the versions here
(`npm ls --all` gives the installed tree) and in `thirdpartylibs.xml` (with the new SHA-384:
`openssl dgst -sha384 -binary xyflow.js | openssl base64 -A`), and keep the banner:
`tests/bundle_test.php` reads the release from it.
