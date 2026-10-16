# Never show the tracker's "no results" state: an empty grid with no way back.
s/noresults\.hidden = visible > 0;/noresults.hidden = true;/;
