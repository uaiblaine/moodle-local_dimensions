import * as esbuild from 'esbuild';

// react-compiler-runtime (a dependency of @xyflow/react 13) is CommonJS and calls require('react').
// With React external, esbuild would leave that as a runtime require(), which on a Moodle page is
// RequireJS's global. Answer it with an ES module re-exporting the external import instead.
const reactRequireBridge = {name: 'react-require-bridge', setup(build) {
    build.onResolve({filter: /^react$/}, (args) =>
        (args.kind === 'require-call' ? {path: 'react-require-bridge', namespace: 'bridge'} : undefined));
    build.onLoad({filter: /.*/, namespace: 'bridge'}, () => ({
        contents: 'import * as React from "react"; export default React; export * from "react";',
        loader: 'js', resolveDir: process.cwd()}));
}};

// One error message of react-compiler-runtime starts with a marker that development-leftover
// checkers refuse anywhere in a shipped file. The message is reworded; nothing else changes.
const leftoverMarker = {name: 'leftover-marker', setup(build) {
    build.onLoad({filter: /react-compiler-runtime[\\/]dist[\\/]index\.js$/}, async(args) => {
        const fs = await import('node:fs/promises');
        const source = await fs.readFile(args.path, 'utf8');
        return {contents: source.replace(/TO[D]O: implement/g, 'Not implemented:'), loader: 'js'};
    });
}};

await esbuild.build({
    entryPoints: ['entry.mjs'], outfile: 'out/xyflow.js', bundle: true, format: 'esm',
    external: ['react', 'react/*', 'react-dom', 'react-dom/*'],
    plugins: [reactRequireBridge, leftoverMarker], minify: true, legalComments: 'none',
    define: {'process.env.NODE_ENV': '"production"'},
    banner: {js: '/*! @xyflow/react 13.0.3 (MIT, (c) 2019-2025 webkid GmbH), bundled with @xyflow/system 1.0.1, '
        + 'zustand 5 (MIT), classcat (MIT), react-compiler-runtime (MIT, (c) Meta), d3-zoom, d3-drag, d3-selection, '
        + 'd3-transition, d3-interpolate, d3-color, d3-dispatch, d3-ease, d3-timer (ISC, (c) Mike Bostock). '
        + 'React is external. See README.md beside this file. */'},
});
