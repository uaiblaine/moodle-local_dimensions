# Take a step only where it fits: a line too narrow for every step is left at the last one tried by accident.
s/return index === TRIES\.length - 1 \|\| !overflows\(\);/return !overflows();/;
