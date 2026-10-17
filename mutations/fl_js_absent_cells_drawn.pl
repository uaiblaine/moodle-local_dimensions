# Measure without the absent cells: the step follows the counts of the moment, not the width.
s/(absent\.forEach\(\(cell\) => \{\n        cell\.hidden = )false;/${1}true;/;
