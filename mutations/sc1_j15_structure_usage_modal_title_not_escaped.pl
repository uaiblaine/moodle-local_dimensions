s/\.then\(\(label\) => label \+ ' — ' \+ escapeHtml\(row\.dataset\.name \|\| ''\)\)/.then((label) => label + ' — ' + (row.dataset.name || ''))/;
