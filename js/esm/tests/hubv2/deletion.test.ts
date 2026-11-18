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
 * Tests for the deletion dialogues' HTML.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {blockedBody, confirmBody} from '../../src/hubv2/deletion';
import type {Labels} from '../../src/hubv2/strings';
import type {DeleteCheck} from '../../src/hubv2/types';

const labels: Labels = {
    hubv2_delete_blocked_badges: 'BADGE.',
    hubv2_delete_blocked_closing_links: 'LINKS.',
    hubv2_delete_blocked_closing_users: 'RECORDS.',
    hubv2_delete_blocked_courses: 'COURSES {$a}.',
    hubv2_delete_blocked_courses_one: 'COURSE 1.',
    hubv2_delete_blocked_courses_some: 'COURSES SOME.',
    hubv2_delete_blocked_intro: 'INTRO {$a}.',
    hubv2_delete_blocked_intro_tree: 'TREE {$a->name} {$a->count}.',
    hubv2_delete_blocked_intro_tree_one: 'TREE1 {$a->name}.',
    hubv2_delete_blocked_plans: 'PLANS {$a}.',
    hubv2_delete_blocked_plans_one: 'PLAN 1.',
    hubv2_delete_blocked_plans_some: 'PLANS SOME.',
    hubv2_delete_blocked_templates: 'TEMPLATES {$a}.',
    hubv2_delete_blocked_templates_one: 'TEMPLATE 1.',
    hubv2_delete_blocked_templates_some: 'TEMPLATES SOME.',
    hubv2_delete_blocked_users: 'USERS {$a}.',
    hubv2_delete_blocked_users_one: 'USER 1.',
    hubv2_delete_blocked_users_some: 'USERS SOME.',
    hubv2_delete_confirm_leaf: 'LEAF {$a}.',
    hubv2_delete_confirm_tree: 'MANY {$a->name} {$a->count} {$a->total}.',
    hubv2_delete_confirm_tree_one: 'ONE {$a->name}.',
    hubv2_delete_parentrule: 'RULE.',
};

const check = (over: Partial<DeleteCheck> = {}): DeleteCheck => ({
    candelete: true, descendants: 0, parenthasrule: false, exact: true, templates: 0, plans: 0, courses: 0, users: 0, badges: false,
    blockedcompetencies: 0, ...over,
});

describe('confirmBody', () => {
    it('words a leaf plainly', () => {
        expect(confirmBody(labels, 'Leaf', check())).toBe('<p>LEAF Leaf.</p>');
    });

    it('tells a leaf too that the parent rule is reset', () => {
        expect(confirmBody(labels, 'Leaf', check({parenthasrule: true}))).toBe('<p>LEAF Leaf.</p><p>RULE.</p>');
    });

    it('counts the children and the whole subtree, in the plural', () => {
        expect(confirmBody(labels, 'Root', check({descendants: 3}))).toBe('<p>MANY Root 3 4.</p>');
    });

    it('uses the singular for one child', () => {
        expect(confirmBody(labels, 'Root', check({descendants: 1}))).toBe('<p>ONE Root.</p>');
    });

    it('adds the rule sentence only when the parent has a rule', () => {
        expect(confirmBody(labels, 'Root', check({descendants: 2, parenthasrule: true}))).toContain('<p>RULE.</p>');
        expect(confirmBody(labels, 'Root', check({descendants: 2}))).not.toContain('RULE.');
    });

    it('escapes the name', () => {
        const html = confirmBody(labels, '<b>&"', check());
        expect(html).toContain('&lt;b&gt;&amp;&quot;');
        expect(html).not.toContain('<b>');
    });
});

describe('blockedBody', () => {
    it('says nothing but the intro when no count is set', () => {
        expect(blockedBody(labels, 'X', check({candelete: false}))).toBe('<p>INTRO X.</p>');
    });

    it('advises on links only when a link blocks, and on records only when a user has one', () => {
        expect(blockedBody(labels, 'X', check({courses: 2}))).toBe('<p>INTRO X.</p><ul><li>COURSES 2.</li></ul><p>LINKS.</p>');
        expect(blockedBody(labels, 'X', check({users: 1}))).toBe('<p>INTRO X.</p><ul><li>USER 1.</li></ul><p>RECORDS.</p>');
        expect(blockedBody(labels, 'X', check({badges: true}))).toContain('<p>LINKS.</p>');
    });

    it('lists one item per reason, in order, singular and plural', () => {
        const html = blockedBody(labels, 'X', check({
            candelete: false, templates: 1, plans: 2, courses: 1, users: 5, badges: true,
        }));
        expect(html).toBe(
            '<p>INTRO X.</p><ul><li>TEMPLATE 1.</li><li>PLANS 2.</li><li>COURSE 1.</li><li>USERS 5.</li>' +
            '<li>BADGE.</li></ul><p>LINKS.</p><p>RECORDS.</p>'
        );
    });

    it('uses the count-less reasons when the counts are not exact, and the counted ones when they are', () => {
        const over = {candelete: false, templates: 1, plans: 1, courses: 1, users: 1};
        expect(blockedBody(labels, 'X', check({...over, exact: false}))).toBe(
            '<p>INTRO X.</p><ul><li>TEMPLATES SOME.</li><li>PLANS SOME.</li><li>COURSES SOME.</li><li>USERS SOME.</li></ul>' +
            '<p>LINKS.</p><p>RECORDS.</p>'
        );
        expect(blockedBody(labels, 'X', check({...over, templates: 3, exact: true}))).toContain('<li>TEMPLATES 3.</li>');
    });

    it('uses the singular for one user', () => {
        expect(blockedBody(labels, 'X', check({users: 1}))).toContain('<li>USER 1.</li>');
    });

    it('names the children in the introduction', () => {
        expect(blockedBody(labels, 'X', check({descendants: 4, plans: 1}))).toContain('<p>TREE X 4.</p>');
        expect(blockedBody(labels, 'X', check({descendants: 1, plans: 1}))).toContain('<p>TREE1 X.</p>');
    });

    it('escapes the name', () => {
        const html = blockedBody(labels, '<b>&"', check({descendants: 2}));
        expect(html).toContain('&lt;b&gt;&amp;&quot;');
        expect(html).not.toContain('<b>');
    });
});
