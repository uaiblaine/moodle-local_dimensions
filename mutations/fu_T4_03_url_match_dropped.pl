s/foreach \(\$rows as \$row\) \{\n\s*if \(isset\(\$row->url\) && isset\(\$byurl\[\$row->url\]\)\) \{\n\s*\$matched\[\$byurl\[\$row->url\]\] = true;\n\s*\}\n\s*\}/foreach (\$byurl as \$id) {\n            \$matched[\$id] = true;\n        }/;
s/\$row->hasfiles = \$id !== null && isset\(\$withfiles\[\$id\]\);/\$row->hasfiles = (bool) \$withfiles;/;
