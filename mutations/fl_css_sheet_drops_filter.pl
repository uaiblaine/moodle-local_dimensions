# Keep the toolbar's backdrop filter under the sheet: the fixed sheet is drawn inside the sticky toolbar.
s/(\.local-dimensions-toolbar\.local-dimensions-toolbar--sheet \{\n    z-index: 1029;\n    backdrop-filter: )none;/${1}blur(8px);/;
