# Offer a filter that would leave nothing: choosing it empties the list.
s/if \(label !== '' && count !== 0\) \{/if (label !== '') {/;
