# Let a refusal part-way through propagate without rolling the delegated transaction back.
s/^( +)\$transaction->rollback\(\$e\);/$1throw \$e;/m;
