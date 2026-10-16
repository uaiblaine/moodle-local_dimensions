# Let the measured line wrap: nothing ever overflows, so the line never folds.
s/(\.local-dimensions-line\.local-dimensions-line--measuring \{\n    flex-wrap: )nowrap;/${1}wrap;/;
