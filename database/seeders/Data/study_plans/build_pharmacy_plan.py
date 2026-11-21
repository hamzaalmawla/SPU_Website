#!/usr/bin/env python3
"""Builds the Pharmacy study plan JSON.

Usage: python -I build_pharmacy_plan.py <out.json> <report.md>

Source: the Faculty of Pharmacy internal regulations PDF (2012), section "الخطة الدراسية
حسب المستويات". The PDF's Arabic text layer is damaged, so the table below was transcribed
by hand from the rendered pages. Prerequisites in the PDF are written as course NAMES; they
were mapped to course codes by hand and the mapping is listed in the report where it needed
a judgement call.
"""
import json
import re
import sys
from collections import OrderedDict

U, F = 'university', 'faculty'

# (level, code, English title, Arabic title, credits, theory, practical, prerequisite codes)
ROWS = [
    (1, 'REQU101', 'Arabic Language', 'اللغة العربية', 2, 2, 0, []),
    (1, 'REQU105', 'English Language 1', 'اللغة الإنكليزية 1', 2, 2, 0, []),
    (1, 'PHCC103', 'General and Inorganic Chemistry', 'كيمياء عامة ولا عضوية', 3, 2, 2, []),
    (1, 'PHBM104', 'Biology - Zoology', 'بيولوجيا حيوانية', 3, 2, 2, []),
    (1, 'PHSS105', 'General Physics', 'فيزياء عامة', 3, 2, 2, []),
    (1, 'PHSS106', 'Mathematics', 'رياضيات', 2, 2, 0, []),
    (1, 'PHPT107', 'Pharmaceutical Calculation', 'حسابات صيدلانية', 2, 2, 0, []),
    (2, 'REQU207', 'English Language 2', 'اللغة الإنكليزية 2', 2, 2, 0, ['REQU105']),
    (2, 'PHPC201', 'Biology - Botany', 'بيولوجيا نباتية', 3, 2, 2, []),
    (2, 'PHSS202', 'Biostatistics', 'إحصاء حيوي', 2, 2, 0, ['PHSS106']),
    (2, 'PHSS203', 'Histology and Anatomy', 'تشريح ونسج', 3, 2, 2, ['PHBM104']),
    (2, 'PHCC204', 'Pharmaceutical Organic Chemistry 1', 'كيمياء عضوية صيدلانية 1', 3, 2, 2, ['PHCC103']),
    (2, 'REQU103', 'Computer Skills', 'مهارات الحاسوب', 3, 2, 2, []),
    (2, 'ELEC-U1', 'Elective University Requirement (1)', 'متطلب جامعة اختياري (1)', 2, 2, 0, [], U),
    (3, 'PHPT301', 'Introduction to Pharmacy', 'مدخل إلى الصيدلة', 2, 2, 0, []),
    (3, 'PHCC302', 'Pharmaceutical Organic Chemistry 2', 'كيمياء عضوية صيدلانية 2', 4, 3, 2, ['PHCC204']),
    (3, 'PHAC304', 'Pharmaceutical Analytical Chemistry 1', 'كيمياء تحليلية صيدلانية 1', 4, 3, 3, ['PHCC103']),
    (3, 'PHAC305', 'Physical Pharmacy', 'صيدلة فيزيائية', 3, 2, 2, ['PHSS105']),
    (3, 'ELEC-U2', 'Elective University Requirement (2)', 'متطلب جامعة اختياري (2)', 2, 2, 0, ['REQU207'], U),
    (3, 'PHPP306', 'Physiology and Pathological Physiology', 'فيزيولوجيا وفيزيولوجيا مرضية', 2, 2, 0, ['PHBM104', 'PHSS203']),
    (4, 'PHAC401', 'Pharmaceutical Analytical Chemistry 2', 'كيمياء تحليلية صيدلانية 2', 3, 2, 3, ['PHAC304']),
    (4, 'PHBM402', 'General Biochemistry 1', 'كيمياء حيوية عامة 1', 3, 2, 2, ['PHCC302']),
    (4, 'PHPT403', 'Pharmaceutics 1', 'صيدلانيات 1', 3, 2, 2, ['PHPT301']),
    (4, 'PHBM404', 'Molecular Biology', 'بيولوجيا جزيئية', 3, 2, 2, ['PHBM104']),
    (4, 'PHPC405', 'Pharmacognosy 1', 'عقاقير 1', 3, 2, 2, ['PHPC201']),
    (4, 'PHSS406', 'English Pharmaceutical Terminology', 'مصطلحات صيدلانية باللغة الإنكليزية', 2, 2, 0, ['ELEC-U2']),
    (4, 'PHSS407', 'Internship 1', 'ملازمة 1', 1, 1, 0, []),
    (5, 'PHPP501', 'Pharmacology 1', 'علم الأدوية 1', 2, 2, 0, ['PHPP306']),
    (5, 'PHBM502', 'General Biochemistry 2', 'كيمياء حيوية عامة 2', 3, 2, 2, ['PHBM402']),
    (5, 'PHPT503', 'Pharmaceutics 2', 'صيدلانيات 2', 3, 2, 2, ['PHPT403']),
    (5, 'PHPC504', 'Pharmacognosy 2', 'عقاقير 2', 3, 2, 2, ['PHPC405']),
    (5, 'PHSS505', 'Pathology', 'علم الأمراض', 2, 2, 0, ['PHPP306']),
    (5, 'PHAC506', 'Instrumental Analysis', 'تحليل آلي', 3, 2, 2, ['PHAC401']),
    (5, 'ELEC-F1', 'Elective Faculty Requirement (1)', 'متطلب كلية اختياري (1)', 2, 2, 0, [], F),
    (6, 'PHCC601', 'Pharmaceutical Chemistry 1', 'كيمياء صيدلية 1', 4, 3, 2, ['PHCC302', 'PHPP501']),
    (6, 'PHPP602', 'Pharmacology 2', 'علم الأدوية 2', 4, 3, 2, ['PHPP501']),
    (6, 'PHPC603', 'Phytochemistry', 'كيمياء العقاقير', 3, 2, 3, ['PHAC506', 'PHPC504']),
    (6, 'PHPT604', 'Pharmaceutical Technology', 'تكنولوجيا صيدلانية', 4, 3, 2, ['PHPT503']),
    (6, 'PHBM605', 'Parasitology and Mycology', 'طفيليات وفطريات', 3, 2, 2, ['PHBM104', 'PHPC201']),
    (6, 'ELEC-F2', 'Elective Faculty Requirement (2)', 'متطلب كلية اختياري (2)', 2, 2, 0, [], F),
    (7, 'PHCC701', 'Pharmaceutical Chemistry 2', 'كيمياء صيدلية 2', 4, 3, 2, ['PHCC601']),
    (7, 'PHPT702', 'Industrial Pharmacy', 'صيدلة صناعية', 3, 2, 3, ['PHPT604']),
    (7, 'PHBM703', 'Bacteriology and Virology', 'جراثيم وفيروسات', 4, 3, 2, ['PHSS505']),
    (7, 'PHPP704', 'Toxicology', 'علم السموم', 3, 2, 2, ['PHPP602']),
    (7, 'PHSS705', 'Biotechnology in Pharmacy', 'تقانة حيوية في الصيدلة', 2, 2, 0, ['PHBM404']),
    (7, 'PHSS706', 'English Scientific Composition', 'الإنشاء العلمي باللغة الإنكليزية', 2, 2, 0, ['PHSS406']),
    (8, 'PHPP801', 'Molecular Pharmacology', 'علم الأدوية الجزيئي', 2, 2, 0, ['PHPP602']),
    (8, 'PHPT802', 'Biopharmacy and Pharmacokinetics', 'صيدلة حيوية وحرائك الدواء', 3, 2, 2, ['PHPT702']),
    (8, 'PHAC803', 'Bromatology', 'كيمياء غذائية', 3, 2, 2, ['PHAC401']),
    (8, 'PHBM804', 'Clinical Biochemistry', 'كيمياء حيوية سريرية', 3, 2, 2, ['PHBM502']),
    (8, 'PHPP805', 'Applied and Forensic Toxicology', 'سموم تطبيقية وشرعية', 3, 2, 2, ['PHPP704']),
    (8, 'ELEC-F3', 'Elective Faculty Requirement (3)', 'متطلب كلية اختياري (3)', 2, 2, 0, [], F),
    (8, 'PHSS807', 'Internship 2', 'ملازمة 2', 1, 1, 0, []),
    (9, 'PHCC901', 'Quality Control of Medicaments', 'مراقبة الأدوية', 3, 2, 2, ['PHCC701', 'PHAC506']),
    (9, 'PHCC902', 'Medicinal Chemistry - Drug Design', 'كيمياء دوائية: تصميم الدواء', 2, 2, 0, ['PHCC701']),
    (9, 'PHCC903', 'Radiopharmaceuticals', 'مواد صيدلانية مشعة', 2, 2, 0, ['PHCC701']),
    (9, 'PHSS904', 'Research Project', 'مشروع التخرج', 2, 2, 0, []),
    (9, 'PHCC905', 'Drug Synthesis', 'تخليق دوائي', 3, 2, 2, ['PHCC701']),
    (9, 'ELEC-F4', 'Elective Faculty Requirement (4)', 'متطلب كلية اختياري (4)', 2, 2, 0, [], F),
    (9, 'PHSS906', 'Microbial Pharmacy', 'صيدلة جرثومية', 2, 1, 2, ['PHBM703', 'PHPT702']),
    (10, 'PHBM001', 'Hematology and Immunology', 'دمويات ومناعيات', 4, 3, 2, ['PHSS505']),
    (10, 'PHSS002', 'Pharmaceutical Promotion, Marketing and Pharmacoeconomics', 'ترويج وتسويق واقتصاد صيدلاني', 2, 2, 0, []),
    (10, 'PHSS003', 'History, Ethics and Legislation', 'تاريخ وأخلاقيات وتشريعات الصيدلة', 2, 2, 0, []),
    (10, 'PHSS004', 'Communication Skills', 'مهارات تواصل', 2, 2, 0, []),
    (10, 'PHSS005', 'First Aid', 'إسعاف أولي', 2, 2, 0, ['PHSS505']),
    (10, 'PHPT006', 'Clinical and Hospital Pharmacy', 'صيدلة سريرية وصيدلة مستشفيات', 3, 2, 2, ['PHPT802']),
    (10, 'ELEC-U3', 'Elective University Requirement (3)', 'متطلب جامعة اختياري (3)', 2, 2, 0, [], U),
    (10, 'ELEC-F5', 'Elective Faculty Requirement (5)', 'متطلب كلية اختياري (5)', 2, 2, 0, [], F),
]

STATED_LEVEL_CREDITS = {1: 17, 2: 18, 3: 17, 4: 18, 5: 18, 6: 20, 7: 18, 8: 17, 9: 16, 10: 19}
STATED_TOTAL = 178


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def main(out_json, out_report):
    issues = []
    codes = {row[1] for row in ROWS}
    by_level, per_level_credits = {}, {}
    total = 0
    for row in ROWS:
        level, code, en, ar, credits, theory, practical, prereqs = row[:8]
        is_slot = code.startswith('ELEC-')
        ctype = row[8] if len(row) > 8 else (U if code.startswith('REQU') else 'specialization')
        for p in prereqs:
            assert p in codes, (code, p)
        per_level_credits[level] = per_level_credits.get(level, 0) + credits
        total += credits
        by_level.setdefault(level, []).append(OrderedDict([
            ('id', slug(code)), ('code', code), ('titleEn', en), ('titleAr', ar), ('term', level),
            ('type', ctype), ('required', not is_slot), ('forced', False), ('credits', credits),
            ('prerequisites', [slug(p) for p in prereqs]), ('prerequisiteCredits', 0), ('instructor', None),
            ('hours', {'theory': theory, 'practical': practical}),
            ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
        ]))

    for level, stated in STATED_LEVEL_CREDITS.items():
        if per_level_credits.get(level) != stated:
            issues.append('Level %d adds up to %s credits but the PDF says %d. Check the transcription.' % (level, per_level_credits.get(level), stated))
    if total != STATED_TOTAL:
        issues.append('Total is %d credits but the PDF says %d. Check the transcription.' % (total, STATED_TOTAL))

    terms = [OrderedDict([('id', n), ('labelEn', str(n)), ('labelAr', str(n)), ('courses', by_level.get(n, []))])
             for n in range(1, 11)]
    department = OrderedDict([('id', 'pharmacy'), ('nameEn', 'Pharmacy'), ('nameAr', 'الصيدلة'), ('summaryEn', ''),
                              ('summaryAr', ''), ('totalCredits', total), ('terms', terms)])

    issues += [
        'INFO: %d plan rows (%d courses plus %d elective slots), %d credits. Every level total and the 178 grand total match the PDF.' % (
            len(ROWS), len([r for r in ROWS if not r[1].startswith('ELEC-')]), len([r for r in ROWS if r[1].startswith('ELEC-')]), total),
        'The PDF lists 8 unnamed elective rows (3 university, 5 faculty). They are imported as elective placeholders with the labels ELEC-U1..3 and ELEC-F1..5 (invented codes so the editor accepts them). Replace them with the real elective courses when known.',
        'PHSS406 "English Pharmaceutical Terminology" requires "English Language 3", which has no row or code in the plan. The only matching row is the level-3 university elective (2), whose own prerequisite is English Language 2, so the prerequisite was linked to ELEC-U2. Check this.',
        'Prerequisites are written as course names in the PDF. They were mapped to codes by hand; "Pharmacology 1/2", "Pharmaceutics 1/2" and "Pharmacognosy 1/2" refer to the numbered course of that name.',
        'The PDF does not say which courses are faculty requirements versus department requirements, so every PH* course is marked "specialization" and the REQU courses "university". Elective faculty slots are "faculty".',
        'Credits, theory and practical values are copied from the PDF columns as written (credits are not always theory + practical / 2 in this document).',
    ]
    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': 'خطة كلية صيدلة مرمزة.pdf (transcribed)', 'departments': [department]}, f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('%d rows, %d credits, %d notes' % (len(ROWS), total, len(issues)))


if __name__ == '__main__':
    main(*sys.argv[1:3])
