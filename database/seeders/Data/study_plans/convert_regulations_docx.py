#!/usr/bin/env python3
"""One-time converter: faculty internal-regulations .docx -> normalized study plan JSON.

Usage: python -I convert_regulations_docx.py <regulations.docx> <out.json> <report.md>

It reads the requirement tables (canonical for course data and prerequisites) and the
"guidance plan" tables (used for term placement), and writes the structure the study-plan
importer expects. Anything it cannot resolve is written to the report rather than guessed.
"""
import json
import os
import re
import sys
import zipfile
from collections import OrderedDict, defaultdict
from xml.etree import ElementTree as ET

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
CODE_RE = re.compile(r'\b([A-Z]{2,6}[.\-]\d+[.\-]\d+)\b')
CREDIT_RE = re.compile(r'إنجاز\s*(\d+)\s*س')
ORDINALS = {'الأول': 1, 'الثاني': 2, 'الثالث': 3, 'الرابع': 4, 'الخامس': 5, 'السادس': 6,
            'السابع': 7, 'الثامن': 8, 'التاسع': 9, 'العاشر': 10}

DEPARTMENTS = OrderedDict([
    ('se', ('البرمجيات', 'هندسة البرمجيات ونظم المعلومات الذكية', 'Software Engineering and Intelligent Information Systems')),
    ('ai', ('الذكاء الاصطناعي وعلوم', 'هندسة الذكاء الاصطناعي وعلوم البيانات', 'Artificial Intelligence and Data Science Engineering')),
    ('sec', ('أمن', 'هندسة نظم أمن المعلومات الذكية', 'Intelligent Information Systems Security Engineering')),
    ('ctrl', ('الروبوت', 'هندسة الروبوت والنظم الذكية', 'Robotics and Intelligent Systems Engineering')),
    ('comm', ('الاتصالات', 'هندسة الاتصالات الذكية', 'Intelligent Communications Engineering')),
    ('med', ('الطبية', 'الهندسة الطبية الذكية والمعلوماتية الحيوية', 'Smart Biomedical Engineering and Bioinformatics')),
])

# (table number in document order, row index) -> corrected code, for source typos that are
# certain from the surrounding rows. Every entry is reported.
CODE_FIXES = {}


def cell_text(tc):
    paras = [''.join(t.text or '' for t in p.iter(W + 't')).strip() for p in tc.iter(W + 'p')]
    return ' / '.join(x for x in paras if x)


def load_items(path):
    root = ET.fromstring(zipfile.ZipFile(path).read('word/document.xml'))
    items = []
    for el in root.find(W + 'body'):
        if el.tag == W + 'p':
            text = ''.join(t.text or '' for t in el.iter(W + 't')).strip()
            if text:
                items.append(('p', text))
        elif el.tag == W + 'tbl':
            items.append(('t', [[cell_text(tc) for tc in tr.findall(W + 'tc')] for tr in el.findall(W + 'tr')]))
    return items


DIACRITICS = re.compile('[ً-ٰٟـ]')


def normalize(text):
    return DIACRITICS.sub('', text)


def split_title(cell):
    cell = cell.replace(' / ', ' ').strip()
    match = re.search(r'[A-Za-z]', cell)
    if not match:
        return cell, ''
    return cell[:match.start()].strip(' /-'), cell[match.start():].strip(' /')


def num(value):
    value = value.strip()
    return int(value) if value.isdigit() else 0


def parse_prereq(cell):
    credits = CREDIT_RE.search(cell)
    codes = []
    for code in CODE_RE.findall(cell):
        if code not in codes:
            codes.append(code)
    return codes, int(credits.group(1)) if credits else 0, ('أو' in cell or re.search(r'\bor\b', cell, re.I) is not None)


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def level_from_code(code):
    parts = re.split(r'[.\-]', code)
    if parts[0] == 'UR':
        return None
    digit = parts[1]
    return 10 if digit == '0' else int(digit)


def dept_from_text(text):
    for key, (needle, _ar, _en) in DEPARTMENTS.items():
        if needle in text:
            return key
    return None


def main(src, out_json, out_report):
    items = load_items(src)
    issues = []
    catalog = defaultdict(list)
    placement = defaultdict(dict)
    context = {'dept': None, 'kind': None, 'elective': False, 'guidance': False, 'level': None, 'gdept': 'common'}
    table_no = 0

    for kind, value in items:
        if kind == 'p':
            text = normalize(value)
            if 'الخطة الدراسية الاسترشادية' in text or ('الخطة الاسترشادية' in text and 'لقسم' in text):
                context['guidance'] = True
                context['gdept'] = dept_from_text(text) or 'common'
                continue
            if context['guidance']:
                match = re.search(r'المستوى\s+(\S+)', text)
                if match and match.group(1) in ORDINALS:
                    context['level'] = ORDINALS[match.group(1)]
                continue
            if 'متطلبات قسم' in text:
                context['dept'] = dept_from_text(text)
                context['kind'] = 'dept'
            elif text.startswith('متطلبات الجامعة') or ('متطلبات الجامعة' in text and 'ساعة' in text):
                context['dept'] = None
                context['kind'] = 'university'
            elif text.startswith('متطلبات الكلية') or ('متطلبات الكلية' in text and 'ساعة' in text):
                context['dept'] = None
                context['kind'] = 'faculty'
            if 'الاختيارية' in text:
                context['elective'] = True
            elif 'الإلزامية' in text:
                context['elective'] = False
            continue

        table_no += 1
        rows = value
        if not rows or not rows[0] or 'Code' not in ' '.join(rows[0]):
            continue
        table_courses = []
        table_credit_sum = 0
        stated_total = None
        for total_row in rows:
            if total_row and total_row[0].startswith('المجموع'):
                numbers = [num(c) for c in total_row[1:] if c.strip().isdigit()]
                stated_total = numbers[-1] if numbers else None
        for row_index, row in enumerate(rows[2:], start=2):
            if len(row) < 6 or not CODE_RE.fullmatch(row[1].strip()):
                continue
            code = CODE_FIXES.get((table_no, row_index), row[1].strip())
            if context['guidance']:
                if context['level']:
                    placement[context['gdept']][code] = context['level']
                continue
            title_ar, title_en = split_title(row[0])
            table_courses.append(code)
            prereq_codes, prereq_credits, has_or = parse_prereq(row[5])
            if has_or:
                issues.append('Table %d: prerequisite of %s mentions an alternative ("or"): %r. Stored as AND of the listed codes.' % (table_no, code, row[5]))
            table_credit_sum += num(row[4])
            catalog[context['dept'] or context['kind']].append({
                'code': code, 'titleAr': title_ar, 'titleEn': title_en,
                'theory': num(row[2]), 'practical': num(row[3]), 'credits': num(row[4]),
                'prereqCodes': prereq_codes, 'prereqCredits': prereq_credits,
                'type': context['kind'], 'required': not context['elective'], '_table': table_no,
            })

        if not context['guidance'] and stated_total is not None and stated_total != table_credit_sum:
            issues.append('Table %d (%s): the rows add up to %d credits but the table total says %d. Check the source.' % (
                table_no, context['dept'] or context['kind'], table_credit_sum, stated_total))

    for group, courses in catalog.items():
        seen = {}
        for course in courses:
            if course['code'] in seen and seen[course['code']]['titleEn'] != course['titleEn']:
                issues.append('Group "%s": code %s is used by both "%s" and "%s" (table %d). Resolve in the source.' % (
                    group, course['code'], seen[course['code']]['titleEn'], course['titleEn'], course['_table']))
            seen.setdefault(course['code'], course)

    shared = [c for k in ('university', 'faculty') for c in catalog.get(k, [])]
    departments = []
    for dept_id, (_needle, name_ar, name_en) in DEPARTMENTS.items():
        merged = OrderedDict()
        for course in shared + catalog.get(dept_id, []):
            key = course['code']
            if key in merged:
                if merged[key]['titleEn'] == course['titleEn']:
                    continue
                # Same code, different course: keep both so nothing is lost, with a distinct id.
                key = course['code'] + '#2'
                if key in merged:
                    continue
                issues.append('%s: code %s is shared by two different courses ("%s" and "%s"); the second keeps the id suffix -2 and prerequisites that cite the code point at the first.' % (
                    dept_id, course['code'], merged[course['code']]['titleEn'], course['titleEn']))
            merged[key] = dict(course)
            merged[key]['id'] = slug(course['code']) + ('-2' if key.endswith('#2') else '')
        codes = {c['code'] for c in merged.values()}
        by_level = defaultdict(list)
        unplaced = []
        for code, course in [(c['code'], c) for c in merged.values()]:
            guide = placement[dept_id].get(code) or placement['common'].get(code)
            digit = level_from_code(code)
            level = guide or digit
            if guide and digit and guide != digit:
                issues.append('%s: %s is in level %d of the guidance plan but its code says level %d. Using the guidance plan.' % (dept_id, code, guide, digit))
            if level is None:
                prereq_levels = [placement[dept_id].get(p) or placement['common'].get(p) or level_from_code(p) or 1
                                 for p in course['prereqCodes'] if p in codes]
                level = (max(prereq_levels) + 1) if prereq_levels else 1
                unplaced.append('%s "%s" -> term %d' % (code, course['titleEn'], level))
            course['level'] = level
            by_level[level].append(course)

        if unplaced:
            issues.append('%s: %d courses have no term in the guidance plan and were placed in the earliest term their prerequisites allow: %s.' % (
                dept_id, len(unplaced), '; '.join(unplaced)))
        terms, total = [], 0
        max_level = max(by_level) if by_level else 10
        for level in range(1, max(10, max_level) + 1):
            out_courses = []
            for course in by_level.get(level, []):
                prereq_ids = []
                for p in course['prereqCodes']:
                    if p in codes:
                        prereq_ids.append(slug(p))
                    else:
                        issues.append('%s: %s lists prerequisite %s, which is not a course in this department plan. Dropped from the graph.' % (
                            dept_id, course['code'], p))
                if course['required']:
                    total += course['credits']
                out_courses.append(OrderedDict([
                    ('id', course['id']), ('code', course['code']),
                    ('titleEn', course['titleEn']), ('titleAr', course['titleAr']),
                    ('term', level), ('type', 'specialization' if course['type'] == 'dept' else course['type']),
                    ('required', course['required']), ('forced', False), ('credits', course['credits']),
                    ('prerequisites', prereq_ids), ('prerequisiteCredits', course['prereqCredits']),
                    ('instructor', None), ('hours', {'theory': course['theory'], 'practical': course['practical']}),
                    ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
                ]))
            terms.append(OrderedDict([('id', level), ('labelEn', str(level)), ('labelAr', str(level)), ('courses', out_courses)]))

        departments.append(OrderedDict([
            ('id', dept_id), ('nameEn', name_en), ('nameAr', name_ar),
            ('summaryEn', ''), ('summaryAr', ''), ('totalCredits', total), ('terms', terms),
        ]))
        count = sum(len(t['courses']) for t in terms)
        issues.append('INFO %s: %d courses, %d credits from required courses (regulations say 180).' % (dept_id, count, total))

    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': os.path.basename(src), 'departments': departments}, f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('%d departments, %d notes' % (len(departments), len(issues)))


if __name__ == '__main__':
    main(*sys.argv[1:4])
