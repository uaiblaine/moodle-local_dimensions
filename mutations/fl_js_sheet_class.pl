# Never write the sheet's class: the toolbar's backdrop filter keeps the fixed sheet inside the toolbar.
s/toolbar\.classList\.toggle\(CLASSES\.sheet, state\.step === TWO_LINES && panelOpen\(\)\);/toolbar.classList.toggle(CLASSES.sheet, false);/;
