#!/usr/bin/env python3
"""One-time converter: Building & Construction Engineering regulations .docx -> study plan JSON.

Usage: python -I convert_construction_docx.py <regulations.docx> <out.json> <report.md>

This document has no English titles, and the requirement table identifies prerequisites by
course NAME rather than code. Names are matched after Arabic normalisation; anything matched
only approximately, or not at all, is written to the report. The English titles below are
draft translations for review in the admin panel, not text from the source document.
"""
import difflib
import json
import os
import re
import sys
import zipfile
from collections import OrderedDict
from xml.etree import ElementTree as ET

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
CODE_RE = re.compile(r'\b([A-Z]{2,6}[.\-]\d+(?:[.\-]\d+)*)\b')
CREDIT_RE = re.compile(r'إنجاز\s*(\d+)\s*س')
DIACRITICS = re.compile('[ً-ٰٟـ]')

EN = {
    'الرياضيات الهندسية': 'Engineering Mathematics',
    'الفيزياء الهندسية': 'Engineering Physics',
    'مبادى الرسم الهندسي': 'Principles of Engineering Drawing',
    'رياضيات تطبيقية متقدمة': 'Advanced Applied Mathematics',
    'الكيمياء الهندسية': 'Engineering Chemistry',
    'علم التوازن': 'Statics',
    'الجيولوجيا الهندسية وميكانيك التربة': 'Engineering Geology and Soil Mechanics',
    'التصميم المعماري': 'Architectural Design',
    'خواص ومقاومة المواد': 'Properties and Strength of Materials',
    'تاريخ تكنولوجيا البناء والتشييد': 'History of Building and Construction Technology',
    'هندسة إنشائية -1': 'Structural Engineering 1',
    'مدخل إلى الخوارزميات والبرمجة': 'Introduction to Algorithms and Programming',
    'المواد الحديثة بتشييد المباني': 'Modern Materials in Building Construction',
    'تطبيقات إحصاء في الهندسة': 'Statistics Applications in Engineering',
    'أساليب ومعدات التشييد': 'Construction Methods and Equipment',
    'استخدام تقنيات الاستشعار عن بعد': 'Remote Sensing Techniques',
    'نظم المعلومات الجغرافية GIS': 'Geographic Information Systems (GIS)',
    'هيدروليك وإنشاءات مائية': 'Hydraulics and Hydraulic Structures',
    'التقنيات الحديثة في المساحة': 'Modern Surveying Techniques',
    'تحليل هياكل انشائية': 'Structural Analysis',
    'المواصفات وحساب الكميات': 'Specifications and Quantity Surveying',
    'الرسم الهندسي المدني': 'Civil Engineering Drawing',
    'الخوارزميات وبنى المعطيات': 'Algorithms and Data Structures',
    'تجهيزات المباني 1 - أنظمة الكهرباء والتدفئة والتكييف': 'Building Services 1: Electrical, Heating and Air Conditioning Systems',
    'هندسة إنشائية -2': 'Structural Engineering 2',
    'مدخل إلى الذكاء الاصطناعي': 'Introduction to Artificial Intelligence',
    'مقدمة في نمذجة معلومات البناء BIM': 'Introduction to Building Information Modeling (BIM)',
    'الرسومات التنفيذية': 'Construction Drawings',
    'الاستدامة في البناء والتشييد': 'Sustainability in Building and Construction',
    'تجهيزات المباني 2- أنظمة المياه والصرف الصحي': 'Building Services 2: Water and Sanitary Drainage Systems',
    'القوانين والعقود في البناء والتشييد': 'Laws and Contracts in Building and Construction',
    'تحليل إنشائي باستخدام الحاسوب': 'Computer-Aided Structural Analysis',
    'خرسانة مسلحة -1': 'Reinforced Concrete 1',
    'مدخل إلى تعلم الآلة': 'Introduction to Machine Learning',
    'التصميم باستخدام الحاسوب': 'Computer-Aided Design',
    'نمذجة معلومات البناء 1 BIM': 'Building Information Modeling 1 (BIM)',
    'التكنولوجيا الحديثة بالخرسانة المسلحة': 'Modern Reinforced Concrete Technology',
    'هندسة الطرق والمطارات': 'Highway and Airport Engineering',
    'إدارة مشروعات البناء التشييد': 'Construction Project Management',
    'النقل وهندسة المرور': 'Transportation and Traffic Engineering',
    'السلامة المهنية وإدارة المخاطر في المشاريع الانشائية': 'Occupational Safety and Risk Management in Construction Projects',
    'تطبيقات وأدوات الذكاء الاصطناعي في البناء والتشييد': 'Artificial Intelligence Applications and Tools in Building and Construction',
    'منهجية كتابة الأبحاث الهندسية': 'Engineering Research Writing Methodology',
    'مبادى التخطيط العمراني': 'Principles of Urban Planning',
    'نمذجة معلومات البناء 2 BIM': 'Building Information Modeling 2 (BIM)',
    'مشروع التخرج 1': 'Graduation Project 1',
    'الخرسانة مسبقة الاجهاد': 'Prestressed Concrete',
    'تصميم المنشآت الفولاذية': 'Steel Structures Design',
    'تطبيقات الحاسوب في الهندسة الانشائية': 'Computer Applications in Structural Engineering',
    'صيانة وإصلاح وتدعيم المباني': 'Building Maintenance, Repair and Strengthening',
    'اقتصاديات البناء والتشييد': 'Economics of Building and Construction',
    'إدارة الجودة في التشييد والبناء': 'Quality Management in Construction',
    'آداب واخلاقيات العمل الهندسي': 'Engineering Work Ethics',
    'التدريب العملي الميداني': 'Field Practical Training',
    'مشروع التخرج 2': 'Graduation Project 2',
    'مهارات اللغة العربية': 'Arabic Language Skills',
    'مهارات الحاسوب': 'Computer Skills',
    'اللغة الإنكليزية - 1': 'English Language 1',
    'اللغة الإنكليزية - 2': 'English Language 2',
    'اللغة الإنكليزية - 3': 'English Language 3',
    'مدخل إلى القانون': 'Introduction to Law',
    'علم الاجتماع': 'Sociology',
    'مهارات التفكير والبحث العلمي': 'Scientific Thinking and Research Skills',
    'أساسيات الإدارة ومهاراتها': 'Management Fundamentals and Skills',
    'علم البيئة': 'Environmental Science',
    'لغة أجنبية حية ثانية - فرنسية': 'Second Foreign Language - French',
    'لغة أجنبية حية ثانية - روسية': 'Second Foreign Language - Russian',
    'لغة أجنبية حية ثانية - ألمانية': 'Second Foreign Language - German',
    'لغة أجنبية حية ثانية - إسبانية': 'Second Foreign Language - Spanish',
    'لغة أجنبية حية ثانية - تركية': 'Second Foreign Language - Turkish',
    'المهارات الحركية البدنية والذهنية': 'Physical and Mental Motor Skills',
    'أخلاقيات وسلوكيات المهنة': 'Professional Ethics and Conduct',
}


def norm(text):
    text = DIACRITICS.sub('', text)
    text = text.replace('–', '-').replace('—', '-')
    text = re.sub('[أإآ]', 'ا', text)
    text = text.replace('ى', 'ي').replace('ة', 'ه')
    text = re.sub(r'\s+', '', text)
    text = re.sub(r'-+', '-', text)
    return text.lower()


# The requirement table and the term plan spell a few course names differently. These
# pairs were reviewed by hand; nothing else is matched by similarity.
ALIASES = {
    'مدخل لنمذجة معلومات البناء BIM': 'مقدمة في نمذجة معلومات البناء BIM',
    'مهارات الحاسوب': 'مهارات حاسوب',
    'تحليل هياكل انشائية': 'تحليل هياكل انشائية -1',
}
ALIAS_NORM = {norm(k): norm(v) for k, v in ALIASES.items()}


def canon(name):
    n = norm(name)
    return ALIAS_NORM.get(n, n)


EN['مهارات حاسوب'] = 'Computer Skills'
EN['تحليل هياكل انشائية -1'] = 'Structural Analysis 1'
EN_NORM = {norm(k): v for k, v in EN.items()}


def cell_text(tc):
    paras = [''.join(t.text or '' for t in p.iter(W + 't')).strip() for p in tc.iter(W + 'p')]
    return ' '.join(x for x in paras if x)


def load_tables(path):
    root = ET.fromstring(zipfile.ZipFile(path).read('word/document.xml'))
    return [[[cell_text(tc) for tc in tr.findall(W + 'tc')] for tr in tbl.findall(W + 'tr')]
            for tbl in root.iter(W + 'tbl')]


def num(value):
    value = value.strip()
    return int(value) if value.isdigit() else 0


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def main(src, out_json, out_report):
    tables = load_tables(src)
    issues = []

    plan = []      # guidance plan rows with a code
    slots = []     # placeholder rows without a code
    for table in tables:
        if not table or 'رمز المقرر' not in ' '.join(table[0]) or 'الفصل' not in ' '.join(table[0]):
            continue
        for row in table:
            if len(row) >= 7 and row[0].strip().isdigit() and row[1].strip().isdigit():
                entry = {'year': int(row[0]), 'term': int(row[1]), 'name': row[2], 'code': row[3].strip(),
                         'theory': num(row[4]), 'practical': num(row[5]), 'credits': num(row[6])}
                (plan if entry['code'] else slots).append(entry)

    req_prereq = {}   # normalized name -> (credits, prereq cell)
    for table in tables:
        if table and 'المتطلب السابق' in ' '.join(table[0]) and 'ساعة نظرية' in ' '.join(table[0]):
            for row in table[1:]:
                if len(row) >= 6 and row[0].strip() and not row[0].startswith('المجموع'):
                    req_prereq[canon(row[0])] = (row[0], row[5])

    university = []   # (code, name, theory, practical, credits, prereq cell, required)
    for table in tables:
        header = ' '.join(table[0]) if table else ''
        if 'رمز المقرر' in header and 'المتطلب السابق' in header and 'الفصل' not in header and 'ساعة نظرية' not in header:
            is_required = any(r[1:2] == ['UR-2-01'] for r in table)
            for row in table:
                if len(row) >= 6 and re.fullmatch(r'UR-\d-\d+', row[1].strip()):
                    # layout A: name, code, prereq, theory, practical, credits
                    university.append((row[1].strip(), row[0], num(row[3]), num(row[4]), num(row[5]), row[2], is_required))
                elif len(row) >= 7 and re.fullmatch(r'UR-\d-\d+', row[2].strip()):
                    # layout B: no., name, code, theory, practical, credits, prereq
                    university.append((row[2].strip(), row[1], num(row[3]), num(row[4]), num(row[5]), row[6], is_required))

    # --- code -> course, resolving duplicated codes --------------------------------------
    courses = OrderedDict()
    name_to_code = {}
    for entry in plan:
        key = entry['code']
        course_id = slug(key)
        if key in courses:
            issues.append('Code %s is used for both "%s" and "%s". The second keeps the id suffix -2; prerequisites naming either course use the matching name.' % (
                key, courses[key]['name'], entry['name']))
            key = key + '#2'
            course_id += '-2'
        level = (entry['year'] - 1) * 2 + entry['term']
        courses[key] = {'id': course_id, 'code': entry['code'], 'name': entry['name'], 'level': level,
                        'theory': entry['theory'], 'practical': entry['practical'], 'credits': entry['credits'],
                        'type': 'university' if entry['code'].startswith('UR-') else 'faculty', 'required': True}
        name_to_code[canon(entry['name'])] = key

    elective_levels = {'UR-2-10': 3}
    for code, name, theory, practical, credits, prereq, required in university:
        if code in courses:
            continue
        level = elective_levels.get(code, 1)
        courses[code] = {'id': slug(code), 'code': code, 'name': name, 'level': level, 'theory': theory,
                         'practical': practical, 'credits': credits, 'type': 'university', 'required': required}
        name_to_code[canon(name)] = code
        if not required:
            issues.append('University elective %s "%s" has no term in the plan (it only lists "optional university requirement" slots); placed in term %d. Move it if wrong.' % (code, name, level))
    if slots:
        issues.append('The plan has %d unnamed "optional university requirement" slots (%s credits in total); they are covered by the university elective courses and not added as separate courses.' % (
            len(slots), sum(s['credits'] for s in slots)))

    def resolve_name(name):
        n = canon(name)
        if n in name_to_code:
            return name_to_code[n], None
        close = difflib.get_close_matches(n, list(name_to_code), n=1, cutoff=0.8)
        return None, (courses[name_to_code[close[0]]]['name'] if close else None)

    # --- prerequisites --------------------------------------------------------------------
    prereqs = {}
    for key, course in courses.items():
        cell = ''
        if course['type'] == 'faculty':
            hit = req_prereq.get(canon(course['name']))
            if hit is None:
                issues.append('"%s" (%s) has no row in the requirements table; no prerequisites imported.' % (course['name'], course['code']))
            if hit:
                cell = hit[1]
        else:
            for ucode, _n, _t, _p, _c, prereq, _r in university:
                if ucode == course['code']:
                    cell = prereq
        ids, credits = [], 0
        parts = [p for p in re.split(r'\+', cell.replace('،', '+')) if p.strip()]
        for part in parts:
            part = part.strip()
            credit_match = CREDIT_RE.search(part)
            if credit_match:
                credits = int(credit_match.group(1))
                continue
            if part in ('-', ''):
                continue
            code_match = CODE_RE.search(part)
            if code_match and code_match.group(1) in courses:
                ids.append(courses[code_match.group(1)]['id'])
                continue
            target, suggestion = resolve_name(part)
            if target is None:
                issues.append('%s "%s": prerequisite "%s" matches no course, so it was dropped.%s' % (
                    course['code'], course['name'], part, (' Closest course: "%s" (not applied).' % suggestion) if suggestion else ''))
            else:
                ids.append(courses[target]['id'])
        prereqs[key] = (list(OrderedDict.fromkeys(ids)), credits)

    # --- build ---------------------------------------------------------------------------
    by_level = {}
    for key, course in courses.items():
        by_level.setdefault(course['level'], []).append((key, course))

    terms, total = [], 0
    for level in range(1, 11):
        out = []
        for key, course in by_level.get(level, []):
            title_en = EN_NORM.get(norm(course['name']))
            if title_en is None:
                issues.append('No English title for "%s" (%s); the Arabic title was used for both languages. Add one in the admin panel.' % (course['name'], course['code']))
                title_en = course['name']
            if course['required']:
                total += course['credits']
            ids, credits = prereqs[key]
            out.append(OrderedDict([
                ('id', course['id']), ('code', course['code']), ('titleEn', title_en), ('titleAr', course['name']),
                ('term', level), ('type', course['type']), ('required', course['required']), ('forced', False),
                ('credits', course['credits']), ('prerequisites', ids), ('prerequisiteCredits', credits),
                ('instructor', None), ('hours', {'theory': course['theory'], 'practical': course['practical']}),
                ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
            ]))
        terms.append(OrderedDict([('id', level), ('labelEn', str(level)), ('labelAr', str(level)), ('courses', out)]))

    count = sum(len(t['courses']) for t in terms)
    issues.append('INFO: %d courses, %d credits from required courses plus elective slots (regulations say 180 in total).' % (count, total))
    issues.append('INFO: the English titles are draft translations written for this import, not text from the source document. Review them.')

    department = OrderedDict([('id', 'construction'), ('nameEn', 'Building and Construction Engineering'),
                              ('nameAr', 'هندسة تكنولوجيا البناء والتشييد'), ('summaryEn', ''), ('summaryAr', ''),
                              ('totalCredits', total), ('terms', terms)])
    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': os.path.basename(src), 'departments': [department]}, f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('1 department, %d courses, %d notes' % (count, len(issues)))


if __name__ == '__main__':
    main(*sys.argv[1:4])
