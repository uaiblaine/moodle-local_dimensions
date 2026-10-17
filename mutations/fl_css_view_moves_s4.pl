# Keep the view toggle on the two-line step's first line beside its "Show as" twin.
s/(\.local-dimensions-line--s4 > \.local-dimensions-line-view \{\n    display: )none;/${1}flex;/;
