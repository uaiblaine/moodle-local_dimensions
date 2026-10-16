# Leave Escape to the browser in the field: the search stays open over the line and the field is emptied.
s/\} else if \(event\.key === 'Escape' && \(state\.open \|\| state\.searching\)\) \{/} else if (event.key === 'Esc' && (state.open || state.searching)) {/;
