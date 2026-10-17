# Never repaint the line after a filter: its chips, its eraser and its suggestions go stale.
s/^            Line\.repaint\(\);\n//m;
