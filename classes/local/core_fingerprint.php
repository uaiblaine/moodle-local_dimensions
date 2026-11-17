<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Fingerprints of the core code the progress rule mirrors.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Hashes of core's mirrored functions, read from the source files of any core tree.
 *
 * The manifest (tests/fixtures/core_parity.json) names each function the progress rule
 * ({@see \local_dimensions\calculator::counts_towards_progress()}) and {@see learner_restrictions} mirror
 * or lean on, with the hash of its code when it was last audited
 * against core, and the core condition types that filter user lists. A hash covers the function's
 * tokens with comments and whitespace removed, so a comment or a reformat is not a change; a renamed
 * variable is. The source is tokenised, never loaded, so the same check reads the running site's core
 * or another checkout (cli/core_parity.php --root, the fleet's weekly look upstream).
 *
 * The procedure that follows a difference is docs/core-parity.md. block_compass carries the same class over the
 * same manifest (its ADR-027); an audit reads both plugins.
 */
final class core_fingerprint {
    /** @var string The manifest, relative to the plugin's directory. */
    public const MANIFEST = '/tests/fixtures/core_parity.json';

    /**
     * The manifest as stored.
     *
     * @return array With audited (the core release), symbols (each with file, class, function and hash)
     *     and listconditions (condition type names).
     */
    public static function manifest(): array {
        return json_decode(file_get_contents(dirname(__DIR__, 2) . self::MANIFEST), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * What differs between a core tree and the manifest.
     *
     * @param string $root The core tree's directory that holds lib/ and availability/ (dirroot).
     * @param array $manifest The manifest ({@see manifest()}).
     * @return array Readable lines, one per difference; empty when the tree matches.
     */
    public static function differences(string $root, array $manifest): array {
        $lines = [];
        foreach ($manifest['symbols'] as $symbol) {
            $hash = self::symbol($root . '/' . $symbol['file'], $symbol['class'], $symbol['function']);
            if ($hash === null) {
                $lines[] = self::name($symbol) . ' is gone from ' . $symbol['file'];
            } else if ($hash !== $symbol['hash']) {
                $lines[] = self::name($symbol) . ' changed in ' . $symbol['file'];
            }
        }
        $conditions = self::list_conditions($root);
        if ($conditions !== $manifest['listconditions']) {
            $lines[] = 'the condition types that filter user lists are now [' . implode(', ', $conditions)
                . '], were [' . implode(', ', $manifest['listconditions']) . ']';
        }
        return $lines;
    }

    /**
     * The manifest with every hash and the condition list read again from a core tree.
     *
     * @param string $root The core tree's dirroot.
     * @param array $manifest The manifest whose symbols are read.
     * @param string $audited The core release the new manifest is audited against.
     * @return array The new manifest; a symbol gone from the tree keeps a null hash.
     */
    public static function rebuild(string $root, array $manifest, string $audited): array {
        $manifest['audited'] = $audited;
        foreach ($manifest['symbols'] as $index => $symbol) {
            $manifest['symbols'][$index]['hash'] = self::symbol(
                $root . '/' . $symbol['file'],
                $symbol['class'],
                $symbol['function']
            );
        }
        $manifest['listconditions'] = self::list_conditions($root);
        return $manifest;
    }

    /**
     * The hash of one function or method, or null when the file or the function is not there.
     *
     * @param string $file The source file.
     * @param string|null $class The class's short name, null for a function outside any class.
     * @param string $function The function's name.
     * @return string|null A sha1 of its tokens without comments and whitespace.
     */
    public static function symbol(string $file, ?string $class, string $function): ?string {
        if (!is_readable($file)) {
            return null;
        }
        $tokens = token_get_all(file_get_contents($file));
        $body = self::function_tokens($tokens, $class, $function);
        return $body === null ? null : sha1(implode(' ', $body));
    }

    /**
     * The core condition types whose condition class says it filters user lists.
     *
     * Read from each availability/condition/<type>/classes/condition.php: a type counts when its
     * is_applied_to_user_lists() is exactly "return true". A type with a computed answer counts too, so
     * that a new rule is never silently left out; an audit reads it.
     *
     * @param string $root The core tree's dirroot.
     * @return string[] Type names, sorted.
     */
    public static function list_conditions(string $root): array {
        $types = [];
        foreach (glob($root . '/availability/condition/*/classes/condition.php') ?: [] as $file) {
            $body = self::function_tokens(token_get_all(file_get_contents($file)), 'condition', 'is_applied_to_user_lists');
            if ($body === null) {
                continue;
            }
            $open = array_search('{', $body, true);
            $statement = array_slice($body, $open + 1, -1);
            if ($statement !== ['return', 'false', ';']) {
                $types[] = basename(dirname($file, 2));
            }
        }
        sort($types);
        return $types;
    }

    /**
     * A symbol's readable name.
     *
     * @param array $symbol One manifest entry.
     * @return string
     */
    public static function name(array $symbol): string {
        return ($symbol['class'] === null ? '' : $symbol['class'] . '::') . $symbol['function'] . '()';
    }

    /**
     * The tokens of a function, from the word function to its closing brace, without comments and whitespace.
     *
     * @param array $tokens The file's tokens.
     * @param string|null $class The class's short name, null for a function outside any class.
     * @param string $function The function's name.
     * @return string[]|null The token texts, or null when the function is not found.
     */
    private static function function_tokens(array $tokens, ?string $class, string $function): ?array {
        $count = count($tokens);
        $start = 0;
        if ($class !== null) {
            $start = null;
            for ($i = 0; $i < $count; $i++) {
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                    $name = self::next_meaningful($tokens, $i);
                    if ($name !== null && is_array($tokens[$name]) && $tokens[$name][1] === $class) {
                        $start = $name;
                        break;
                    }
                }
            }
            if ($start === null) {
                return null;
            }
        }

        $depth = 0;
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } else if ($text === '}') {
                $depth--;
                if ($class !== null && $depth === 0) {
                    // Left the class without meeting the method.
                    return null;
                }
            }
            $atlevel = $class === null ? $depth === 0 : $depth === 1;
            if (!$atlevel || !is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = self::next_meaningful($tokens, $i);
            if ($name === null || !is_array($tokens[$name]) || strcasecmp($tokens[$name][1], $function) !== 0) {
                continue;
            }
            return self::collect($tokens, $i);
        }
        return null;
    }

    /**
     * The meaningful tokens from a function keyword to the end of its body, or to its ; when abstract.
     *
     * @param array $tokens The file's tokens.
     * @param int $from The index of the function keyword.
     * @return string[]
     */
    private static function collect(array $tokens, int $from): array {
        $out = [];
        $depth = 0;
        $count = count($tokens);
        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $text = is_array($token) ? $token[1] : $token;
            $out[] = $text;
            if ($text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } else if ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } else if ($text === ';' && $depth === 0) {
                break;
            }
        }
        return $out;
    }

    /**
     * The index of the next token that is not whitespace or a comment.
     *
     * @param array $tokens The file's tokens.
     * @param int $from The index to start after.
     * @return int|null
     */
    private static function next_meaningful(array $tokens, int $from): ?int {
        $count = count($tokens);
        for ($i = $from + 1; $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $i;
        }
        return null;
    }
}
