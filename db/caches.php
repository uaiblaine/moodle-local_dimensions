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
 * Cache definitions for local_dimensions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Cache for storing valid course IDs per template.
    // Key: template_id
    // Value: array of course IDs linked to all competencies in the template.
    'template_courses' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 50,
        'ttl' => 3600, // 1 hour TTL.
    ],

    // Cache for template metadata consumed by block cards.
    // Key: templateid
    // Value: type, tag1, tag2, bgcolor, textcolor, templatecardimageurl, timemodified.
    // Invalidated when template metadata or card image is updated.
    'template_metadata' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
        'ttl' => 1800, // 30 minutes defensive TTL.
    ],

    // Cache for competency metadata consumed by block cards.
    // Key: competencyid
    // Value: tag1, tag2, bgcolor, textcolor, cardimageurl, timemodified.
    // Invalidated when competency metadata or card image is updated.
    'competency_metadata' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 200,
        'ttl' => 1800, // 30 minutes defensive TTL.
    ],

    // Cache for compiled CSS from template SCSS custom fields.
    // Key: css_{templateid}
    // Value: compiled CSS string.
    // Invalidated manually when SCSS is saved in the template form.
    'template_scss' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 20,
    ],

    // Cache for compiled CSS from competency SCSS custom fields.
    // Key: css_{competencyid}
    // Value: compiled CSS string.
    // Invalidated manually when SCSS is saved in the competency form.
    'competency_scss' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 20,
    ],

    // Session cache for the "Return to Plan" button context.
    // Key: 'returncontext'
    // Value: serialised array with return URL and valid course IDs.
    'returncontext' => [
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => true,
    ],

    // Session cache for plan trail data (competency id, shortname, proficiency).
    // Key: planid_userid
    // Value: array with total count and competency trail rows.
    // Invalidated when user competency proficiency changes.
    'plan_trail' => [
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 300, // 5 minutes defensive TTL.
    ],

    // Cache for course-level custom field values consumed by chip filters.
    // Key: courseid
    // Value: array<shortname, value> of all course custom fields for the course.
    // Defensive TTL keeps the cache fresh enough between admin edits without
    // requiring an explicit invalidation hook.
    'course_customfields' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 200,
        'ttl' => 600, // 10 minutes defensive TTL.
    ],
];
