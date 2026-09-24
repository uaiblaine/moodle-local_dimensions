s/(return \$allowed\[\$optionindex\] \?\? \$default;)/if (str_contains(\$options[\$optionindex], '|')) {\n            return \$default;\n        }\n        $1/;
