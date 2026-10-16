# Draw a zero cell nobody chose: "To do 0" on offer when every course is done.
s/cell\.hidden = count === 0 && cell\.getAttribute\('aria-checked'\) !== 'true';/cell.hidden = false;/;
