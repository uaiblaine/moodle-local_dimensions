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
 * English language strings for local_dimensions plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// All strings below are sorted alphabetically by key.
$string['access'] = 'Access';
$string['access_course'] = 'Access course';
$string['accordioncontentsheading'] = 'Accordion Content Display';
$string['accordioncontentsheading_desc'] = 'Choose which elements to display when expanding a competency in the plan summary view.';
$string['add_comment'] = 'Comment';
$string['addcompetencywithcustomfields'] = 'Add Competency with Custom Fields';
$string['api_completion_enabled'] = 'If completion is enabled';
$string['api_completion_percentage'] = 'Completion percentage';
$string['api_content_locked'] = 'If content is locked';
$string['api_course_id'] = 'Course ID';
$string['api_error_message'] = 'Auxiliary error message';
$string['api_formatted_start_date'] = 'Formatted start date';
$string['api_has_activities'] = 'If has activities';
$string['api_is_locked'] = 'If locked';
$string['api_section_name'] = 'Section name';
$string['api_section_url'] = 'Section URL';
$string['aria_completion_percentage'] = 'Section progress: {$a}%';
$string['assessment_status'] = 'Assessment';
$string['available_at'] = 'Available on {$a}';
$string['cancel_reply'] = 'Cancel';
$string['cardicon'] = 'Locked card icon';
$string['cardicon_browseall'] = 'Browse all icons';
$string['cardicon_desc'] = 'Choose a custom icon for locked course cards. If empty, the default lock icon is used.';
$string['cardicon_noicon'] = 'Default lock icon';
$string['cardicon_placeholder'] = 'Search for an icon...';
$string['cardicon_sourcecore'] = 'Core';
$string['cardicon_sourcefablank'] = 'FA';
$string['cardicon_sourcefabrand'] = 'FA Brand';
$string['cardicon_sourcefasolid'] = 'FA Solid';
$string['cardicon_toomanyicons'] = 'Too many icons found ({$a}). Please refine your search.';
$string['comment_by'] = 'Comment by';
$string['comment_placeholder'] = 'Write a comment...';
$string['comments_section'] = 'Comments';
$string['competency_id'] = 'Competency ID: {$a}';
$string['competency_id_missing'] = 'Competency ID not provided.';
$string['competency_path'] = 'Path';
$string['competencyviewheading'] = 'Competency View Mode';
$string['competencyviewheading_desc'] = 'Settings for the competency courses view.';
$string['completion_disabled'] = 'Completion disabled.';
$string['connection_error'] = 'Connection error.';
$string['custombgcolor'] = 'Background Color';
$string['custombgcolor_desc'] = 'Custom background color in hex format (e.g., #3498db)';
$string['custombgimage'] = 'Background Image';
$string['custombgimage_desc'] = 'Custom background image for the hero header';
$string['custombgimage_help'] = 'Upload an image to be displayed as the background of the hero header. Recommended formats: JPG or PNG. The image will be resized to fit the header area.';
$string['customcard'] = 'Card Image';
$string['customcard_desc'] = 'Custom image to display on the card';
$string['customcard_help'] = 'Upload an image to be displayed on the card. Recommended formats: JPG or PNG.';
$string['customfields'] = 'Competency Custom Fields';
$string['customscss'] = 'Custom SCSS';
$string['customscss_desc'] = 'Custom SCSS code to be compiled and injected in the Plan Summary page. Supports full SCSS syntax including nesting, variables and mixins.';
$string['customscss_invalid'] = 'Invalid SCSS syntax: {$a}';
$string['customscss_js_closingbracewithoutopen'] = 'SCSS validation error: there is a closing brace "}" without a matching opening brace "{".';
$string['customscss_js_closingparenwithoutopen'] = 'SCSS validation error: there is a closing parenthesis ")" without a matching opening parenthesis "(".';
$string['customscss_js_punctuationwarning'] = 'Warning: possible missing punctuation (such as semicolon) on line(s): {$a}. Do you want to save anyway?';
$string['customscss_js_unbalancedbraces'] = 'SCSS validation error: opening and closing braces do not match. Please review your SCSS before saving.';
$string['customscss_js_unbalancedparentheses'] = 'SCSS validation error: opening and closing parentheses do not match. Please review your SCSS before saving.';
$string['customtextcolor'] = 'Text Color';
$string['customtextcolor_desc'] = 'Custom text color in hex format (e.g., #ffffff)';
$string['description_label'] = 'Description';
$string['dimensions:view'] = 'View Dimensions';
$string['displaymode'] = 'Display Mode';
$string['displaymode_competencies'] = 'Competencies Format';
$string['displaymode_desc'] = 'Choose how the learning plan will be displayed in the Dimensions block';
$string['displaymode_plan'] = 'Plan Format';
$string['duedate'] = 'Due Date';
$string['editcompetencywithcustomfields'] = 'Edit Competency with Custom Fields';
$string['edittemplate'] = 'Edit Template';
$string['emptycomment'] = 'Comment cannot be empty';
$string['enablecustomscss'] = 'Enable custom SCSS';
$string['enablecustomscss_desc'] = 'When enabled, both the Plan Summary and Competency View pages can have custom SCSS code compiled and injected as CSS. The SCSS is validated on save.';
$string['enablereturnbutton'] = 'Enable "Return to Plan" button';
$string['enablereturnbutton_desc'] = 'Show a floating button that allows students to quickly return to the learning plan view after accessing a course or activity.';
$string['enrollmentfilter'] = 'Enrolments';
$string['enrollmentfilter_active'] = 'Show only courses with active enrolments';
$string['enrollmentfilter_all'] = 'Show all courses linked to the competency';
$string['enrollmentfilter_desc'] = 'Filter which courses are displayed in the competency view based on the user\'s enrolment status.';
$string['enrollmentfilter_enrolled'] = 'Show only enrolled courses (includes future enrolments)';
$string['enrolment_starts'] = 'Enrolment starts {$a}';
$string['error_loading_summary'] = 'Error loading competency summary.';
$string['erroraddingcomment'] = 'Error adding comment';
$string['evidence_author'] = 'Registered by';
$string['evidence_by'] = 'by';
$string['evidence_date'] = 'Date';
$string['evidence_details'] = 'Evidence details';
$string['evidence_grade'] = 'Rating';
$string['evidence_label'] = 'Evidence';
$string['evidence_link'] = 'Related activity';
$string['evidence_note'] = 'Note';
$string['evidence_open_link'] = 'Open activity';
$string['evidence_slider_next'] = 'Next';
$string['evidence_slider_prev'] = 'Previous';
$string['evidence_type_activity'] = 'Activity completion';
$string['evidence_type_coursegrade'] = 'Course grade';
$string['evidence_type_file'] = 'File attachment';
$string['evidence_type_manual'] = 'Manual rating';
$string['evidence_type_other'] = 'Other evidence';
$string['evidence_type_prior'] = 'Prior learning';
$string['evidence_view_details'] = 'View details';
$string['filter_all'] = 'All';
$string['filter_competencies'] = 'Filter competencies';
$string['filter_not_completed'] = 'Not completed';
$string['generalsettingsheading'] = 'General Settings';
$string['generalsettingsheading_desc'] = 'General display and navigation settings.';
$string['imagehandler'] = 'Image handling method';
$string['imagehandler_builtin'] = 'Built-in (no external plugin required)';
$string['imagehandler_desc'] = 'Choose how background images are managed. "Built-in" handles images directly within the plugin. "External plugin" uses the separate customfield_picture plugin (must be installed).';
$string['imagehandler_external'] = 'External plugin (customfield_picture)';
$string['in_framework'] = 'in';
$string['invalidplan'] = 'Invalid learning plan.';
$string['learn_more'] = 'Learn more';
$string['learning_plan_id'] = 'Learning Plan ID: {$a}';
$string['learningplantemplates'] = 'Learning Plan Templates';
$string['learnmorebuttoncolor'] = '"Learn More" button color';
$string['learnmorebuttoncolor_desc'] = 'Choose the color for the "Learn More" button on locked course cards.';
$string['linked_courses'] = 'Linked Courses';
$string['linked_courses_count'] = '{$a} courses';
$string['loading_competency_summary'] = 'Loading...';
$string['loading_progress'] = 'Loading progress...';
$string['local_dimensions_handler_header'] = 'Fill custom fields for Competency';
$string['locked'] = 'Locked';
$string['locked_content'] = 'Locked Content';
$string['lockedcardmode'] = 'Locked card display mode';
$string['lockedcardmode_blocked'] = 'Locked Content';
$string['lockedcardmode_desc'] = 'Choose how locked course cards are displayed. "Locked Content" shows a static message. "Learn More" shows a button linking to the course page.';
$string['lockedcardmode_learnmore'] = 'Learn More';
$string['managecompetencies'] = 'Manage Competencies';
$string['managetemplates'] = 'Manage Learning Plan Templates';
$string['no'] = 'No';
$string['no_comments'] = 'No comments yet';
$string['no_competencies_in_plan'] = 'No competencies in this learning plan.';
$string['no_completion_tracking'] = 'No completion tracking';
$string['no_courses_linked'] = 'No courses linked to this competency.';
$string['no_evidence'] = 'No evidence recorded';
$string['no_related'] = 'No related competencies';
$string['nocompetencies'] = 'No competencies in this framework';
$string['noframeworks'] = 'No competency frameworks found';
$string['nopermissiontocomment'] = 'You do not have permission to post comments';
$string['not_evaluated'] = '-';
$string['notemplates'] = 'No learning plan templates found';
$string['notproficient'] = 'Not Proficient';
$string['notrated'] = 'Not rated';
$string['percentagedisplaymode'] = 'Percentage Display Mode';
$string['percentagedisplaymode_desc'] = 'Choose how the completion percentage will be displayed on course cards';
$string['percentagemode_fixed'] = 'Show percentage fixed (always visible)';
$string['percentagemode_hidden'] = 'Do not show percentage';
$string['percentagemode_hover'] = 'Show percentage on hover';
$string['plan_competencies'] = 'Plan Competencies';
$string['plan_progress'] = 'Plan Progress';
$string['plansummaryheading'] = 'Plan Summary Mode';
$string['plansummaryheading_desc'] = 'Settings for the accordion content display in plan summary view.';
$string['pluginname'] = 'Dimensions';
$string['pluginsettings'] = 'Local Dimensions Settings';
$string['proficiency'] = 'Proficiency';
$string['proficient'] = 'Proficient';
$string['proficient_label'] = 'Proficient';
$string['proficient_status'] = 'Proficiency Status';
$string['rating'] = 'Rating';
$string['rating_label'] = 'Rating';
$string['related_competencies'] = 'Related competencies';
$string['reply'] = 'Reply';
$string['reply_error'] = 'Error sending reply';
$string['reply_placeholder'] = 'Write your reply...';
$string['reply_sent'] = 'Reply sent successfully';
$string['returnbuttoncolor'] = 'Button color';
$string['returnbuttoncolor_desc'] = 'Choose the color for the "Return to Plan" floating button.';
$string['returntoplan'] = 'Return to Plan';
$string['search_competencies'] = 'Search competencies...';
$string['selectframework'] = 'Select a competency framework to view competencies';
$string['send_reply'] = 'Send';
$string['show_less'] = 'See less';
$string['show_more'] = 'See more';
$string['showcomments'] = 'Show comments';
$string['showcomments_desc'] = 'Display comments section with reply functionality.';
$string['showdescription'] = 'Show competency description';
$string['showdescription_desc'] = 'Display the full description of the competency.';
$string['showevidence'] = 'Show evidence';
$string['showevidence_desc'] = 'Display evidence cards with icons for attachments, grades, etc.';
$string['showlockeddate'] = 'Show availability date';
$string['showlockeddate_desc'] = 'Show the availability date on locked course cards.';
$string['showpath'] = 'Show competency path';
$string['showpath_desc'] = 'Display the hierarchy path (framework > parent > competency).';
$string['showrelated'] = 'Show related competencies';
$string['showrelated_desc'] = 'Display competencies that are linked as related.';
$string['singlecourseredirect'] = 'Redirect to course when single active enrolment';
$string['singlecourseredirect_desc'] = 'When the enrolment filter is set to "active" and the user has only one course with an active enrolment for the selected competency, skip the competency view and redirect directly to the course page. This provides a faster experience for users who have a single active course path.';
$string['summaryenrollmentfilter'] = 'Enrolments';
$string['summaryenrollmentfilter_desc'] = 'Filter which courses are displayed in the plan summary accordion based on the user\'s enrolment status.';
$string['tag1'] = 'Year';
$string['tag1_desc'] = 'Year or period classification';
$string['tag1_options'] = "1st Year\n2nd Year\n3rd Year";
$string['tag2'] = 'Category';
$string['tag2_desc'] = 'Category or type classification';
$string['tag2_options'] = "Basic\nIntermediate\nAdvanced";
$string['template_handler_header'] = 'Fill custom fields for Learning Plan Template';
$string['templatecustomfields'] = 'Learning Plan Template Custom Fields';
$string['templateinfo'] = 'Template Information';
$string['todo'] = 'To do';
$string['view'] = 'View learning plan by competency';
$string['view_course'] = 'View course: {$a}';
$string['view_courses'] = 'View Courses';
$string['view_section'] = 'View section: {$a}';
$string['yes'] = 'Yes';
