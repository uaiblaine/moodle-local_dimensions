s#(=== \[\]\) \{\n\s+)throw self::missing_framework\(\);#$1throw new \\moodle_exception('nopermissions');#;
