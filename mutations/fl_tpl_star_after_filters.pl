# Draw the star after Filters: the contract's slot order broken.
s/(        \{\{#hasstar\}\}.*?\{\{\/hasstar\}\}\n)(        \{\{#haschipfilters\}\}.*?\{\{\/haschipfilters\}\}\n)/$2$1/s;
