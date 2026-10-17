# Leave the two-line step's panel a popover: a narrow screen gets a panel cut by the screen's edge.
s/(\.local-dimensions-line--s4 \.local-dimensions-filter-panel \{\n    position: )fixed;/${1}absolute;/;
