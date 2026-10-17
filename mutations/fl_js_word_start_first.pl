# Rank by count alone: a filter whose word starts with the text sorts under one that only contains it.
s/\.sort\(\(a, b\) => a\.rank - b\.rank \|\| count\(b\.item\)/.sort((a, b) => count(b.item)/;
