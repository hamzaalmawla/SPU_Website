#!/usr/bin/env python3
"""Builds the Petroleum Engineering study plan JSON.

Usage: python -I build_petroleum_plan.py <out.json> <report.md>

The source ("توصيف المقررات الدراسية في كلية هندسة البترول") is a PDF whose text layer is
unusable (legacy font encoding), so the course table below was transcribed by hand from the
rendered pages: code, Arabic name, weekly theory/practical hours. Everything else is derived
and listed in the report. The English titles are draft translations for review.

The PDF contains no credit values and no prerequisites.
"""
import json
import os
import re
import sys
from collections import OrderedDict

# (code, arabic title, english title draft, theory hours, practical hours, type, term override)
COURSES = [
    ('PT-11-01', 'فيزياء عامة', 'General Physics', 3, 2),
    ('PT-11-02', 'رياضيات -1', 'Mathematics 1', 2, 2),
    ('PT-11-03', 'كيمياء عامة ولا عضوية', 'General and Inorganic Chemistry', 2, 2),
    ('PT-11-04', 'هندسة وصفية', 'Descriptive Geometry', 2, 2),
    ('PT-11-05', 'جيولوجيا عامة', 'General Geology', 2, 2),
    ('REQU-1-01', 'اللغة الإنكليزية -1', 'English Language 1', 2, 0, 'university', 1),
    ('PT-12-01', 'رسم هندسي', 'Engineering Drawing', 1, 2),
    ('PT-12-02', 'رياضيات -2', 'Mathematics 2', 2, 2),
    ('PT-12-03', 'كيمياء عضوية', 'Organic Chemistry', 2, 2),
    ('PT-12-04', 'ميكانيك هندسي', 'Engineering Mechanics', 3, 2),
    ('REQU-1-05', 'اللغة العربية', 'Arabic Language', 2, 0, 'university', 2),
    ('REQU-2-07', 'اللغة الإنكليزية -2', 'English Language 2', 2, 0, 'university', 2),
    ('PT-21-01', 'جيولوجيا فيزيائية', 'Physical Geology', 2, 2),
    ('PT-21-02', 'مقاومة المواد', 'Strength of Materials', 2, 2),
    ('PT-21-03', 'أسس الهندسة الكهربائية', 'Fundamentals of Electrical Engineering', 2, 2),
    ('PT-21-04', 'رياضيات هندسية', 'Engineering Mathematics', 2, 2),
    ('PT-21-05', 'كيمياء فيزيائية', 'Physical Chemistry', 2, 2),
    ('REQU-1-03', 'مهارات الحاسوب', 'Computer Skills', 2, 2, 'university', 3),
    ('PT-22-01', 'الصخور والأحواض الرسوبية', 'Sedimentary Rocks and Basins', 2, 2),
    ('PT-22-02', 'جيوكيمياء النفط والغاز', 'Petroleum and Gas Geochemistry', 2, 2),
    ('PT-22-03', 'ثرموديناميك هندسي', 'Engineering Thermodynamics', 2, 2),
    ('PT-22-04', 'خواص الصخور الخزنية', 'Reservoir Rock Properties', 2, 2),
    ('PT-22-05', 'الجيولوجيا البنيوية', 'Structural Geology', 2, 2),
    ('PT-31-01', 'ميكانيك تفتيت الصخور', 'Rock Fragmentation Mechanics', 2, 2),
    ('PT-31-02', 'جيولوجيا النفط', 'Petroleum Geology', 3, 2),
    ('PT-31-03', 'ميكانيك الموائع', 'Fluid Mechanics', 3, 2),
    ('PT-31-04', 'سوائل الحفر', 'Drilling Fluids', 2, 2),
    ('PT-31-05', 'هندسة مخزون النفط -1', 'Petroleum Reservoir Engineering 1', 2, 2),
    ('PT-32-01', 'هيدروليك الموائع الجوفية', 'Subsurface Fluid Hydraulics', 2, 2),
    ('PT-32-02', 'الجيوفيزياء التطبيقية', 'Applied Geophysics', 3, 2),
    ('PT-32-03', 'طرائق الحفر', 'Drilling Methods', 2, 2),
    ('PT-32-04', 'خواص الموائع الطبقية', 'Reservoir Fluid Properties', 2, 2),
    ('PT-32-05', 'هندسة إنتاج النفط -1', 'Petroleum Production Engineering 1', 3, 2),
    ('PT-32-06', 'هندسة مخزون النفط -2', 'Petroleum Reservoir Engineering 2', 2, 2),
    ('PT-41-01', 'القياسات الجيوفيزيائية البئرية -1', 'Well Logging 1', 2, 2),
    ('PT-41-02', 'تغليف وسمنتة الآبار', 'Well Casing and Cementing', 3, 2),
    ('PT-41-03', 'ميكانيك آلات الحفر والإنتاج -1', 'Drilling and Production Machinery Mechanics 1', 2, 2),
    ('PT-41-04', 'هندسة إنتاج النفط -2', 'Petroleum Production Engineering 2', 3, 2),
    ('PT-41-05', 'المسح الاهتزازي', 'Seismic Survey', 3, 2),
    ('PT-42-01', 'هندسة مخزون الغاز', 'Gas Reservoir Engineering', 2, 2),
    ('PT-42-02', 'الاستثمار المدعم للنفط', 'Enhanced Oil Recovery', 3, 2),
    ('PT-42-03', 'القياسات الجيوفيزيائية البئرية -2', 'Well Logging 2', 2, 2),
    ('PT-42-04', 'ميكانيك آلات الحفر والإنتاج -2', 'Drilling and Production Machinery Mechanics 2', 2, 2),
    ('PT-42-05', 'المساحة والاستشعار عن بعد', 'Surveying and Remote Sensing', 2, 2),
    ('PT-42-06', 'لغة إنكليزية لمهندسي البترول', 'English for Petroleum Engineers', 2, 0),
    ('PT-51-01', 'هندسة انتاج الغاز', 'Gas Production Engineering', 2, 2),
    ('PT-51-02', 'القياس والتحكم', 'Measurement and Control', 2, 2),
    ('PT-51-03', 'نظام ومشاكل الحفر', 'Drilling Systems and Problems', 2, 2),
    ('PT-51-04', 'المعالجة الرقمية للمعطيات الاهتزازية', 'Digital Processing of Seismic Data', 2, 2),
    ('PT-51-05', 'نمذجة ومحاكاة الخزانات', 'Reservoir Modeling and Simulation', 2, 2),
    ('PT-51-06', 'نقل وتخزين النفط', 'Oil Transport and Storage', 2, 2),
    ('PT-51-07', 'مشروع التخرج', 'Graduation Project', 0, 4),
    ('PT-52-01', 'صيانة وإصلاح الآبار', 'Well Maintenance and Workover', 2, 2),
    ('PT-52-02', 'نقل وتخزين الغاز', 'Gas Transport and Storage', 2, 2),
    ('PT-52-03', 'الحفر البحري والموجه', 'Offshore and Directional Drilling', 2, 2),
    ('PT-52-04', 'الجيولوجيا الإقليمية', 'Regional Geology', 2, 2),
    ('PT-52-05', 'الأمن الصناعي', 'Industrial Safety', 2, 0),
]


def slug(code):
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def main(out_json, out_report):
    issues = []
    by_term = {}
    total = 0
    for row in COURSES:
        code, title_ar, title_en, theory, practical = row[:5]
        match = re.fullmatch(r'PT-(\d)(\d)-\d+', code)
        if match:
            term = (int(match.group(1)) - 1) * 2 + int(match.group(2))
            ctype = 'specialization'
        else:
            ctype, term = row[5], row[6]
        credits = theory + practical // 2
        total += credits
        by_term.setdefault(term, []).append(OrderedDict([
            ('id', slug(code)), ('code', code), ('titleEn', title_en), ('titleAr', title_ar),
            ('term', term), ('type', ctype), ('required', True), ('forced', False),
            ('credits', credits), ('prerequisites', []), ('prerequisiteCredits', 0),
            ('instructor', None), ('hours', {'theory': theory, 'practical': practical}),
            ('descriptionEn', ''), ('descriptionAr', ''), ('lessons', []),
        ]))

    terms = [OrderedDict([('id', n), ('labelEn', str(n)), ('labelAr', str(n)), ('courses', by_term.get(n, []))])
             for n in range(1, 11)]
    department = OrderedDict([('id', 'petroleum'), ('nameEn', 'Petroleum Engineering'), ('nameAr', 'هندسة البترول'),
                              ('summaryEn', ''), ('summaryAr', ''), ('totalCredits', total), ('terms', terms)])

    issues += [
        'The source PDF has NO prerequisites, so no prerequisite links were created. Add them in the editor.',
        'The source PDF has NO credit values. Credits were derived as theory hours + practical hours / 2, which matches every course in the AI and construction regulations. The graduation project (4 practical hours over two semesters) was given 2 credits. Total: %d credits across %d courses; with the three 2-credit elective slots that makes %d, matching the usual 180.' % (total, len(COURSES), total + 6),
        'Terms come from the code PT-<year><semester>-<nn> (PT-32 = year 3, semester 2).',
        'PT-32-04 "خواص الموائع الطبقية" appears among the year-2 pages and is labelled "second stage", but its code is year 3 semester 2 and it completes the PT-32-01..06 series. Placed in term 6 by its code.',
        'The REQU (university) courses carry no semester. They were placed with the PT block they appear next to in the PDF: English 1 in term 1, Arabic and English 2 in term 2, Computer Skills in term 3.',
        'All PT courses are marked "specialization" and all REQU courses "university" because the PDF does not classify them further.',
        'The PDF lists three "optional university requirement" slots (2 theory hours each); they are not courses, so they were not added.',
        'Course descriptions, objectives and contents in the PDF were NOT imported (they are long Arabic texts); the description fields are empty.',
        'The English titles are draft translations written for this import, not text from the source. Review them.',
    ]
    with open(out_json, 'w', encoding='utf8') as f:
        json.dump({'source': 'توصيف مقررات خطة جديدة عربي كلية هندسة البترول.pdf (transcribed)', 'departments': [department]},
                  f, ensure_ascii=False, indent=2)
    with open(out_report, 'w', encoding='utf8') as f:
        f.write('# Conversion report\n\n' + '\n'.join('- ' + i for i in issues) + '\n')
    print('%d courses, %d credits' % (len(COURSES), total))


if __name__ == '__main__':
    main(*sys.argv[1:3])
