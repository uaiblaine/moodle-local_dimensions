s/\$cache->set\(\$id, \$payload\);/\$cache->set(\$id, self::normalise_payload(\$payload));/;
s/(is_array\(\$payload\)\) \{\n\s+\$result\[\$id\] = )self::normalise_payload\(\$payload\)/$1\$payload/;
