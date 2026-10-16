# Stop placing the panel as it opens: it opens at its stylesheet offset over a wrapped line.
s/watch\(parts\.panel, \(\) => place\(parts\.panel\)\);/watch(parts.panel, () => null);/;
