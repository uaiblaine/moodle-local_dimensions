# Leave every cell in the tab order: the radio group costs a Tab per cell.
s/cell\.tabIndex = cell === chosen \? 0 : -1;/cell.tabIndex = 0;/;
