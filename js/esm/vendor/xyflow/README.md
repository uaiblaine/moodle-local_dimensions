# React Flow, vendored

`xyflow.js` is [React Flow](https://github.com/xyflow/xyflow) (`@xyflow/react`) as one minified ES
module, with its dependencies inlined and React left external. The Competency hub v2's canvas
(`js/esm/src/hubv2/`) imports it, and the plugin's bundle step (`js/esm/bundle.json`, built by
`mdl grunt`) inlines it into `js/esm/build/bundle.js`, which is the only file the page loads.

It lives outside `js/esm/src` on purpose: core's `grunt esm` compiles every `.ts` under `src`,
and its per-file build would treat the library as a plugin source.

| Package | Version | Licence |
|---|---|---|
| @xyflow/react | 12.12.1 | MIT, (c) 2019-2025 webkid GmbH |
| @xyflow/system | 0.0.84 | MIT, (c) 2019-2025 webkid GmbH |
| zustand | 4.5.7 | MIT |
| use-sync-external-store | 1.7.0 | MIT, (c) Meta |
| classcat | 5.0.5 | MIT |
| d3-zoom, d3-drag, d3-selection, d3-transition, d3-interpolate, d3-color, d3-dispatch, d3-ease, d3-timer | 3.x | ISC, (c) Mike Bostock |

`react`, `react/jsx-runtime` and `react-dom` are external: the module imports them by name, and
Moodle's import map serves core's React. `LICENSE` beside this file is React Flow's.

The module exports exactly: `ReactFlow`, `ReactFlowProvider`, `Background`, `BackgroundVariant`,
`Controls`, `ControlButton`, `MiniMap`, `Panel`, `Handle`, `Position`, `MarkerType`, `BaseEdge`,
`EdgeLabelRenderer`, `getSmoothStepPath`, `getBezierPath`, `useReactFlow`, `useNodesState`,
`useEdgesState`, `useNodesInitialized`, `useStore`, `applyNodeChanges`, `applyEdgeChanges`.

`xyflow.d.ts` is the plugin's own, hand-written: narrow declarations of what the canvas uses,
copied from the release's `dist/esm/types` (of `@xyflow/react` and `@xyflow/system`). Declare a
name there before the canvas uses it.

React Flow's stylesheet is not vendored: the rules the canvas needs are ported into the plugin's
`styles.css`, scoped under `.local-dimensions-hubv2` and painted with the plugin's colour tokens.

## How it was built

esbuild 0.25.12 (`bundle: true`, `format: 'esm'`, `minify: true`, `legalComments: 'none'`,
`define: {'process.env.NODE_ENV': '"production"'}`, `external: ['react', 'react/*', 'react-dom',
'react-dom/*']`) over an entry that re-exports the names above, with a banner naming the packages.

One plugin is part of the build. zustand 4 reaches `use-sync-external-store`'s CommonJS shims,
which call `require('react')`; with React external, esbuild turns that call into a runtime
`require()`, which on a Moodle page is RequireJS's global and throws (react is no AMD module).
The plugin answers every `require('react')` with a small ES module re-exporting the external
import, so the output carries static `import ... from "react"` statements only (check: the file
holds no `typeof require`).

## Rebuilding

```sh
mkdir xyflow-build && cd xyflow-build
npm init -y && npm install @xyflow/react@12.12.1 esbuild@0.25.12
# entry.mjs: export {ReactFlow, ReactFlowProvider, ... the names above} from '@xyflow/react';
# build.mjs:
#   const reactRequireBridge = {name: 'react-require-bridge', setup(build) {
#       build.onResolve({filter: /^react$/}, (args) =>
#           args.kind === 'require-call' ? {path: 'react-require-bridge', namespace: 'bridge'} : undefined);
#       build.onLoad({filter: /.*/, namespace: 'bridge'}, () => ({
#           contents: 'import * as React from "react"; export default React; export * from "react";',
#           loader: 'js', resolveDir: process.cwd()}));
#   }};
#   await esbuild.build({entryPoints: ['entry.mjs'], outfile: 'out/xyflow.js', bundle: true,
#       format: 'esm', external: ['react', 'react/*', 'react-dom', 'react-dom/*'],
#       plugins: [reactRequireBridge], minify: true, legalComments: 'none',
#       define: {'process.env.NODE_ENV': '"production"'},
#       banner: {js: '/*! @xyflow/react 12.12.1 (MIT, (c) 2019-2025 webkid GmbH), bundled with ... */'}});
node build.mjs
cp out/xyflow.js <plugin>/js/esm/vendor/xyflow/xyflow.js
```

Then rebuild the plugin's bundle (`mdl grunt m503 local/dimensions`), update the versions here and
in `thirdpartylibs.xml` (with the new SHA-384), and keep the banner: `tests/bundle_test.php` reads
the release from it.
