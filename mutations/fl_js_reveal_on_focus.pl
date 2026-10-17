# Reveal only on a press: a cell reached by the keyboard stays half hidden on the two-line step.
s/^        scroller\.addEventListener\('focusin', \(event\) => reveal\(scroller, itemOf\(event\.target\)\)\);\n//m;
