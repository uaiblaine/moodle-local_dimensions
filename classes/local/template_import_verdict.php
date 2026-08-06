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
 * The fixed vocabulary of a learning plan CSV import: verdicts, reasons, link statuses,
 * confidences, remedies and outcomes.
 *
 * The analyser assigns these, the preview renders them and the importer re-checks them, so
 * they are declared once here rather than as string literals in three places. Every label is
 * resolved by a LITERAL match() returning a fixed get_string key: the string checker cannot
 * verify a constructed id, so get_string('reason_' . $x) is forbidden.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Constants and labels for the learning plan import verdict model.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_import_verdict {
    /** @var string No template on the target matches this row: a new one would be created. */
    const VERDICT_CREATE = 'create';

    /** @var string A template matches and the row would change it. */
    const VERDICT_UPDATE = 'update';

    /** @var string A template matches and nothing would change. */
    const VERDICT_INSYNC = 'insync';

    /** @var string A template matches, but updating existing templates is switched off. */
    const VERDICT_SKIP = 'skip';

    /** @var string The row matches ambiguously, or matches something that needs a decision. */
    const VERDICT_CONFLICT = 'conflict';

    /** @var string The row cannot be applied as it stands. */
    const VERDICT_BLOCKED = 'blocked';

    /** @var string A competency row whose parent key matches no template row in the file. */
    const VERDICT_ORPHANLINK = 'orphanlink';

    /** @var string The verdict needs no reason (create, insync). */
    const REASON_NONE = '';

    /** @var string More than one template in the target context matches. */
    const REASON_AMBIGUOUS = 'ambiguous';

    /** @var string Another template in the context already uses this name. */
    const REASON_SHORTNAMETAKEN = 'shortnametaken';

    /** @var string A template with this ID number exists, but in another context. */
    const REASON_CONTEXTMISMATCH = 'contextmismatch';

    /** @var string The due date is in the past, which core refuses on a new or changed value. */
    const REASON_DUEDATEPAST = 'duedatepast';

    /** @var string The due date cell could not be read. */
    const REASON_DUEDATEUNPARSEABLE = 'duedateunparseable';

    /** @var string The name exceeds the 100 characters core allows. */
    const REASON_SHORTNAMETOOLONG = 'shortnametoolong';

    /** @var string The row carries no name. */
    const REASON_SHORTNAMEMISSING = 'shortnamemissing';

    /** @var string The description format is not one core accepts. */
    const REASON_INVALIDFORMAT = 'invalidformat';

    /** @var string None of the plan's competencies resolve on this site. */
    const REASON_STRUCTUREMISSING = 'structuremissing';

    /** @var string The user cannot manage templates in the target context. */
    const REASON_NOCAPABILITY = 'nocapability';

    /** @var string A custom field the file carries is not provisioned on this site. */
    const REASON_FIELDNOTPROVISIONED = 'fieldnotprovisioned';

    /** @var string A custom field the file carries is not writable by this user. */
    const REASON_FIELDNOTWRITABLE = 'fieldnotwritable';

    /** @var string A custom field value is longer than the field allows. */
    const REASON_CFVALUETOOLONG = 'cfvaluetoolong';

    /** @var string core_competency's own validation rejected the row. */
    const REASON_VALIDATIONFAILED = 'validationfailed';

    /** @var string A template matched but "update existing" is off. */
    const REASON_UPDATEEXISTINGOFF = 'updateexistingoff';

    /** @var string No template row in the file carries this link row's parent key. */
    const REASON_NOPARENT = 'noparent';

    /** @var string The competency resolved by ID number. */
    const LINK_MATCHED = 'matched';

    /** @var string The competency resolved by name rather than by ID number. */
    const LINK_MATCHEDFALLBACK = 'matchedfallback';

    /** @var string The competency is already on the target template. */
    const LINK_ALREADYLINKED = 'alreadylinked';

    /** @var string The structure the row names does not exist here. */
    const LINK_MISSINGFRAMEWORK = 'missingframework';

    /** @var string The structure exists but the competency does not. */
    const LINK_MISSINGCOMPETENCY = 'missingcompetency';

    /** @var string The structure is hidden, and core refuses to link a competency from one. */
    const LINK_HIDDENFRAMEWORK = 'hiddenframework';

    /** @var string More than one competency matches the row. */
    const LINK_AMBIGUOUS = 'ambiguous';

    /** @var string The row names no competency at all. */
    const LINK_EMPTYREFERENCE = 'emptyreference';

    /** @var string Resolved by the ID number, the only DB-enforced cross-site key. */
    const CONFIDENCE_EXACT = 'exact';

    /** @var string Resolved by the template's name. */
    const CONFIDENCE_TEMPLATESHORTNAME = 'templateshortname';

    /** @var string Resolved by the structure's name. */
    const CONFIDENCE_FRAMEWORKSHORTNAME = 'frameworkshortname';

    /** @var string Resolved by the competency's name, within the resolved structure. */
    const CONFIDENCE_COMPETENCYSHORTNAME = 'competencyshortname';

    /** @var string Not resolved. */
    const CONFIDENCE_NONE = 'none';

    /** @var string Apply the row as it stands. */
    const REMEDY_NONE = 'none';

    /** @var string Drop the past due date instead of blocking on it. */
    const REMEDY_CLEARDUEDATE = 'clearduedate';

    /** @var string Move the past due date forward in whole years until it is in the future. */
    const REMEDY_SHIFTDUEDATE = 'shiftduedate';

    /** @var string Keep the stored due date, offered only when it is byte-identical. */
    const REMEDY_KEEPDUEDATE = 'keepduedate';

    /** @var string Shorten an over-long name to the 100 characters core allows. */
    const REMEDY_TRUNCATE = 'truncate';

    /** @var string Import only the competencies that resolve here. */
    const REMEDY_PARTIAL = 'partial';

    /** @var string Treat the same-named template in the context as the match. */
    const REMEDY_ADOPT = 'adopt';

    /** @var string Create a separate template here, alongside the one in the other context. */
    const REMEDY_CREATEHERE = 'createhere';

    /** @var string The template was created. */
    const OUTCOME_CREATED = 'created';

    /** @var string The template was updated. */
    const OUTCOME_UPDATED = 'updated';

    /** @var string The selection was not applied. */
    const OUTCOME_SKIPPED = 'skipped';

    /** @var string The row moved between the preview and the apply, so nothing was written. */
    const OUTCOME_CHANGED = 'changed';

    /** @var string The row is no longer in the file. */
    const OUTCOME_GONE = 'gone';

    /** @var string The write failed; the rest of the run continued. */
    const OUTCOME_FAILED = 'failed';

    /**
     * Every template verdict.
     *
     * @return array
     */
    public static function verdicts(): array {
        return [
            self::VERDICT_CREATE, self::VERDICT_UPDATE, self::VERDICT_INSYNC, self::VERDICT_SKIP,
            self::VERDICT_CONFLICT, self::VERDICT_BLOCKED, self::VERDICT_ORPHANLINK,
        ];
    }

    /**
     * Every reason, excluding the empty one.
     *
     * @return array
     */
    public static function reasons(): array {
        return [
            self::REASON_AMBIGUOUS, self::REASON_SHORTNAMETAKEN, self::REASON_CONTEXTMISMATCH,
            self::REASON_DUEDATEPAST, self::REASON_DUEDATEUNPARSEABLE, self::REASON_SHORTNAMETOOLONG,
            self::REASON_SHORTNAMEMISSING, self::REASON_INVALIDFORMAT, self::REASON_STRUCTUREMISSING,
            self::REASON_NOCAPABILITY, self::REASON_FIELDNOTPROVISIONED, self::REASON_FIELDNOTWRITABLE,
            self::REASON_CFVALUETOOLONG, self::REASON_VALIDATIONFAILED, self::REASON_UPDATEEXISTINGOFF,
            self::REASON_NOPARENT,
        ];
    }

    /**
     * Every competency-link status.
     *
     * @return array
     */
    public static function link_statuses(): array {
        return [
            self::LINK_MATCHED, self::LINK_MATCHEDFALLBACK, self::LINK_ALREADYLINKED,
            self::LINK_MISSINGFRAMEWORK, self::LINK_MISSINGCOMPETENCY, self::LINK_HIDDENFRAMEWORK,
            self::LINK_AMBIGUOUS, self::LINK_EMPTYREFERENCE,
        ];
    }

    /**
     * Every match confidence.
     *
     * @return array
     */
    public static function confidences(): array {
        return [
            self::CONFIDENCE_EXACT, self::CONFIDENCE_TEMPLATESHORTNAME, self::CONFIDENCE_FRAMEWORKSHORTNAME,
            self::CONFIDENCE_COMPETENCYSHORTNAME, self::CONFIDENCE_NONE,
        ];
    }

    /**
     * Every remedy.
     *
     * @return array
     */
    public static function remedies(): array {
        return [
            self::REMEDY_NONE, self::REMEDY_CLEARDUEDATE, self::REMEDY_SHIFTDUEDATE, self::REMEDY_KEEPDUEDATE,
            self::REMEDY_TRUNCATE, self::REMEDY_PARTIAL, self::REMEDY_ADOPT, self::REMEDY_CREATEHERE,
        ];
    }

    /**
     * Every apply outcome.
     *
     * @return array
     */
    public static function outcomes(): array {
        return [
            self::OUTCOME_CREATED, self::OUTCOME_UPDATED, self::OUTCOME_SKIPPED,
            self::OUTCOME_CHANGED, self::OUTCOME_GONE, self::OUTCOME_FAILED,
        ];
    }

    /**
     * Whether a link status means the competency was found and can be linked.
     *
     * @param string $status One of the LINK_* constants.
     * @return bool
     */
    public static function link_is_resolved(string $status): bool {
        return match ($status) {
            self::LINK_MATCHED, self::LINK_MATCHEDFALLBACK, self::LINK_ALREADYLINKED => true,
            default => false,
        };
    }

    /**
     * Whether a link status means the row named a competency that could not be resolved.
     *
     * LINK_EMPTYREFERENCE is deliberately neither resolved nor unresolved: a row naming no
     * competency at all must not push a template into the "no structure on this site" roll-up.
     *
     * @param string $status One of the LINK_* constants.
     * @return bool
     */
    public static function link_is_unresolved(string $status): bool {
        return match ($status) {
            self::LINK_MISSINGFRAMEWORK, self::LINK_MISSINGCOMPETENCY,
            self::LINK_HIDDENFRAMEWORK, self::LINK_AMBIGUOUS => true,
            default => false,
        };
    }

    /**
     * The short label for a verdict.
     *
     * @param string $verdict One of the VERDICT_* constants.
     * @return string
     */
    public static function verdict_label(string $verdict): string {
        $key = match ($verdict) {
            self::VERDICT_CREATE => 'central_plans_import_verdict_create',
            self::VERDICT_UPDATE => 'central_plans_import_verdict_update',
            self::VERDICT_INSYNC => 'central_plans_import_verdict_insync',
            self::VERDICT_SKIP => 'central_plans_import_verdict_skip',
            self::VERDICT_CONFLICT => 'central_plans_import_verdict_conflict',
            self::VERDICT_BLOCKED => 'central_plans_import_verdict_blocked',
            self::VERDICT_ORPHANLINK => 'central_plans_import_verdict_orphanlink',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The sentence explaining what a verdict means for the operator.
     *
     * @param string $verdict One of the VERDICT_* constants.
     * @return string
     */
    public static function verdict_help(string $verdict): string {
        $key = match ($verdict) {
            self::VERDICT_CREATE => 'central_plans_import_verdicthelp_create',
            self::VERDICT_UPDATE => 'central_plans_import_verdicthelp_update',
            self::VERDICT_INSYNC => 'central_plans_import_verdicthelp_insync',
            self::VERDICT_SKIP => 'central_plans_import_verdicthelp_skip',
            self::VERDICT_CONFLICT => 'central_plans_import_verdicthelp_conflict',
            self::VERDICT_BLOCKED => 'central_plans_import_verdicthelp_blocked',
            self::VERDICT_ORPHANLINK => 'central_plans_import_verdicthelp_orphanlink',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The Bootstrap badge classes a verdict wears in the preview.
     *
     * @param string $verdict One of the VERDICT_* constants.
     * @return string
     */
    public static function verdict_badge(string $verdict): string {
        return match ($verdict) {
            self::VERDICT_CREATE => 'bg-success text-white',
            self::VERDICT_UPDATE => 'bg-primary text-white',
            self::VERDICT_INSYNC => 'bg-secondary text-dark',
            self::VERDICT_SKIP => 'bg-secondary text-dark',
            self::VERDICT_CONFLICT => 'bg-warning text-dark',
            self::VERDICT_BLOCKED => 'bg-danger text-white',
            self::VERDICT_ORPHANLINK => 'bg-danger text-white',
            default => 'bg-secondary text-dark',
        };
    }

    /**
     * The sentence explaining a reason. The empty reason has an empty label.
     *
     * @param string $reason One of the REASON_* constants.
     * @return string
     */
    public static function reason_label(string $reason): string {
        $key = match ($reason) {
            self::REASON_AMBIGUOUS => 'central_plans_import_reason_ambiguous',
            self::REASON_SHORTNAMETAKEN => 'central_plans_import_reason_shortnametaken',
            self::REASON_CONTEXTMISMATCH => 'central_plans_import_reason_contextmismatch',
            self::REASON_DUEDATEPAST => 'central_plans_import_reason_duedatepast',
            self::REASON_DUEDATEUNPARSEABLE => 'central_plans_import_reason_duedateunparseable',
            self::REASON_SHORTNAMETOOLONG => 'central_plans_import_reason_shortnametoolong',
            self::REASON_SHORTNAMEMISSING => 'central_plans_import_reason_shortnamemissing',
            self::REASON_INVALIDFORMAT => 'central_plans_import_reason_invalidformat',
            self::REASON_STRUCTUREMISSING => 'central_plans_import_reason_structuremissing',
            self::REASON_NOCAPABILITY => 'central_plans_import_reason_nocapability',
            self::REASON_FIELDNOTPROVISIONED => 'central_plans_import_reason_fieldnotprovisioned',
            self::REASON_FIELDNOTWRITABLE => 'central_plans_import_reason_fieldnotwritable',
            self::REASON_CFVALUETOOLONG => 'central_plans_import_reason_cfvaluetoolong',
            self::REASON_VALIDATIONFAILED => 'central_plans_import_reason_validationfailed',
            self::REASON_UPDATEEXISTINGOFF => 'central_plans_import_reason_updateexistingoff',
            self::REASON_NOPARENT => 'central_plans_import_reason_noparent',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The label for a competency-link status.
     *
     * @param string $status One of the LINK_* constants.
     * @return string
     */
    public static function link_status_label(string $status): string {
        $key = match ($status) {
            self::LINK_MATCHED => 'central_plans_import_link_matched',
            self::LINK_MATCHEDFALLBACK => 'central_plans_import_link_matchedfallback',
            self::LINK_ALREADYLINKED => 'central_plans_import_link_alreadylinked',
            self::LINK_MISSINGFRAMEWORK => 'central_plans_import_link_missingframework',
            self::LINK_MISSINGCOMPETENCY => 'central_plans_import_link_missingcompetency',
            self::LINK_HIDDENFRAMEWORK => 'central_plans_import_link_hiddenframework',
            self::LINK_AMBIGUOUS => 'central_plans_import_link_ambiguous',
            self::LINK_EMPTYREFERENCE => 'central_plans_import_link_emptyreference',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The Bootstrap badge classes a link status wears in the preview.
     *
     * A name-based match is badged as a warning rather than a success, so the inference is
     * visible: it is ticked, but the operator can see it was not an ID number match.
     *
     * @param string $status One of the LINK_* constants.
     * @return string
     */
    public static function link_status_badge(string $status): string {
        return match ($status) {
            self::LINK_MATCHED => 'bg-success text-white',
            self::LINK_MATCHEDFALLBACK => 'bg-warning text-dark',
            self::LINK_ALREADYLINKED => 'bg-secondary text-dark',
            self::LINK_AMBIGUOUS => 'bg-warning text-dark',
            self::LINK_EMPTYREFERENCE => 'bg-secondary text-dark',
            default => 'bg-danger text-white',
        };
    }

    /**
     * The label for a match confidence.
     *
     * @param string $confidence One of the CONFIDENCE_* constants.
     * @return string
     */
    public static function confidence_label(string $confidence): string {
        $key = match ($confidence) {
            self::CONFIDENCE_EXACT => 'central_plans_import_confidence_exact',
            self::CONFIDENCE_TEMPLATESHORTNAME => 'central_plans_import_confidence_templateshortname',
            self::CONFIDENCE_FRAMEWORKSHORTNAME => 'central_plans_import_confidence_frameworkshortname',
            self::CONFIDENCE_COMPETENCYSHORTNAME => 'central_plans_import_confidence_competencyshortname',
            self::CONFIDENCE_NONE => 'central_plans_import_confidence_none',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The label for a remedy, as the operator reads it on the row's control.
     *
     * @param string $remedy One of the REMEDY_* constants.
     * @return string
     */
    public static function remedy_label(string $remedy): string {
        $key = match ($remedy) {
            self::REMEDY_NONE => 'central_plans_import_remedy_none',
            self::REMEDY_CLEARDUEDATE => 'central_plans_import_remedy_clearduedate',
            self::REMEDY_SHIFTDUEDATE => 'central_plans_import_remedy_shiftduedate',
            self::REMEDY_KEEPDUEDATE => 'central_plans_import_remedy_keepduedate',
            self::REMEDY_TRUNCATE => 'central_plans_import_remedy_truncate',
            self::REMEDY_PARTIAL => 'central_plans_import_remedy_partial',
            self::REMEDY_ADOPT => 'central_plans_import_remedy_adopt',
            self::REMEDY_CREATEHERE => 'central_plans_import_remedy_createhere',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The label for an apply outcome.
     *
     * @param string $outcome One of the OUTCOME_* constants.
     * @return string
     */
    public static function outcome_label(string $outcome): string {
        $key = match ($outcome) {
            self::OUTCOME_CREATED => 'central_plans_import_outcome_created',
            self::OUTCOME_UPDATED => 'central_plans_import_outcome_updated',
            self::OUTCOME_SKIPPED => 'central_plans_import_outcome_skipped',
            self::OUTCOME_CHANGED => 'central_plans_import_outcome_changed',
            self::OUTCOME_GONE => 'central_plans_import_outcome_gone',
            self::OUTCOME_FAILED => 'central_plans_import_outcome_failed',
            default => '',
        };
        return $key === '' ? '' : get_string($key, 'local_dimensions');
    }

    /**
     * The Bootstrap badge classes an apply outcome wears in the result row.
     *
     * @param string $outcome One of the OUTCOME_* constants.
     * @return string
     */
    public static function outcome_badge(string $outcome): string {
        return match ($outcome) {
            self::OUTCOME_CREATED, self::OUTCOME_UPDATED => 'bg-success text-white',
            self::OUTCOME_SKIPPED, self::OUTCOME_GONE => 'bg-secondary text-dark',
            self::OUTCOME_CHANGED => 'bg-warning text-dark',
            default => 'bg-danger text-white',
        };
    }
}
