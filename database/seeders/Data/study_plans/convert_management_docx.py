#!/usr/bin/env python3
"""One-time converter: Faculty of Management Sciences approved study plan .docx -> study plan JSON.

Usage: python -I convert_management_docx.py <plan.docx> <out.json> <report.md>

Source: "الخطة الدراسة المعتمدة.doc" (saved as .docx). The requirement tables (sections 2-4) are
canonical for course data and prerequisites; the level tables (section 5) give term placement;
the course descriptions (sections 6-7) fill descriptionAr. Anything that is corrected or cannot
be resolved is written to the report rather than guessed silently.
"""
import difflib
import json
import re
import sys
import zipfile
from collections import OrderedDict
from xml.etree import ElementTree as ET

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
CODE_RE = re.compile(r'\b((?:REQU|UR)\d{3}|AS[A-Z]{2,3}\.?\d+\.\d+)\b')
ARABIC_RE = re.compile(r'[؀-ۿ]')
LATIN_RE = re.compile(r'[A-Za-z]')

TOTAL_CREDITS = 134
FACULTY_CREDITS = 57

# (id, Arabic name, English name), in document order.
DEPARTMENTS = [
    ('management', 'إدارة الأعمال', 'Business Administration'),
    ('hr', 'إدارة الموارد البشرية', 'Human Resources Management'),
    ('marketing', 'التسويق', 'Marketing'),
    ('accounting', 'المحاسبة', 'Accounting'),
    ('finance', 'التمويل والمصارف', 'Finance and Banking'),
    ('mis', 'نظم المعلومات الإدارية', 'Management Information Systems'),
]

# Requirement tables (1-based, document order) -> (scope, type, required).
# scope: 'common' or a department index.
REQUIREMENT_TABLES = {
    4: ('common', 'university', True),
    5: ('common', 'university', False),
    6: ('common', 'faculty', True),
    7: ('common', 'faculty', False),
}
for _i in range(6):
    REQUIREMENT_TABLES[8 + 2 * _i] = (_i, 'specialization', True)
    REQUIREMENT_TABLES[9 + 2 * _i] = (_i, 'specialization', False)

# Level tables: 20-23 are the shared levels 1-4, then four tables (levels 5-8) per department.
LEVEL_TABLES = {20: ('common', 1), 21: ('common', 2), 22: ('common', 3), 23: ('common', 4)}
for _i in range(6):
    for _level in range(4):
        LEVEL_TABLES[24 + 4 * _i + _level] = (_i, 5 + _level)

# Code typos in the source that are certain from the level tables or the code scheme.
CODE_FIXES = {
    'ASCR.7.07': 'ASCR.2.07',   # Business Law: level 1-2 tables and every prerequisite use ASCR.2.07
    'ASAAC.5.03': 'ASAC.5.03',  # Governmental accounting: department prefix is ASAC
    'ASAM.5.02': 'ASMA.5.02',   # Public Relations: department prefix is ASMA
    'ASIS6.21': 'ASIS.6.21',
    'ASCR.2.081': 'ASCR.2.08',
    'ASCR.3.112': 'ASCR.3.11',
}

# English spelling fixes for titles shown on the public site. Every applied fix is reported.
TITLE_FIXES = {
    'lanuage': 'Language', 'languge': 'Language', 'Scientafic': 'Scientific', 'Scince': 'Science',
    'Managment': 'Management', 'Contemporay': 'Contemporary', 'Contmporary': 'Contemporary',
    'Mangement': 'Management', 'Intenship': 'Internship', 'Quantitive': 'Quantitative',
    'Seles': 'Sales', 'Aduting': 'Auditing', 'Accountig': 'Accounting', 'Petrolume': 'Petroleum',
    'Finacial': 'Financial', 'Finanacial': 'Financial', 'Finanace': 'Finance', 'Systemes': 'Systems',
    'Algorrithm': 'Algorithms', 'Advenced': 'Advanced', 'Desgion': 'Decision', 'Maony': 'Money',
    'Managements': 'Management', 'Toureism': 'Tourism', 'Supplay': 'Supply', 'Marketig': 'Marketing',
}

report = []


def note(message):
    report.append(message)


def text_of(element):
    return ''.join(t.text or '' for t in element.iter(W + 't'))


def cell_text(tc):
    paras = [text_of(p).strip() for p in tc.iter(W + 'p')]
    return ' / '.join(x for x in paras if x)


def clean(value):
    return re.sub(r'\s+', ' ', value).strip(' /،,:')


def norm_ar(value):
    value = re.sub(r'[ً-ْ]', '', value)
    value = value.translate(str.maketrans({'أ': 'ا', 'إ': 'ا', 'آ': 'ا', 'ة': 'ه', 'ى': 'ي'}))
    value = re.sub(r'\b(ال)', '', value)
    value = re.sub(r'[^؀-ۿ0-9]', '', value)
    return value.replace('ال', '')


def fix_code(code, context):
    fixed = CODE_FIXES.get(code, code)
    if fixed != code:
        note(f'Code {code} read as {fixed} ({context}).')
    return fixed


SMALL_WORDS = ('in', 'and', 'of', 'for', 'the', 'to', '&')


def fix_title(title, code):
    fixed = re.sub(r'[A-Za-z]+', lambda m: TITLE_FIXES.get(m.group(), m.group()), title)
    fixed = re.sub(r'\s*&\s*', ' & ', fixed)
    fixed = re.sub(r'\s*\(\s*(\d)\s*\)', r' (\1)', fixed)
    fixed = re.sub(r'([A-Za-z])\s*(\d)$', r'\1 (\2)', fixed)
    fixed = ' '.join(
        w if w.isupper() else w.lower() if i and w.lower() in SMALL_WORDS else w[:1].upper() + w[1:]
        for i, w in enumerate(fixed.split(' '))
    )
    if fixed != title:
        note(f'{code}: English title "{title}" written as "{fixed}".')
    return fixed


def number(value):
    match = re.search(r'\d+', value or '')
    return int(match.group()) if match else 0


def load_body(path):
    root = ET.fromstring(zipfile.ZipFile(path).read('word/document.xml'))
    return root.find(W + 'body')


def parse(path):
    body = load_body(path)
    tables = []
    paragraphs = []
    for element in body:
        if element.tag == W + 'tbl':
            rows = [[cell_text(tc) for tc in tr.findall(W + 'tc')] for tr in element.findall(W + 'tr')]
            tables.append(rows)
        elif element.tag == W + 'p':
            value = clean(text_of(element))
            if value:
                paragraphs.append(value)
    return tables, paragraphs


def prereq_side(rows):
    for row in rows[:3]:
        if not row:
            continue
        first, last = row[0], row[-1]
        if re.search(r'المتطلب السابق|Prerequisit|شرط', first):
            return 'first'
        if re.search(r'المتطلب السابق|Prerequisit|شرط', last):
            return 'last'
    raise ValueError('No prerequisite column found')


def requirement_rows(table_no, rows):
    side = prereq_side(rows)
    for row in rows:
        code_index = next((i for i, c in enumerate(row) if CODE_RE.fullmatch(c.strip().split(' ')[0] if c.strip() else '')), None)
        if code_index is None:
            continue
        code = fix_code(row[code_index].strip(), f'table {table_no}')
        prereq = row[0] if side == 'first' else row[-1]
        if side == 'first':
            numbers = row[1:code_index]
            ch, theory, practical = number(numbers[0]), number(numbers[-1]), number(numbers[-2]) if len(numbers) >= 3 else 0
            names = row[code_index + 1:]
        else:
            numbers = row[code_index + 1:-1]
            ch, theory, practical = number(numbers[-1]), number(numbers[0]), number(numbers[1]) if len(numbers) >= 3 else 0
            names = row[:code_index]
        ar, en = [], []
        for cell in names:
            for part in cell.split(' / '):
                part = clean(part)
                if not part:
                    continue
                (ar if ARABIC_RE.search(part) else en if LATIN_RE.search(part) else []).append(part)
        if len(ar) > 1:
            note(f'{code}: Arabic name cell has extra text {ar[1:]!r}; kept "{ar[0]}".')
        yield {
            'code': code,
            'titleAr': clean(ar[0]) if ar else '',
            # A mixed Arabic/English cell ("مهارات التواصل / Games Theory") carries stray text; the
            # dedicated English cell comes first, so keep only it then.
            'titleEn': clean(en[0] if len(en) > 1 and any(ARABIC_RE.search(c) and LATIN_RE.search(c) for c in names) else ' '.join(en)),
            'credits': ch,
            'hours': {'theory': theory, 'practical': practical},
            'prereqText': prereq,
        }


def level_codes(rows):
    """Only the code column: prerequisite cells name codes of other levels."""
    for row in rows:
        for cell in row:
            if CODE_RE.fullmatch(cell.strip()):
                yield CODE_FIXES.get(cell.strip(), cell.strip())
                break


def descriptions(paragraphs):
    """Section 6-7: 'Name (N ساعة معتمدة)' headings followed by description paragraphs."""
    start = next((i for i, p in enumerate(paragraphs) if p.startswith('توصيف مقررات')), None)
    if start is None:
        return {}
    scope = 'common'
    found = {}
    current = None
    heading = re.compile(r'^(.+?)\s*[:(]\s*\(?\s*\d+\s*ساع')
    section = re.compile(r'^7\s*-\s*(\d)\s*-')
    for p in paragraphs[start + 1:]:
        sec = section.match(p)
        if sec:
            scope = int(sec.group(1)) - 1
            current = None
            continue
        if re.match(r'^\d+\s*-', p):
            current = None
            continue
        match = heading.match(p)
        if match:
            # "Name (3 ساعات معتمدة):" alone, or with the description on the same line.
            inline = re.split(r'معتمد[ةه]?\s*\)?\s*:?\s*\)?', p, maxsplit=1)
            rest = clean(inline[1]) if len(inline) == 2 else ''
            if len(p) < 90 or len(rest) > 40:
                current = (scope, clean(match.group(1)))
                found.setdefault(current, [])
                if rest:
                    found[current].append(rest)
                continue
        if current is not None:
            found[current].append(p)
    return {key: ' '.join(value) for key, value in found.items() if value}


def main(src, out_path, report_path):
    tables, paragraphs = parse(src)
    numbered = {i + 1: t for i, t in enumerate(tables)}

    courses = OrderedDict()       # code -> course
    scope_of = {}                 # code -> 'common' | dept index
    for table_no, (scope, ctype, required) in REQUIREMENT_TABLES.items():
        for row in requirement_rows(table_no, numbered[table_no]):
            code = row['code']
            if code in courses:
                note(f'{code} appears twice in the requirement tables (table {table_no}); first one kept.')
                continue
            row.update({'type': ctype, 'required': required})
            courses[code] = row
            scope_of[code] = scope

    for code, course in courses.items():
        course['titleEnFixed'] = fix_title(course['titleEn'], code)

    terms = {}
    for table_no, (scope, level) in LEVEL_TABLES.items():
        for code in level_codes(numbered[table_no]):
            if code not in courses:
                note(f'Level table {table_no} lists {code}, which is not in any requirement table. Ignored.')
                continue
            if scope_of[code] not in ('common', scope):
                continue
            previous = terms.get(code)
            if previous is not None and previous != level:
                note(f'{code} is placed in level {previous} and level {level}; using {previous}.')
                continue
            terms[code] = level

    def available(dept):
        return [c for c in courses if scope_of[c] in ('common', dept)]

    def code_level(code):
        match = re.match(r'AS[A-Z]+\.(\d)\.', code)
        return int(match.group(1)) if match else None

    def resolve_prereqs(code, dept, placed):
        text = courses[code]['prereqText']
        pool = available(dept)
        prereqs, credits = [], 0
        if re.search(r'90\s*%', text):
            credits = -(-FACULTY_CREDITS * 9 // 10)
            note(f'{code}: "{clean(text)}" shown as "Completed {credits} credits" (90% of the {FACULTY_CREDITS} faculty credits).')
            return prereqs, credits
        if re.search(r'80\s*%', text):
            credits = -(-TOTAL_CREDITS * 8 // 10)
            note(f'{code}: "{clean(text)}" shown as "Completed {credits} credits" (80% of {TOTAL_CREDITS}).')
            return prereqs, credits
        parts = [clean(p) for p in re.split(r'\s*/\s*|،', text) if clean(p)]
        codes = [fix_code(c, f'prerequisite of {code}') for c in CODE_RE.findall(text)]
        names = [p for p in parts if ARABIC_RE.search(p) and not re.search(r'لا\s*يوجد|^-$', p)]
        by_name = []
        for name in names:
            stripped = clean(CODE_RE.sub('', name))
            if not stripped:
                continue
            target = norm_ar(stripped)
            scored = sorted(((difflib.SequenceMatcher(None, target, norm_ar(courses[c]['titleAr'])).ratio(), c) for c in pool if c != code), reverse=True)
            if scored and scored[0][0] >= 0.75:
                by_name.append(scored[0][1])
        chosen = []
        for i, c in enumerate(codes):
            name_match = by_name[i] if i < len(by_name) else None
            if c in pool and c != code and (name_match is None or name_match == c):
                chosen.append(c)
            elif name_match is not None:
                note(f'{code}: prerequisite "{clean(text)}" names {name_match} "{courses[name_match]["titleAr"]}" but gives code {c}; using the named course.')
                chosen.append(name_match)
            else:
                note(f'{code}: prerequisite code {c} is not a course of this plan ({DEPARTMENTS[dept][0]}). Dropped.')
        if not codes:
            chosen.extend(by_name)
            if names and not by_name:
                note(f'{code}: prerequisite "{clean(text)}" matches no course. Dropped.')
        for c in dict.fromkeys(chosen):
            if placed.get(c, 0) >= placed.get(code, 99):
                note(f'{code} ({DEPARTMENTS[dept][0]}): prerequisite {c} is not in an earlier term. Dropped from the graph.')
                continue
            prereqs.append(c.lower().replace('.', '-'))
        return prereqs, credits

    desc = descriptions(paragraphs)
    used_desc = set()

    def words(value):
        return {re.sub(r'^و', '', norm_ar(w)) for w in value.split()} - {'', 'في', 'و', 'مشروع'}

    def description_for(code, dept):
        title = courses[code]['titleAr']
        # Own section first, then the faculty section: faculty electives such as Business English
        # are offered again as department electives.
        own = ['common'] if scope_of[code] == 'common' else [dept]
        for scopes in (own, ['common']):
            best = (0, None)
            for key in desc:
                if key[0] not in scopes:
                    continue
                ratio = difflib.SequenceMatcher(None, norm_ar(title), norm_ar(key[1])).ratio()
                a, b = words(title), words(key[1])
                # Same words in another order, or one title contained in the other
                # ("مشروع التدريب المهني" / "التدريب المهني في التسويق").
                if a == b or (min(len(a), len(b)) >= 2 and (a <= b or b <= a)):
                    ratio = max(ratio, 0.9)
                if ratio > best[0]:
                    best = (ratio, key)
            if best[0] >= 0.8:
                used_desc.add(best[1])
                return desc[best[1]]
        return ''

    uni_elective_slots = [2, 5, 6]
    departments = []
    for dept, (dept_id, name_ar, name_en) in enumerate(DEPARTMENTS):
        codes = available(dept)
        placed = {}
        for code in codes:
            if code in terms:
                placed[code] = terms[code]
        for code in codes:
            if code in placed:
                continue
            level = code_level(code)
            if level is None and courses[code]['type'] == 'university':
                prereq_terms = [placed.get(c, 1) for c in CODE_RE.findall(courses[code]['prereqText'])]
                level = next(s for s in uni_elective_slots if s > max(prereq_terms or [0]))
            placed[code] = level or 1
            if dept == 0 or scope_of[code] == dept:
                note(f'{dept_id}: {code} "{courses[code]["titleEnFixed"]}" has no level in the level tables; placed in term {placed[code]} '
                     + ('(from its code).' if code_level(code) else '(first university-elective slot after its prerequisites).'))
        by_term = OrderedDict((t, []) for t in range(1, 9))
        for code in codes:
            course = courses[code]
            level_from_code = code_level(code)
            if level_from_code and code in terms and level_from_code != terms[code] and (dept == 0 or scope_of[code] == dept):
                note(f'{dept_id}: {code} is in level {terms[code]} of the level tables but its code says level {level_from_code}. Using the level tables.')
            prereqs, prereq_credits = resolve_prereqs(code, dept, placed)
            by_term[placed[code]].append({
                'id': code.lower().replace('.', '-'),
                'code': code,
                'titleEn': course['titleEnFixed'],
                'titleAr': course['titleAr'],
                'term': placed[code],
                'type': course['type'],
                'required': course['required'],
                'forced': False,
                'credits': course['credits'],
                'prerequisites': prereqs,
                'prerequisiteCredits': prereq_credits,
                'instructor': None,
                'hours': course['hours'],
                'descriptionEn': '',
                'descriptionAr': description_for(code, dept),
                'lessons': [],
            })
        required_credits = sum(c['credits'] for t in by_term.values() for c in t if c['required'])
        note(f'INFO {dept_id}: {sum(len(t) for t in by_term.values())} courses, {required_credits} credits from required courses '
             f'(plan total {TOTAL_CREDITS} with electives: 4 university, 6 faculty, 6 department).')
        departments.append({
            'id': dept_id,
            'nameEn': name_en,
            'nameAr': name_ar,
            'summaryEn': '',
            'summaryAr': '',
            'totalCredits': TOTAL_CREDITS,
            'terms': [{'id': t, 'labelEn': str(t), 'labelAr': str(t), 'courses': cs} for t, cs in by_term.items() if cs],
        })

    for key in desc:
        if key not in used_desc:
            note(f'Description "{key[1]}" ({"faculty" if key[0] == "common" else DEPARTMENTS[key[0]][0]}) matches no course. Not used.')
    missing = [c for d in departments for t in d['terms'] for c in t['courses'] if not c['descriptionAr']]
    if missing:
        note('INFO no Arabic description found for: ' + ', '.join(sorted({c['code'] for c in missing})) + '.')

    with open(out_path, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump({'source': 'الخطة الدراسة المعتمدة.doc (Faculty of Management Sciences, approved plan)', 'departments': departments},
                  handle, ensure_ascii=False, indent=2)
        handle.write('\n')
    with open(report_path, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write('# Conversion report\n\n')
        for line in dict.fromkeys(report):
            handle.write(f'- {line}\n')


if __name__ == '__main__':
    if len(sys.argv) != 4:
        sys.exit(__doc__)
    main(*sys.argv[1:])
