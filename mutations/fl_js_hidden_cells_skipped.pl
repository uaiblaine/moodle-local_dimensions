# Walk the hidden cells too: the arrow keys choose a zero cell nobody can see.
s/Array\.from\(parts\.scope\.querySelectorAll\(SELECTORS\.cell\)\)\.filter\(\(cell\) => !cell\.hidden\)/Array.from(parts.scope.querySelectorAll(SELECTORS.cell))/;
