s/(\/\/ 1\. Filter by visibility \(Eye icon\)\.\n)\s*if \(!\$section->visible\) \{\n\s*continue;\n\s*\}\n/$1/;
s/if \(!\$section->uservisible\) \{\n(\s*\/\/ If it is set to "Hide entirely")/if (false) {\n$1/;
