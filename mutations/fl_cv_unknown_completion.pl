# Hide a card whose completion is not known yet under "To do": the grid is empty until the batch answers.
s/wrapper\.getAttribute\('data-completed'\) === '1'\) \{/wrapper.getAttribute('data-completed') !== '0') {/;
