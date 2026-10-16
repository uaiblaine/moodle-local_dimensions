# Drop the quiet guard: the focus given back as the search closes opens it again.
s/if \(state\.quiet \|\| !intent\) \{/if (!intent) {/;
