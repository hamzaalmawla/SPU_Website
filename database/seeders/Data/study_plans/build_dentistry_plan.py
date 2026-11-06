#!/usr/bin/env python3
"""Builds the Dentistry study plan JSON.

Usage: python -I build_dentistry_plan.py <out.json> <report.md>

Source: the Faculty of Dentistry study plan PDF approved by decision 176 of 23/2/2014
("خطة درسية جديدة بقرار 176 تاريخ 23-2-2014"). The terms, hours and prerequisites come from
the guidance plan (section 5, pages 17-21); course codes come from the course descriptions
(pages 22-106, one page per course). The PDF's Arabic text layer is damaged, so the tables
were transcribed by hand from the rendered pages. Prerequisites are written as course NAMES
and were mapped to codes by hand.
"""
import json
import re
import sys
from collections import OrderedDict

U, F, S = 'university', 'faculty', 'specialization'

# (term, code, English title, Arabic title, credits, theory, practical, prerequisite codes, type,
#  prerequisite credits, required)
ROWS = [
    # Year 1, term 1
    (1, 'UNRE101', 'Computer Skills', 'مهارات الحاسوب', 3, 2, 2, [], U),
    (1, 'UNRE103', 'Arabic Language', 'لغة عربية', 2, 2, 0, [], U),
    (1, 'UNRE105', 'English Language (1)', 'لغة انكليزية (1)', 2, 2, 0, [], U),
    (1, 'DEBL103', 'Dental Biochemistry (1)', 'كيمياء حيوية لطب الأسنان (1)', 3, 2, 2, [], F),
    (1, 'DEBL104', 'Physiology (1)', 'فيزيولوجيا (1)', 3, 2, 2, [], F),
    (1, 'DEBL105', 'Cell Biology & Immunology', 'علم الخلية والمناعة', 3, 2, 2, [], F),
    (1, 'DEOP107', 'Dental Anatomy (1)', 'تشريح الأسنان (1)', 2, 1, 2, [], S),
    # Year 1, term 2
    (2, 'UNRE207', 'English Language (2)', 'لغة انكليزية (2)', 2, 2, 0, ['UNRE105'], U),
    (2, 'DEBL202', 'Medical Physics', 'الفيزياء الطبية', 3, 2, 2, [], F),
    (2, 'DEBL203', 'Dental Biochemistry (2)', 'كيمياء حيوية لطب الأسنان (2)', 3, 2, 2, ['DEBL103'], F),
    (2, 'DEBL204', 'Physiology (2) (Oral Physiology)', 'فيزيولوجيا (2) (فيزيولوجيا الفم)', 1, 1, 0, ['DEBL104'], F),
    (2, 'DEBL205', 'Human Genetics', 'علم الوراثة الإنساني', 1, 1, 0, [], F),
    (2, 'DEOS206', 'Anatomy (1) (General)', 'تشريح (1) (عام)', 3, 2, 2, [], F),
    (2, 'DEOP207', 'Dental Anatomy (2)', 'تشريح الأسنان (2)', 2, 1, 2, ['DEOP107'], S),
    (2, 'DEOS208', 'Microbiology', 'علم الأحياء الدقيقة', 3, 2, 2, [], F),
    # Year 2, term 1
    (3, 'DEOP301', 'Operative Dentistry (1)', 'مداواة أسنان ترميمية (1)', 4, 2, 4, ['DEOP107'], S),
    (3, 'DERP302', 'Removable Prosthodontics (1)', 'تعويض الأسنان المتحركة (1)', 3, 1, 4, ['DEOP107'], S),
    (3, 'DEOM303', 'Biomedical Statistics', 'علم الإحصاء الحيوي', 1, 1, 0, [], S),
    (3, 'DEOS304', 'General and Minor Surgery', 'جراحة عامة وصغرى', 3, 2, 2, ['DEOS206'], F),
    (3, 'DEHS305', 'General Histology', 'علم النسج العام', 3, 2, 2, ['DEBL105'], S),
    (3, 'DEOS306', 'Anatomy (2) (Head & Neck Anatomy)', 'تشريح (2) (تشريح رأس وعنق)', 3, 2, 2, ['DEOS206'], F),
    (3, 'DEOM307', 'Epidemiology', 'علم الوبائيات', 1, 1, 0, ['UNRE207'], S),
    # Year 2, term 2
    (4, 'DEPE401', 'Infection Control', 'مكافحة العدوى', 1, 1, 0, ['DEOS208'], S),
    (4, 'DEOM402', 'Introduction to Clinical Dentistry', 'مقدمة في طب الأسنان السريري', 2, 1, 2, ['DEOP207'], S),
    (4, 'DEFP403', 'Fixed Prosthodontics (1)', 'تعويض الأسنان الثابتة (1)', 4, 2, 4, ['DEOP301'], S),
    (4, 'DEFP404', 'Occlusion', 'علم الإطباق', 1, 1, 0, ['DEOP207'], S),
    (4, 'DEHS405', 'General Pathology', 'التشريح المرضي العام', 3, 2, 2, ['DEHS305'], S),
    (4, 'DEOP406', 'Dental Materials (1)', 'مواد سنية (1)', 3, 2, 2, ['DEBL202'], S),
    (4, 'DEOS407', 'Pharmacology', 'علم الأدوية', 2, 2, 0, ['DEBL203'], F),
    (4, 'DEOS408', 'Emergencies', 'الإسعافات الأولية', 2, 1, 2, ['DEOS304'], F),
    # Year 3, term 1
    (5, 'DEOS501', 'Internal Medicine', 'أمراض داخلية', 2, 2, 0, ['DEOS304'], F),
    (5, 'DEOM502', 'Dental Public Health and Preventive Dentistry', 'الصحة السنية العامة وطب الأسنان الوقائي', 2, 2, 0, ['DEOM402', 'DEOM307'], S),
    (5, 'DEFP503', 'Fixed Prosthodontics (2)', 'تعويض الأسنان الثابتة (2)', 3, 1, 4, ['DEFP403'], S),
    (5, 'DEPE504', 'Periodontology (1)', 'علم النسج حول السنية (1)', 2, 1, 2, ['DEPE401'], S),
    (5, 'DEHS505', 'Oral Histology', 'علم النسج الخاص بالفم والأسنان', 3, 2, 2, ['DEHS305'], S),
    (5, 'DEOP506', 'Dental Materials (2)', 'مواد سنية (2)', 2, 1, 2, ['DEOP406'], S),
    (5, 'DEPD507', 'Radiology & Imaging in Dentistry (1)', 'علم الأشعة والتصوير في طب الأسنان (1)', 3, 2, 2, ['DEOS306'], S),
    (5, 'DEOP508', 'Ethics and Laws', 'الأخلاقيات والقوانين', 1, 1, 0, [], S, 60),
    # Year 3, term 2
    (6, 'DEOP601', 'Operative Dentistry (2)', 'مداواة أسنان ترميمية (2)', 3, 1, 4, ['DEOP301'], S),
    (6, 'DERP602', 'Removable Prosthodontics (2)', 'تعويض الأسنان المتحركة (2)', 3, 1, 4, ['DERP302'], S),
    (6, 'DEPE603', 'Dental Management of Medically Compromised Patients', 'تدبير الأمراض العامة', 1, 1, 0, ['DEOS501'], S),
    (6, 'DEOS604', 'Principles of Pain and Anxiety Control', 'مبادئ السيطرة على الألم والقلق', 2, 1, 2, ['DEOS306', 'DEOS407'], S),
    (6, 'DEHS605', 'Oral Pathology', 'التشريح المرضي الخاص بالفم والأسنان', 4, 2, 4, ['DEHS405', 'DEHS505'], S),
    (6, 'DEOM606', 'Forensic Dentistry', 'طب الأسنان الشرعي', 1, 1, 0, ['DEOS306'], S),
    (6, 'DEPD607', 'Radiology & Imaging in Dentistry (2)', 'علم الأشعة والتصوير في طب الأسنان (2)', 2, 1, 2, ['DEPD507'], S),
    (6, 'DEPD608', 'Behavioral Sciences and Communications Skills', 'العلوم السلوكية ومهارات التواصل', 2, 2, 0, [], S, 60),
    # Year 4, term 1
    (7, 'DEOP701', 'Operative Dentistry (3)', 'مداواة أسنان ترميمية (3)', 3, 1, 4, ['DEOP601', 'DEOP506'], S),
    (7, 'DERP702', 'Removable Prosthodontics (3)', 'تعويض الأسنان المتحركة (3)', 4, 2, 4, ['DERP602'], S),
    (7, 'DEOR703', 'Orthodontics (1)', 'تقويم الأسنان (1)', 3, 2, 2, ['DEPD507'], S),
    (7, 'DEOS704', 'Nutrition', 'التغذية', 1, 1, 0, ['DEOM402'], F),
    (7, 'DEOP705', 'Endodontics (1)', 'مداواة الأسنان اللبية (1)', 3, 2, 2, ['DEOP601'], S),
    (7, 'DEPD706', 'Pediatric Dentistry (1)', 'طب أسنان الأطفال (1)', 2, 1, 2, ['DEOM502'], S),
    (7, 'DEOS707', 'Oral Surgery (1)', 'جراحة الفم (1)', 3, 2, 2, ['DEOS604'], S),
    # Year 4, term 2
    (8, 'DEOP801', 'Operative Dentistry (4)', 'مداواة أسنان ترميمية (4)', 2, 1, 2, ['DEOP701'], S),
    (8, 'DEOS802', 'Dental Implantology', 'غرس الأسنان', 2, 1, 2, ['DEOS707'], S),
    (8, 'DEFP803', 'Fixed Prosthodontics (3)', 'تعويض الأسنان الثابتة (3)', 3, 1, 4, ['DEFP503'], S),
    (8, 'DEPE804', 'Periodontology (2)', 'علم النسج حول السنية (2)', 2, 1, 2, ['DEPE504'], S),
    (8, 'DEOP805', 'Endodontics (2)', 'مداواة الأسنان اللبية (2)', 2, 1, 2, ['DEOP705'], S),
    (8, 'DEOR806', 'Orthodontics (2)', 'تقويم الأسنان (2)', 3, 2, 2, ['DEOR703'], S),
    (8, 'DEOS807', 'Oral Surgery (2)', 'جراحة الفم (2)', 2, 1, 2, ['DEOS707'], S),
    (8, 'DEOM808', 'Oral Medicine (1)', 'طب الفم (1)', 2, 1, 2, ['DEPE603'], S),
    # Year 5, term 1
    (9, 'DEOM901', 'Geriatric Dentistry', 'طب الأسنان الشيخوخي', 1, 1, 0, ['DEOM606'], S),
    (9, 'DERP902', 'Removable Prosthodontics (4)', 'تعويض الأسنان المتحركة (4)', 2, 1, 2, ['DERP702'], S),
    (9, 'DEFP903', 'Fixed Prosthodontics (4)', 'تعويض الأسنان الثابتة (4)', 2, 1, 2, ['DEFP803'], S),
    (9, 'DEPE904', 'Periodontology (3)', 'علم النسج حول السنية (3)', 2, 1, 2, ['DEPE804'], S),
    (9, 'DEOP905', 'Endodontics (3)', 'مداواة الأسنان اللبية (3)', 3, 1, 4, ['DEOP805'], S),
    (9, 'DEPD906', 'Pediatric Dentistry (2)', 'طب أسنان الأطفال (2)', 2, 1, 2, ['DEPD706'], S),
    (9, 'DEOS907', 'Oral Surgery (3)', 'جراحة الفم (3)', 2, 1, 2, ['DEOS807'], S),
    (9, 'DEOM908', 'Oral Medicine (2)', 'طب الفم (2)', 2, 1, 2, ['DEOM808'], S),
    (9, 'DEOS909', 'Maxillofacial Surgery (1)', 'جراحة وجه وفكين (1)', 1, 1, 0, ['DEOS807'], S),
    # Year 5, term 2
    (10, 'DEOP001', 'Operative Dentistry (5)', 'مداواة أسنان ترميمية (5)', 2, 0, 4, ['DEOP801'], S),
    (10, 'DERP002', 'Removable Prosthodontics (5)', 'تعويض الأسنان المتحركة (5)', 2, 0, 4, ['DERP902'], S),
    (10, 'DEFP003', 'Fixed Prosthodontics (5)', 'تعويض الأسنان الثابتة (5)', 2, 0, 4, ['DEFP903'], S),
    (10, 'DEPE004', 'Integrated Dental Care', 'التدبير الشامل للمريض', 1, 0, 2, [], S, 130),
    (10, 'DEOP005', 'Endodontics (4)', 'مداواة الأسنان اللبية (4)', 3, 1, 4, ['DEOP905'], S),
    (10, 'DEPD006', 'Pediatric Dentistry (3)', 'طب أسنان الأطفال (3)', 3, 1, 4, ['DEPD906'], S),
    (10, 'DEOS007', 'Oral Surgery (4)', 'جراحة الفم (4)', 2, 1, 2, ['DEOS907'], S),
    (10, 'DEOS008', 'Maxillofacial Surgery (2)', 'جراحة وجه وفكين (2)', 2, 2, 0, ['DEOS909'], S),
    # University electives (choose 3 courses, 6 credits). The guidance plan gives them no term;
    # they are placed in term 3, the first term after English Language (2).
    (3, 'UNRE302', 'English Language (3)', 'اللغة الإنكليزية (3)', 2, 2, 0, ['UNRE207'], U, 0, False),
    (3, 'UNRE104', 'Scientific Thinking', 'المنطق والتفكير العلمي', 2, 2, 0, [], U, 0, False),
    (3, 'UNRE106', 'Second Foreign Language', 'لغة أجنبية حية (ثانية)', 2, 2, 0, [], U, 0, False),
    (3, 'UNRE108', 'Social Science', 'علم اجتماع', 2, 2, 0, [], U, 0, False),
    (3, 'UNRE110', 'Introduction to Law', 'مدخل إلى القانون', 2, 2, 0, [], U, 0, False),
    (3, 'UNRE112', 'Management Science', 'علم الإدارة', 2, 2, 0, [], U, 0, False),
    (3, 'UNRE114', 'Environmental Science', 'علم البيئة', 2, 2, 0, [], U, 0, False),
]

STATED_TERM_CREDITS = {1: 18, 2: 18, 3: 18, 4: 18, 5: 18, 6: 18, 7: 19, 8: 18, 9: 17, 10: 17}
STATED_SECTION_CREDITS = {U: 9, F: 19 + 17, S: 134}
STATED_ELECTIVE_CREDITS = 6
STATED_TOTAL = 185


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def main(out_json, out_report):
    issues = []
    codes = {row[1] for row in ROWS}
    assert len(codes) == len(ROWS), 'duplicate code'
    by_term, term_credits, section_credits = {}, {}, {}
    compulsory = 0
    for row in ROWS:
        term, code, en, ar, credits, theory, practical, prereqs, ctype = row[:9]
        prereq_credits = row[9] if len(row) > 9 else 0
        required = row[10] if len(row) > 10 else True
        for p in prereqs:
            assert p in codes, (code, p)
        if required:
            term_credits[term] = term_credits.get(term, 0) + credits
            section_credits[ctype] = section_credits.get(ctype, 0) + credits
            compulsory += credits
        by_term.setdefault(term, []).append(OrderedDict([
            ('id', slug(code)), ('code', code), ('titleEn', en), ('titleAr', ar), ('term', term),
            ('type', ctype), ('required', required), ('forced', False), ('credits', credits),
            ('prerequisites', [slug(p) for p in prereqs]), ('prerequisiteCredits', prereq_credits), ('instructor', None),
            ('hours', {'theory': theory, 'practical': practical}),
            ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
        ]))

    for term, stated in STATED_TERM_CREDITS.items():
        if term_credits.get(term) != stated:
            issues.append('Term %d adds up to %s compulsory credits but the PDF says %d. Check the transcription.' % (term, term_credits.get(term), stated))
    for ctype, stated in STATED_SECTION_CREDITS.items():
        if section_credits.get(ctype) != stated:
            issues.append('%s courses add up to %s credits but the PDF says %d. Check the transcription.' % (ctype, section_credits.get(ctype), stated))
    total = compulsory + STATED_ELECTIVE_CREDITS
    if total != STATED_TOTAL:
        issues.append('Total is %d credits but the PDF says %d. Check the transcription.' % (total, STATED_TOTAL))

    terms = [OrderedDict([('id', n), ('labelEn', str(n)), ('labelAr', str(n)), ('courses', by_term.get(n, []))])
             for n in range(1, 11)]
    department = OrderedDict([('id', 'dentistry'), ('nameEn', 'Dentistry'), ('nameAr', 'طب الأسنان'), ('summaryEn', ''),
                              ('summaryAr', ''), ('totalCredits', total), ('terms', terms)])

    electives = [r for r in ROWS if len(r) > 10 and not r[10]]
    issues += [
        'INFO: %d compulsory courses (%d credits) plus %d university electives to choose 3 from (6 credits) = %d credits. Every term total, every section total and the 185 grand total match the PDF.' % (
            len(ROWS) - len(electives), compulsory, len(electives), total),
        'The guidance plan (pages 17-21) gives the university electives no term. All seven are placed in term 3 (the first term after English Language (2), which English Language (3) requires) and marked elective. Move them in the editor if the faculty schedules them elsewhere.',
        'Course codes come from the course description pages (22-106). The guidance plan itself has no codes.',
        'Types follow the PDF\'s sections: UNRE courses "university"; medical sciences (Microbiology, Anatomy 1 and 2, General and Minor Surgery, Internal Medicine, Emergencies, Pharmacology, Nutrition) and life sciences (Dental Biochemistry 1 and 2, Medical Physics, Physiology 1 and 2, Cell Biology & Immunology, Human Genetics) "faculty"; the 134-credit dentistry sciences "specialization".',
        'Ethics and Laws and Behavioral Sciences need 60 completed credits, Integrated Dental Care needs 130. These are stored as "completed credits" prerequisites.',
        'Emergencies (after General and Minor Surgery) and Dental Management of Medically Compromised Patients (after Internal Medicine) say "previous or concurrent course". The plan has no concurrent link, so they are stored as ordinary prerequisites; both prerequisite courses fall in an earlier term anyway.',
        'Geriatric Dentistry lists Forensic Dentistry as its prerequisite on both pages 14 and 21. It is kept as written. Check this.',
        'Year 5 term 2 is headed "9 courses" but lists 8 rows adding up to the stated 17 credits; the 8 rows are used.',
        'Page 12 labels Anatomy (1) (General) as "Anatomy 2 (General)" in English; the Arabic and the guidance plan say Anatomy (1).',
    ]
    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': 'خطة درسية جديدة بقرار 176 تاريخ 23-2-2014.pdf (transcribed)', 'departments': [department]}, f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('%d rows, %d credits, %d notes' % (len(ROWS), total, len(issues)))


if __name__ == '__main__':
    main(*sys.argv[1:3])
