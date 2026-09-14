s/if \(!\$cm->visible \|\| !\(\$cm->is_visible_on_course_page\(\) \|\| \$cm->uservisible\)\) \{/if (false) {/;
s/if \(count\(\$tracked\) === 1 && \$tracked\[0\]->uservisible\) \{/if (count(\$tracked) === 1) {/;
