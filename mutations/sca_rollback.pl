s/\$transaction->rollback\(\$e\);/\$transaction->allow_commit();\n            throw \$e;/;
