#!/usr/bin/env python3
"""Builds the Medicine study plan JSON.

Usage: python -I build_medicine_plan.py <out.json> <report.md>

Source: "الخطة الدراسية: المقررات" (Medicine, 5 pages). The PDF lists every course with its
credits, hours and prerequisites but NO semester table, so term placement here is PROVISIONAL:
courses are taken in the order the PDF lists them and filled into terms in credit-balanced
batches, a course only moving later when one of its prerequisites needs an earlier term.
The table was transcribed by hand from the rendered pages (the PDF's Arabic text layer is
damaged); prerequisites written as names were mapped to codes by hand.
"""
import json
import math
import re
import sys
from collections import OrderedDict

U, F, S = 'university', 'faculty', 'specialization'

# (code, English, Arabic, credits, theory, lab, clinical, [prerequisite codes], type, required)
ROWS = [
    # --- University compulsory ---
    ('REQU101', 'English Language (1)', 'اللغة الإنجليزية (1)', 2, 2, 0, 0, [], U, True),
    ('REQU204', 'English Language (2)', 'اللغة الإنجليزية (2)', 2, 2, 0, 0, ['REQU101'], U, True),
    ('REQU103', 'Arabic Language', 'اللغة العربية', 2, 2, 0, 0, [], U, True),
    ('REQU102', 'Computer Skills', 'مهارات الحاسوب', 3, 2, 2, 0, [], U, True),
    # --- University elective (choose 6) ---
    ('REQU305', 'English Language (3)', 'اللغة الإنجليزية (3)', 2, 2, 0, 0, ['REQU204'], U, False),
    ('REQU107', 'Second Foreign Language', 'لغة أجنبية حية (ثانية)', 2, 2, 0, 0, [], U, False),
    ('REQU106', 'Scientific Thinking', 'المنطق والتفكير العلمي', 2, 2, 0, 0, [], U, False),
    ('REQU108', 'Social Science', 'علم اجتماع', 2, 2, 0, 0, [], U, False),
    ('REQU109', 'Introduction to Law', 'مدخل إلى القانون', 2, 2, 0, 0, [], U, False),
    ('REQU110', 'Management Science', 'علم الإدارة', 2, 2, 0, 0, [], U, False),
    ('REQU111', 'Environment Science', 'علم البيئة', 2, 2, 0, 0, [], U, False),
    # --- Faculty compulsory ---
    ('MERA101', 'Medical Physics', 'الفيزياء الطبية', 3, 2, 2, 0, [], F, True),
    ('MEBC101', 'General & Organic Chemistry', 'الكيمياء العامة والعضوية', 4, 3, 2, 0, [], F, True),
    ('MEBI101', 'Biology & Cytology', 'علم الحياة والخلية', 4, 3, 2, 0, [], F, True),
    ('MEAN106', 'Genetics', 'علم الوراثة', 2, 2, 0, 0, [], F, True),
    ('MEED101', 'Research Methodology & Evidence-Based Medicine', 'منهجية البحث والطب المسند بالدليل', 2, 1, 2, 0, [], F, True),
    ('MEED102', 'Communication Skills', 'مهارات التواصل', 2, 2, 0, 0, [], F, True),
    ('MEED103', 'Medicine History & Ethics', 'تاريخ الطب وآدابه', 2, 2, 0, 0, [], F, True),
    ('MEED108', 'Graduation Project', 'رسالة التخرج', 3, 0, 6, 0, [], F, True),
    # --- Faculty elective (choose 4) ---
    ('MEED204', 'Medical Terminology', 'علم المصطلحات الطبية', 2, 2, 0, 0, ['REQU204'], F, False),
    ('MECM104', 'Health Systems', 'النظم الصحية', 2, 2, 0, 0, [], F, False),
    ('MEBC405', 'Genetic Engineering: Principles & Applications', 'الهندسة الوراثية: مبادئ وتطبيقات', 2, 2, 0, 0, ['MEAN106', 'MEBC303'], F, False),
    ('MEED105', 'Behavioral Psychology', 'علم النفس السلوكي', 2, 2, 0, 0, [], F, False),
    ('MEED506', 'Alternative Medicine', 'الطب البديل', 2, 2, 0, 0, ['MEPH404'], F, False),
    ('MEED207', 'Research Skills', 'مهارات بحثية', 2, 1, 2, 0, ['MEED101'], F, False),
    # --- Faculty departments, years 1-5 ---
    ('MEAN105', 'Medical Embryology', 'علم الجنين الطبي', 3, 2, 2, 0, [], S, True),
    ('MEAN101', 'Anatomy (1)', 'التشريح (1)', 4, 3, 2, 0, [], S, True),
    ('MEAN202', 'Anatomy (2)', 'التشريح (2)', 4, 3, 2, 0, ['MEAN101'], S, True),
    ('MEAN303', 'Anatomy (3)', 'التشريح (3)', 4, 3, 2, 0, ['MEAN202', 'MEAN105'], S, True),
    ('MEAN404', 'Anatomy (4)', 'التشريح (4)', 4, 3, 2, 0, ['MEAN303'], S, True),
    ('MEBC202', 'Medical Biochemistry (1)', 'الكيمياء الحيوية الطبية (1)', 5, 4, 2, 0, ['MEBC101'], S, True),
    ('MEBC303', 'Medical Biochemistry (2)', 'الكيمياء الحيوية الطبية (2)', 5, 4, 2, 0, ['MEBC202'], S, True),
    ('MEBC404', 'Clinical Biochemistry', 'الكيمياء السريرية', 2, 2, 0, 0, ['MEBC303'], S, True),
    ('MEPH101', 'Physiology (1)', 'الفيزيولوجيا (1)', 4, 3, 2, 0, [], S, True),
    ('MEPH202', 'Physiology (2)', 'الفيزيولوجيا (2)', 5, 4, 2, 0, ['MEPH101'], S, True),
    ('MEPH303', 'Physiology (3)', 'الفيزيولوجيا (3)', 5, 4, 2, 0, ['MEPH202'], S, True),
    ('MEPH404', 'Pharmacology (1) (General)', 'علم الأدوية (1) (العام)', 4, 3, 2, 0, ['MEPH303', 'MEPA302'], S, True),
    ('MEPH505', 'Pharmacology (2) (Special)', 'علم الأدوية (2) (الخاص)', 4, 3, 2, 0, ['MEPH404'], S, True),
    ('MEPA201', 'Histology (1)', 'علم الأنسجة (1)', 4, 3, 2, 0, ['MEBI101'], S, True),
    ('MEPA302', 'Histology (2)', 'علم الأنسجة (2)', 4, 3, 2, 0, ['MEPA201'], S, True),
    ('MEPA403', 'Pathology (1) (General)', 'علم الأمراض (1) (العام)', 4, 3, 2, 0, ['MEPH303', 'MEPA302'], S, True),
    ('MEPA504', 'Pathology (2) (Special)', 'علم الأمراض (2) (الخاص)', 4, 3, 2, 0, ['MEPA403'], S, True),
    ('MEPA605', 'Forensic Medicine & Toxicology', 'الطب الشرعي وعلم السموم', 3, 2, 2, 0, ['MEPA504'], S, True),
    ('MEBI202', 'Microbiology (1)', 'الأحياء الدقيقة (1)', 4, 3, 2, 0, ['MEBI101'], S, True),
    ('MEBI303', 'Microbiology (Parasitology) (2)', 'الأحياء الدقيقة (2)', 4, 3, 2, 0, ['MEBI202'], S, True),
    ('MEBI304', 'Immunology', 'علم المناعة', 2, 2, 0, 0, ['MEBI202'], S, True),
    ('MECM101', 'Public & Occupational Health', 'الصحة العامة والمهنية', 3, 2, 2, 0, [], S, True),
    ('MECM102', 'Epidemiology & Biostatistics', 'الوبائيات والإحصاء الحيوي', 3, 2, 2, 0, [], S, True),
    ('MECM103', 'Preventive & Family Medicine', 'الطب الوقائي وطب الأسرة', 3, 2, 0, 40, [], S, True),
    ('MEIM501', 'Internal Medicine (1) (Symptoms & Diagnosis)', 'الطب الباطني (1) (الأعراض والتشخيص)', 2, 2, 0, 0, ['MEAN404', 'MEPH303'], S, True),
    ('MEIM602', 'Internal Medicine (2)', 'الطب الباطني (2)', 6, 4, 0, 80, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEIM603', 'Internal Medicine (3)', 'الطب الباطني (3)', 6, 4, 0, 80, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEIM604', 'Internal Medicine (4)', 'الطب الباطني (4)', 6, 4, 0, 80, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEIM605', 'Internal Medicine (5)', 'الطب الباطني (5)', 6, 4, 0, 80, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEIM606', 'Internal Medicine (6)', 'الطب الباطني (6)', 6, 4, 0, 80, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEIM407', 'Psychiatry', 'الطب النفسي', 3, 2, 0, 40, ['MEAN303'], S, True),
    ('MEIM608', 'Dermatology & Venereal Diseases', 'أمراض الجلد والأمراض المنتقلة بالجنس', 3, 2, 0, 40, ['MEPA504'], S, True),
    ('MERA402', 'Radiology & Radiotherapy', 'الأشعة والمعالجة الشعاعية', 3, 2, 0, 40, ['MEAN303'], S, True),
    ('MEPD601', 'Pediatrics (1)', 'أمراض الأطفال (1)', 4.5, 3, 0, 60, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEGO601', 'Obstetrics', 'طب التوليد', 4.5, 3, 0, 60, ['MEIM501', 'MEPH505', 'MEED102', 'MEPA504'], S, True),
    ('MEPD702', 'Pediatrics (2)', 'أمراض الأطفال (2)', 5, 3, 0, 80, ['MEPD601'], S, True),
    ('MEGO702', 'Gynecology', 'أمراض النساء', 4.5, 3, 0, 60, ['MEGO601'], S, True),
    ('MESR501', 'Surgery (1) (Minor Surgery)', 'الجراحة (1) (جراحة صغرى)', 3, 2, 2, 0, ['MEAN404'], S, True),
    ('MESR602', 'Surgery (2)', 'الجراحة (2)', 6, 4, 0, 80, ['MESR501'], S, True),
    ('MESR603', 'Surgery (3)', 'الجراحة (3)', 5, 3, 0, 80, ['MESR501'], S, True),
    ('MESR804', 'Surgery (4)', 'الجراحة (4)', 5, 3, 0, 80, ['MESR501', 'MEPD702'], S, True),
    ('MESR605', 'Surgery (5)', 'الجراحة (5)', 6, 4, 0, 80, ['MESR501'], S, True),
    ('MESR406', 'Ophthalmology', 'أمراض العين وجراحتها', 3, 2, 0, 40, ['MEAN303'], S, True),
    ('MESR407', 'Emergency, Anesthesia & IC', 'الطوارئ والتخدير والإنعاش', 4.5, 3, 0, 60, ['MEPH303'], S, True),
    ('MESR408', 'Otorhinolaryngology', 'أمراض الأذن والأنف والحنجرة', 3, 2, 0, 40, ['MEAN303'], S, True),
]

# Year-6 clinical rotations (24 credits): prerequisite is "all compulsory and elective courses
# of years 1-5 except the graduation project", which is stated in the description, not as 100+ edges.
YEAR6 = [
    ('MEIM809', 'Internal Medicine', 'الطب الباطني', 6, 240),
    ('MESR809', 'Surgery', 'الجراحة', 5, 200),
    ('MEPD803', 'Pediatrics', 'أمراض الأطفال', 4, 160),
    ('MEGO803', 'Gynecology & Obstetrics', 'طب التوليد وأمراض النساء', 4, 160),
    ('MEIM810', 'Dermatology', 'الأمراض الجلدية', 1, 40),
    ('MESR510', 'Ophthalmology', 'أمراض العين وجراحتها', 1, 40),
    ('MECM804', 'Primary Health Care', 'الرعاية الصحية الأولية', 1, 40),
    ('MESR811', 'Otorhinolaryngology', 'أمراض الأنف والأذن والحنجرة وجراحتها', 1, 40),
    ('MESR812', 'Emergency, Anesthesia & Intensive Care', 'طب الطوارئ والتخدير والإنعاش', 1, 40),
]
YEAR6_NOTE_AR = 'يتطلب اجتياز جميع مقررات السنوات الأولى إلى الخامسة، الإلزامية والاختيارية، عدا رسالة التخرج.'
YEAR6_NOTE_EN = 'Requires passing all compulsory and elective courses of years 1 to 5 except the graduation project.'

TERMS_Y15 = 10
STATED = {'compulsory_y15': 22 + 9 + 186, 'year6': 24, 'total': 251}


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def credits_value(value):
    return int(value) if float(value).is_integer() else value


def main(out_json, out_report):
    issues = []
    by_code = {r[0]: r for r in ROWS}
    for r in ROWS:
        for p in r[7]:
            assert p in by_code, (r[0], p)

    # --- provisional term placement for years 1-5 ---------------------------------------------
    total_y15 = sum(r[3] for r in ROWS)
    capacity = math.ceil(total_y15 / TERMS_Y15)
    term_of, load, pointer = {}, {}, 1
    pending = [r[0] for r in ROWS]
    while pending:
        for code in pending:
            row = by_code[code]
            if all(p in term_of for p in row[7]):
                break
        else:
            raise SystemExit('prerequisite cycle among: %s' % pending)
        minimum = 1 + max([term_of[p] for p in by_code[code][7]] or [0])
        term = max(pointer, minimum)
        while load.get(term, 0) + by_code[code][3] > capacity and load.get(term, 0) > 0 and term < TERMS_Y15:
            term += 1
        term_of[code] = term
        load[term] = load.get(term, 0) + by_code[code][3]
        while load.get(pointer, 0) >= capacity and pointer < TERMS_Y15:
            pointer += 1
        pending.remove(code)

    by_term = {}
    for r in ROWS:
        code, en, ar, credits, theory, lab, clinical, prereqs, ctype, required = r
        by_term.setdefault(term_of[code], []).append(OrderedDict([
            ('id', slug(code)), ('code', code), ('titleEn', en), ('titleAr', ar), ('term', term_of[code]),
            ('type', ctype), ('required', required), ('forced', False), ('credits', credits_value(credits)),
            ('prerequisites', [slug(p) for p in prereqs]), ('prerequisiteCredits', 0), ('instructor', None),
            ('hours', {'theory': theory, 'practical': lab if lab else clinical}),
            ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
        ]))

    # --- year 6: two rotation blocks in PDF order ------------------------------------------------
    y6_total = sum(c for _, _, _, c, _ in YEAR6)
    spill = load.get(11, 0)  # years 1-5 courses pushed into term 11 by their prerequisite chain
    running = spill
    for code, en, ar, credits, clinical in YEAR6:
        term = 11 if running < (y6_total + spill) / 2 else 12
        running += credits
        by_term.setdefault(term, []).append(OrderedDict([
            ('id', slug(code)), ('code', code), ('titleEn', en), ('titleAr', ar), ('term', term),
            ('type', S), ('required', True), ('forced', False), ('credits', credits),
            ('prerequisites', []), ('prerequisiteCredits', 0), ('instructor', None),
            ('hours', {'theory': 0, 'practical': clinical}),
            ('descriptionEn', YEAR6_NOTE_EN), ('descriptionAr', YEAR6_NOTE_AR), ('lessons', []),
        ]))

    terms = [OrderedDict([('id', n), ('labelEn', str(n)), ('labelAr', str(n)), ('courses', by_term.get(n, []))])
             for n in range(1, 13)]
    compulsory = sum(r[3] for r in ROWS if r[9])
    total = compulsory + y6_total
    department = OrderedDict([('id', 'medicine'), ('nameEn', 'Medicine'), ('nameAr', 'الطب البشري'), ('summaryEn', ''),
                              ('summaryAr', ''), ('totalCredits', credits_value(total)), ('terms', terms)])

    if sum(r[3] for r in ROWS if r[9] and r[0].startswith('REQU')) != 9:
        issues.append('University compulsory credits do not add up to 9.')
    if sum(r[3] for r in ROWS if r[9] and r[9] and r[8] == F) != 22:
        issues.append('Faculty compulsory credits do not add up to 22.')
    if sum(r[3] for r in ROWS if r[8] == S) != 186:
        issues.append('Department credits for years 1-5 are %s but the PDF says 186. Check the transcription.' % sum(r[3] for r in ROWS if r[8] == S))
    if y6_total != 24:
        issues.append('Year 6 credits are %s but the PDF says 24.' % y6_total)

    issues += [
        'INFO: %d courses in years 1-5 plus %d year-6 rotations. Compulsory credits %s (+10 elective credits to choose = 251, matching the PDF).' % (
            len(ROWS), len(YEAR6), credits_value(total)),
        'PROVISIONAL TERMS: the PDF has no semester table. Years 1-5 courses were taken in the order the PDF lists them and filled into terms 1-10 in batches of about %d credits, moving a course later only when a prerequisite needs it. Year-6 rotations are split over terms 11 and 12. Reorder them in the editor.' % capacity,
        'Surgery (4) (MESR804) ends up in term 11: it needs Pediatrics (2), which the batching placed in term 10. Move it in the editor if you want it earlier.',
        'Elective courses (university 7 listed / 6 credits to choose, faculty 6 listed / 4 credits to choose) are marked "elective" and placed like the rest.',
        'Internal Medicine (2) to (6) share one merged prerequisite cell in the PDF (Internal Medicine 1, Pharmacology 2, Communication Skills, Pathology 2); each of the five was given those four prerequisites. Pediatrics (1) and Obstetrics also share a merged cell and got the same four.',
        'Year-6 rotations require "all compulsory and elective courses of years 1-5 except the graduation project". That is stored as a description line on each rotation, not as individual prerequisites.',
        'Hours: "practical" holds the laboratory hours where a course has them, otherwise the clinical hours (a different unit: 1 credit = 2.5 clinical hours a week for 16 weeks).',
        'The PDF has no per-course type beyond university / faculty / department sections; the department courses are marked "specialization".',
        'Medicine has 12 terms, so the public graph now sizes itself to 12 columns.',
    ]
    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': 'الخطة الدراسية: المقررات (Medicine, transcribed)', 'departments': [department]}, f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('%d + %d courses, %s compulsory credits, capacity %d/term, load %s' % (len(ROWS), len(YEAR6), credits_value(total), capacity, dict(sorted(load.items()))))


if __name__ == '__main__':
    main(*sys.argv[1:3])
