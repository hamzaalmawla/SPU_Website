#!/usr/bin/env python3
"""Builds business-administration.json and its report from the Business Faculty 2026 study plan.

Usage: python3 -I build_business_2026.py   (writes both files next to it)

Source: "كلية الأعمال - الخطة الدراسية 2026" (PDF, 71 pages, September 2026). Five programs of 134
credit hours each: university 15 (11 compulsory, 4 elective), faculty 57 (51 compulsory, 6 elective),
program 62 (56 compulsory, 6 elective).

Everything here is transcribed from the PDF: the requirement tables (sections 14 to 16) give each
course's code, Arabic and English title, theory and practical hours, credits and prerequisite; the
level tables (section 17) give the level each course is taught in; chapter 4 gives the Arabic course
descriptions (business-2026-descriptions.json, with English translations). Where the PDF disagrees
with itself, the choice made is written to the report, never silently.

The program ids of the old plan are kept so the departments page and staff profiles stay linked:
hr = Smart Human Resource Management, accounting = Smart Accounting, marketing = Digital Marketing,
finance = Finance Technology (FinTech), mis = Business Intelligence and Logistics.

Import (manual, after review): php artisan faculty:import-study-plan business-administration
database/seeders/Data/study_plans/business-administration.json --user=<id> --replace-edited --write
"""
import json
import math
import re
from pathlib import Path

HERE = Path(__file__).parent
DESCRIPTIONS = json.loads((HERE / 'business-2026-descriptions.json').read_text(encoding='utf-8'))
REPORT = []

TOTAL_CREDITS = 134
# Graduation projects: "after completing 80% of the hours of the whole plan" (section 8 and the tables).
GRADUATION_PROJECT_CREDITS = math.ceil(TOTAL_CREDITS * 0.8)


def note(line: str) -> None:
    REPORT.append(f"- {line}")


def course_id(code: str) -> str:
    """Lowercase URL-safe, as the study plan requires: BFCR.1.01 -> bfcr-1-01, UR-2-11/15 -> ur-2-11-15."""
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def course(code, title_ar, title_en, term, kind, credits, theory, practical, required=True, prerequisites=(), prerequisite_credits=0):
    text = DESCRIPTIONS['courses'].get(code, {})

    return {
        'id': course_id(code),
        'code': code,
        'titleEn': title_en,
        'titleAr': title_ar,
        'term': term,
        'type': kind,
        'required': required,
        'forced': False,
        'credits': credits,
        'prerequisites': [course_id(p) for p in prerequisites],
        'prerequisiteCredits': prerequisite_credits,
        'instructor': None,
        'hours': {'theory': theory, 'practical': practical},
        'descriptionEn': text.get('descriptionEn', ''),
        'descriptionAr': text.get('descriptionAr', ''),
        'lessons': [],
    }


# ── University requirements (section 14) ─────────────────────────────────────
# code: (Arabic, English, credits, theory, practical, prerequisites, term)
UNIVERSITY = {
    'UR-2-02': ('مهارات الحاسوب', 'Computer Skills', 3, 2, 2, [], 1),
    'UR-2-03': ('اللغة الإنكليزية (1)', 'English Language (1)', 3, 3, 0, [], 2),
    'UR-2-04': ('اللغة الإنكليزية (2)', 'English Language (2)', 3, 3, 0, ['UR-2-03'], 3),
    'UR-2-01': ('مهارات اللغة العربية', 'Arabic Language', 2, 2, 0, [], 4),
}
# Students choose two (4 credit hours). code: (Arabic, English, prerequisites, term)
UNIVERSITY_ELECTIVES = {
    'UR-2-07': ('مهارات التفكير والبحث العلمي', 'Scientific Thinking', [], 3),
    'UR-2-11/15': ('لغة أجنبية حية ثانية (فرنسية - ألمانية - روسية - إسبانية - فارسية)', 'Second Foreign Language (French - German - Russian - Spanish - Persian)', [], 3),
    'UR-2-06': ('علم الاجتماع', 'Social Science', [], 3),
    'UR-2-05': ('مدخل إلى القانون', 'Introduction to Law', [], 3),
    'UR-2-08': ('أساسيات الإدارة ومهاراتها', 'Management Science', [], 3),
    'UR-2-09': ('علم البيئة', 'Environmental Science', [], 3),
    'UR-2-17': ('أخلاقيات وسلوكيات المهنة', 'Career Ethics and Behaviors', [], 3),
    'UR-2-16': ('المهارات الحركية والبدنية والذهنية', 'Motor, Physical and Mental Skills', [], 3),
    'UR-2-10': ('اللغة الإنكليزية (3)', 'English Language (3)', ['UR-2-04'], 5),
}

# ── Faculty requirements (section 15) ────────────────────────────────────────
# code: (Arabic, English, credits, theory, practical, prerequisites)
FACULTY = {
    'BFCR.1.01': ('مبادئ التسويق', 'Principles of Marketing', 3, 3, 0, []),
    'BFCR.1.02': ('مبادئ الإدارة', 'Management Principles', 3, 3, 0, []),
    'BFCR.1.03': ('رياضيات الأعمال', 'Mathematics for Business', 3, 2, 2, []),
    'BFCR.1.04': ('اقتصاد الأعمال', 'Business Economics', 3, 2, 2, []),
    'BFCR.1.05': ('إدارة المشاريع والتنمية المستدامة', 'Project Management and Sustainable Development', 3, 3, 0, []),
    'BFCR.2.06': ('مبادئ المحاسبة 1', 'Principles of Accounting 1', 3, 2, 2, []),
    'BFCR.2.07': ('مبادئ إدارة الموارد البشرية', 'Principles of Human Resources Management', 3, 3, 0, []),
    'BFCR.2.08': ('مبادئ برمجة الأعمال', 'Principles of Programming for Business', 3, 2, 2, ['UR-2-02']),
    'BFCR.2.09': ('إحصاء الأعمال', 'Statistics for Management', 3, 2, 2, ['BFCR.1.03']),
    'BFCR.2.10': ('تقنيات الثورة الصناعية الحديثة وتطبيقاتها', 'Applications of (4IR)', 3, 2, 2, []),
    'BFCR.3.11': ('مبادئ الذكاء الاصطناعي', 'Principles of Artificial Intelligence', 3, 2, 2, ['BFCR.2.09']),
    'BFCR.3.12': ('مبادئ تكنولوجيا مالية (1)', 'Principles of FinTech (1)', 3, 2, 2, ['BFCR.1.03']),
    'BFCR.3.13': ('مبادئ المحاسبة 2', 'Principles of Accounting 2', 3, 2, 2, ['BFCR.2.06']),
    'BFCR.4.14': ('بحوث العمليات', 'Operations Research', 3, 2, 2, ['BFCR.1.03']),
    'BFCR.4.15': ('ريادة الأعمال والإبداع', 'Entrepreneurship and Innovation', 3, 3, 0, []),
    'BFCR.4.16': ('محاسبة إدارية ودراسات الجدوى الاقتصادية', 'Managerial Accounting and Economic Feasibility Studies', 3, 2, 2, ['BFCR.3.13']),
    'BFCR.4.17': ('مناهج البحث العلمي', 'Research Methodology', 3, 3, 0, []),
}
# Students choose two (6 credit hours). code: (Arabic, English, theory, practical, prerequisites)
FACULTY_ELECTIVES = {
    'BFCR.3.18': ('إنكليزي الأعمال', 'English for Business', 3, 0, ['UR-2-04']),
    'BFCR.1.19': ('مهارات الدخول لسوق العمل', 'Entering Labor Market Skills', 2, 2, []),
    'BFCR.1.20': ('مهارات التواصل', 'Communication Skills', 2, 2, []),
    'BFCR.2.21': ('الإدارة الاستراتيجية', 'Strategic Management', 3, 0, ['BFCR.1.02']),
    'BFCR.4.22': ('محاسبة شركات الأشخاص', 'Accounting for Partnerships', 2, 2, ['BFCR.3.13']),
    'BFCR.2.23': ('تسويق الأعمال بين الشركات', 'B2B Marketing', 3, 0, ['BFCR.1.01']),
    'BFCR.2.24': ('إدارة العمليات في المنظمات الذكية', 'Operations Management in Smart Organizations', 3, 0, ['BFCR.1.02']),
    'BFCR.2.25': ('إدارة اللوجستيات', 'Logistics Management', 3, 0, ['BFCR.1.02']),
}

# Levels 1-4 are the same for every program (section 17).
SHARED_TERMS = {
    1: ['BFCR.1.01', 'BFCR.1.02', 'BFCR.1.03', 'BFCR.1.04', 'BFCR.1.05', 'UR-2-02'],
    2: ['BFCR.2.06', 'BFCR.2.07', 'BFCR.2.08', 'BFCR.2.09', 'BFCR.2.10', 'UR-2-03'],
    3: ['BFCR.3.11', 'BFCR.3.12', 'BFCR.3.13', 'UR-2-04'],
    4: ['BFCR.4.14', 'BFCR.4.15', 'BFCR.4.16', 'BFCR.4.17', 'UR-2-01'],
}
# The level tables put the faculty elective in levels 3 and 4, the program elective in levels 5 and 6.
FACULTY_ELECTIVE_TERMS = (3, 4)
PROGRAM_ELECTIVE_TERMS = (5, 6)

INTERNSHIP = 'internship'
GRADUATION = 'graduation'

# ── Programs (section 16) ────────────────────────────────────────────────────
# Compulsory: (code, Arabic, English, credits, theory, practical, prerequisites, term)
# Electives (6 credit hours, two courses): (code, Arabic, English, theory, practical, prerequisites)
PROGRAMS = [
    {
        'id': 'hr', 'nameAr': 'إدارة الموارد البشرية الذكية', 'nameEn': 'Smart Human Resource Management',
        'summaryEn': 'The program starts from the shift of the human resource function from an administrative, procedural role to a strategic partner in building capabilities, culture and performance. It therefore keeps the professional foundations of recruitment, development, compensation and labor relations, and adds digital systems, people analytics, workforce planning and change management. In this it follows the plan\'s direction toward "smart human resource management" without reducing the specialization to the use of a single information system.',
        'courses': [
            ('BFSHR5.01', 'إدارة الموارد البشرية في المنظمات الذكية', 'Human Resource Management in Smart Organizations', 3, 3, 0, ['BFCR.2.07'], 5),
            ('BFSHR5.02', 'استقطاب المواهب وبناء العلامة التجارية لصاحب العمل', 'Talent Acquisition & Employer Branding', 3, 3, 0, ['BFCR.2.07'], 5),
            ('BFSHR5.03', 'التعويضات والمكافآت ورفاهية العاملين', 'Compensation, Rewards & Employee Wellbeing', 3, 3, 0, ['BFCR.2.07'], 5),
            ('BFSHR5.04', 'إدارة الأداء والقياس', 'Performance Management and Measurement', 3, 2, 2, ['BFCR.2.07'], 5),
            ('BFSHR6.05', 'التعلم والتطوير المستمر', 'Continuous Learning and Development', 3, 3, 0, ['BFCR.2.07'], 6),
            ('BFSHR6.06', 'السلوك التنظيمي', 'Organizational Behavior', 3, 3, 0, ['BFCR.2.07'], 6),
            ('BFSHR6.07', 'فن القيادة', 'The Art of Leadership', 3, 3, 0, ['BFCR.1.02'], 6),
            ('BFSHR6.08', 'حل المشكلات واتخاذ القرار', 'Problem Solving and Decision Making', 3, 2, 2, ['BFCR.1.02'], 6),
            ('BFSHR7.09', 'إدارة التنوع والعدالة والشمول', 'Diversity, Equity and Inclusion Management', 3, 3, 0, ['BFCR.2.07'], 7),
            ('BFSHR7.10', 'التفاوض وإدارة النزاعات', 'Negotiation & Conflict Management', 3, 2, 2, ['BFCR.1.02'], 7),
            ('BFSHR7.11', 'الإدارة الاستراتيجية للمواهب والتطوير التنظيمي', 'Strategic Talent Management & Organizational Development', 3, 2, 2, ['BFCR.2.07'], 7),
            ('BFSHR7.12', 'إدارة المعرفة', 'Knowledge Management', 3, 2, 2, ['BFCR.1.02'], 7),
            ('BFSHR7.13', 'تدريب مهني في إدارة الموارد البشرية', 'Internship in HRM', 4, 2, 4, INTERNSHIP, 7),
            ('BFSHR8.14', 'نظم معلومات إدارة الموارد البشرية', 'HRM Information System', 3, 2, 2, ['BFCR.2.07'], 8),
            ('BFSHR8.15', 'قانون العمل وحوكمة الموارد البشرية', 'Employment Law & HR Governance', 3, 3, 0, [], 8),
            ('BFSHR8.16', 'قضايا معاصرة في إدارة الموارد البشرية', 'Contemporary Issues in HRM', 3, 3, 0, ['BFCR.2.07'], 8),
            ('BFSHR8.17', 'التحليلات التنبؤية للموارد البشرية', 'Predictive HR Analytics', 3, 2, 2, ['BFCR.2.07'], 8),
            ('BFSHR8.18', 'مشروع تخرج في إدارة الموارد البشرية', 'Graduation Project in HRM', 4, 2, 4, GRADUATION, 8),
        ],
        'electives': [
            ('BFSHR5.19', 'الإدارة الرشيقة للموارد البشرية', 'Agile HRM', 3, 0, ['BFCR.2.07']),
            ('BFSHR5.20', 'إدارة الموارد البشرية في المنظمات غير الحكومية', 'Human Resource Management in Non-Governmental Organizations', 2, 2, ['BFCR.2.07']),
            ('BFSHR5.21', 'إدارة الموارد البشرية في المنشآت السياحية', 'Human Resource Management in Hospitality and Tourism Organization', 3, 0, ['BFCR.2.07']),
            ('BFSHR5.22', 'إدارة الموارد البشرية الخضراء والاستدامة', 'Green HR Management and Sustainability', 2, 2, ['BFCR.2.07']),
            ('BFSHR5.23', 'إدارة التغيير', 'Change Management', 3, 0, []),
        ],
    },
    {
        'id': 'accounting', 'nameAr': 'المحاسبة الذكية', 'nameEn': 'Smart Accounting',
        'summaryEn': 'The program reflects how the accountant\'s role has moved from recording and reporting to analysis, control, forecasting and decision support. It therefore keeps depth in financial and managerial accounting, costing, auditing and standards, and adds information systems, analytics and smart tools. Technology remains a tool that strengthens professional judgment, not a substitute for principles, standards and professional responsibility.',
        'courses': [
            ('BFSAC5.01', 'محاسبة متوسطة I', 'Intermediate Accounting I', 3, 2, 2, ['BFCR.3.13'], 5),
            ('BFSAC5.02', 'محاسبة تكاليف', 'Cost Accounting', 3, 2, 2, ['BFCR.3.13'], 5),
            ('BFSAC5.03', 'محاسبة ضريبية', 'Taxation Accounting', 3, 2, 2, ['BFCR.3.13'], 5),
            ('BFSAC5.4', 'محاسبة إدارية متقدمة', 'Advanced Managerial Accounting', 3, 2, 2, ['BFCR.4.16'], 5),
            ('BFSAC6.05', 'محاسبة متوسطة II', 'Intermediate Accounting II', 3, 2, 2, ['BFSAC5.01'], 6),
            ('BFSAC6.06', 'الحوكمة والتدقيق 1', 'Governance and Auditing 1', 3, 3, 0, ['BFCR.3.13'], 6),
            ('BFSAC6.07', 'محاسبة شركات الأموال', 'Corporate Accounting', 3, 2, 2, ['BFCR.3.13'], 6),
            ('BFSAC6.08', 'نظم المعلومات المحاسبية', 'Accounting Information Systems', 3, 3, 0, ['BFCR.2.10'], 6),
            ('BFSAC7.09', 'تحليل القوائم المالية', 'Financial Statements Analysis', 3, 2, 2, ['BFSAC6.07'], 7),
            ('BFSAC7.10', 'الحوكمة والتدقيق 2', 'Governance and Auditing 2', 3, 3, 0, ['BFSAC6.06'], 7),
            ('BFSAC7.11', 'تطبيقات الحاسوب في المحاسبة والتدقيق', 'Computer Applications in Accounting and Auditing', 3, 2, 2, ['BFSAC6.08'], 7),
            ('BFSAC7.12', 'محاسبة المؤسسات المالية والمصرفية', 'Financial and Banking Institutions Accounting', 3, 2, 2, ['BFCR.3.13'], 7),
            ('BFSAC7.13', 'تدريب مهني في المحاسبة', 'Internship in Accounting', 4, 2, 4, INTERNSHIP, 7),
            ('BFSAC8.14', 'التحليلات التنبؤية في المحاسبة والتدقيق', 'Predictive Analytics in Accounting and Auditing', 3, 2, 2, ['BFSAC7.11'], 8),
            ('BFSAC8.15', 'محاسبة توحيد الأعمال', 'Business Combination Accounting', 3, 2, 2, ['BFSAC6.07'], 8),
            ('BFSAC8.16', 'معايير المحاسبة الدولية', 'International Accounting', 3, 2, 2, ['BFSAC6.05'], 8),
            ('BFSAC8.17', 'محاسبة مالية متخصصة', 'Specialized Financial Accounting', 3, 2, 2, ['BFCR.3.13'], 8),
            ('BFSAC8.18', 'مشروع تخرج في المحاسبة', 'Graduation Project in Accounting', 4, 2, 4, GRADUATION, 8),
        ],
        'electives': [
            ('BFSAC5.19', 'قضايا محاسبية معاصرة', 'Contemporary Accounting Issues', 2, 2, ['BFCR.3.13']),
            ('BFSAC5.20', 'المحاسبة الحكومية', 'Governmental Accounting', 2, 2, ['BFCR.3.13']),
            ('BFSAC6.21', 'المحاسبة الخضراء والاستدامة', 'Green Accounting and Sustainability', 2, 2, ['BFSAC5.4']),
            ('BFSAC5.22', 'نظرية المحاسبة', 'Accounting Theory', 3, 0, ['BFCR.3.13']),
            ('BFSAC5.23', 'التدقيق الداخلي', 'Internal Audit', 3, 0, ['BFCR.3.13']),
        ],
    },
    {
        'id': 'marketing', 'nameAr': 'التسويق الرقمي', 'nameEn': 'Digital Marketing',
        'summaryEn': 'The program does not treat digital marketing as managing social media platforms only: it starts from understanding the market, consumer behavior, value and the brand, then moves to data, digital channels, personalization, customer experience and measuring performance. This progression supports building a marketer who combines strategic thinking, execution and analysis instead of separating traditional and digital marketing.',
        'courses': [
            ('BFDM5.01', 'إدارة التسويق', 'Marketing Management', 3, 3, 0, ['BFCR.1.01'], 5),
            ('BFDM5.02', 'سلوك المستهلك عبر الإنترنت', 'Online Consumer Behavior', 3, 2, 2, ['BFCR.1.01'], 5),
            ('BFDM5.03', 'مدخل إلى التسويق الإلكتروني', 'Introduction to Digital Marketing', 3, 3, 0, ['BFCR.1.01'], 5),
            ('BFDM5.04', 'إدارة المبيعات والبيع الشخصي', 'Sales Management and Personal Selling', 3, 3, 0, ['BFCR.1.01'], 5),
            ('BFDM6.5', 'التسويق الاستراتيجي', 'Strategic Marketing', 3, 2, 2, ['BFDM5.01'], 6),
            ('BFDM6.6', 'إدارة المنتج والعلامة التجارية الرقمية', 'Digital Product and Brand Management', 3, 3, 0, ['BFDM5.03'], 6),
            ('BFDM6.7', 'نظم المعلومات التسويقية وبحوث التسويق', 'Marketing Information Systems & Marketing Research', 3, 2, 2, ['BFCR.2.09'], 6),
            ('BFDM6.8', 'إدارة العلاقة مع العملاء', 'Customer Relationship Management', 3, 3, 0, ['BFDM5.01'], 6),
            ('BFDM7.9', 'التسويق عبر شبكات التواصل الاجتماعي', 'Social Media Marketing', 3, 3, 0, ['BFDM5.03'], 7),
            ('BFDM7.10', 'التسويق باستخدام محركات البحث', 'Search Engine Marketing', 3, 2, 2, ['BFDM5.03'], 7),
            ('BFDM7.11', 'الاتصالات التسويقية الإلكترونية المتكاملة', 'Integrated Digital Marketing Communications', 3, 3, 0, ['BFDM6.6'], 7),
            ('BFDM7.12', 'تطبيقات الذكاء الاصطناعي في التسويق', 'Applications of Artificial Intelligence in Marketing', 3, 2, 2, ['BFCR.3.11'], 7),
            ('BFDM7.13', 'مشروع تدريب مهني في التسويق الرقمي', 'Internship in DM', 4, 2, 4, INTERNSHIP, 7),
            ('BFDM8.14', 'التسويق الدولي في البيئة الرقمية', 'International Marketing in the Digital Environment', 3, 3, 0, ['BFDM5.01'], 8),
            ('BFDM8.15', 'استراتيجية المحتوى والتسويق الرقمي', 'Content Strategy and Digital Marketing', 3, 3, 0, ['BFDM7.11'], 8),
            ('BFDM8.16', 'استراتيجيات التسعير والتوزيع في البيئة الرقمية', 'Pricing and Distribution Strategies in the Digital Environment', 3, 3, 0, ['BFDM5.01'], 8),
            ('BFDM8.17', 'تحليل البيانات التسويقية', 'Marketing Data Analytics', 3, 2, 2, ['BFDM6.7'], 8),
            ('BFDM8.18', 'مشروع تخرج في التسويق الرقمي', 'Graduation Project in DM', 4, 2, 4, GRADUATION, 8),
        ],
        'electives': [
            ('BFDM6.19', 'التسويق عبر المؤثرين وصناع المحتوى', 'Influencer and Creator Marketing', 3, 0, ['BFDM5.03']),
            ('BFDM6.20', 'التسويق الريادي الرقمي', 'Digital Entrepreneurial Marketing', 3, 0, ['BFDM5.01']),
            ('BFDM6.21', 'التسويق الأخضر والمستدام', 'Green and Sustainable Marketing', 3, 0, ['BFDM5.01']),
            ('BFDM6.22', 'أخلاقيات التسويق والمسؤولية المجتمعية', 'Marketing Ethics and Social Responsibility', 3, 0, ['BFDM5.01']),
            ('BFDM6.23', 'ممارسات تسويقية في قطاعات متخصصة', 'Marketing Practices in Specialized Sectors', 3, 0, ['BFDM5.01']),
        ],
    },
    {
        'id': 'finance', 'nameAr': 'التكنولوجيا المالية', 'nameEn': 'Finance Technology (FinTech)',
        'summaryEn': 'The program responds to the fast transformation of financial services, markets and institutions. Its courses show that its identity is not limited to digital applications: it rests on a base of financial management, markets, institutions, investment and risk management, then moves to payments, digital banking services, RegTech, artificial intelligence, blockchain and FinTech business models. This prepares graduates to work where financial decisions and technology meet.',
        'courses': [
            ('BFFinTech.5.01', 'إدارة المصارف', 'Bank Management', 3, 3, 0, ['BFCR.1.02'], 5),
            ('BFFinTech.5.02', 'مبادئ إدارة الأسواق والمؤسسات المالية', 'Principles of Financial Markets and Institutions', 3, 2, 2, [], 5),
            ('BFFinTech.5.03', 'إدارة مالية في الشركات المساهمة', 'Corporate Financial Management', 3, 2, 2, ['BFCR.1.03'], 5),
            ('BFFinTech.5.04', 'المدفوعات الرقمية والتكنولوجيا المصرفية', 'Digital Payments and Banking Technology', 3, 2, 2, ['BFCR.3.12'], 5),
            ('BFFinTech.6.05', 'التحليل المالي', 'Financial Analysis', 3, 2, 2, ['BFFinTech.5.03'], 6),
            ('BFFinTech.6.06', 'المصارف الإسلامية', 'Islamic Banking', 3, 3, 0, ['BFFinTech.5.01'], 6),
            ('BFFinTech.6.07', 'الخدمات المصرفية الرقمية والتكنولوجيا المالية', 'Digital Banking Services and FinTech', 3, 2, 2, ['BFCR.3.12'], 6),
            ('BFFinTech.6.08', 'التكنولوجيا التنظيمية والامتثال المالي الرقمي', 'RegTech and Digital Financial Compliance', 3, 2, 2, ['BFFinTech.5.01'], 6),
            ('BFFinTech.7.09', 'محاسبة المؤسسات المالية والمصرفية', 'Financial and Banking Institutions Accounting', 3, 2, 2, ['BFCR.3.13'], 7),
            ('BFFinTech.7.10', 'إدارة المحافظ الاستثمارية', 'Investment Portfolio Management', 3, 2, 2, ['BFCR.3.12'], 7),
            ('BFFinTech.7.11', 'الذكاء الاصطناعي في العلوم المالية', 'Artificial Intelligence in Finance', 3, 2, 2, ['BFCR.3.11'], 7),
            ('BFFinTech.7.12', 'إدارة المخاطر المالية', 'Financial Risk Management', 3, 2, 2, ['BFFinTech.5.01'], 7),
            ('BFFinTech.7.13', 'التدريب المهني في التكنولوجيا المالية', 'Internship in Finance and Banking', 4, 2, 4, INTERNSHIP, 7),
            ('BFFinTech.8.14', 'المشتقات المالية والهندسة المالية', 'Financial Derivatives and Financial Engineering', 3, 2, 2, ['BFCR.3.12'], 8),
            ('BFFinTech.8.16', 'نماذج الأعمال والابتكار في التكنولوجيا المالية', 'FinTech Business Models and Innovation', 3, 2, 2, ['BFCR.4.15'], 8),
            ('BFFinTech.8.17', 'البلوك تشين والتطبيقات المالية', 'Blockchain and Financial Applications', 3, 2, 2, ['BFFinTech.7.11'], 8),
            ('BFFinTech.8.23', 'تحليلات الاحتيال المالي الرقمي', 'Digital Financial Fraud Analytics', 3, 2, 2, ['BFFinTech.7.11'], 8),
            ('BFFinTech.8.18', 'مشروع تخرج في التكنولوجيا المالية', 'Graduation Project in FinTech', 4, 2, 4, GRADUATION, 8),
        ],
        'electives': [
            ('BFFinTech.8.19', 'التمويل اللامركزي والأصول الرقمية', 'Decentralized Finance (DeFi) and Digital Assets', 2, 2, ['BFCR.3.12']),
            ('BFFinTech.7.20', 'التمويل الجماعي والإقراض الرقمي', 'Crowdfunding and Digital Lending', 2, 2, ['BFFinTech.6.07']),
            ('BFFinTech.5.21', 'تكنولوجيا التأمين', 'InsurTech', 2, 2, ['BFCR.3.12']),
            ('BFFinTech.7.22', 'الخدمات المصرفية المفتوحة وواجهات البرمجة المالية', 'Open Banking and Financial APIs', 2, 2, ['BFFinTech.6.07']),
            ('BFFinTech.8.15', 'إدارة التحول الرقمي في القطاع المالي', 'Managing Digital Transformation in the Financial Sector', 2, 2, ['BFFinTech.7.11']),
        ],
    },
    {
        'id': 'mis', 'nameAr': 'ذكاء الأعمال والخدمات اللوجستية', 'nameEn': 'Business Intelligence and Logistics',
        'summaryEn': 'The program rests on an integrated academic identity that combines business intelligence, advanced analytics and supply chain and logistics management in one path that links data to decisions and then to operational execution. It prepares students to build a complete decision cycle that starts with collecting, modeling and analyzing data, then uses business intelligence and advanced analytics to interpret performance, forecast and support decisions. The applied side extends to emerging technologies such as the Internet of Things, blockchain, geographic information systems and automation, so that graduates can improve logistics operations and design sustainable, secure solutions that make supply chains efficient and resilient.',
        'courses': [
            ('BFBIL5.1', 'مدخل إلى ذكاء الأعمال والخدمات اللوجستية', 'Introduction to Business Intelligence and Logistics', 3, 3, 0, [], 5),
            ('BFBIL5.2', 'نظم المعلومات الذكية والحوسبة السحابية', 'Intelligent Information Systems and Cloud Computing', 3, 2, 2, ['BFCR.2.10'], 5),
            ('BFBIL5.3', 'إدارة سلاسل الإمداد والخدمات اللوجستية', 'Supply Chain and Logistics Management', 3, 3, 0, ['BFCR.1.02'], 5),
            ('BFBIL5.4', 'التجارة الإلكترونية والخدمات الرقمية', 'E-Commerce and Digital Services', 3, 3, 0, ['BFCR.1.01'], 5),
            ('BFBIL6.5', 'تنقيب البيانات وتحليلها', 'Data Mining and Analysis', 3, 2, 2, ['BFCR.2.09'], 6),
            ('BFBIL6.6', 'إدارة قواعد البيانات', 'Database Management', 3, 2, 2, ['BFCR.2.08'], 6),
            ('BFBIL6.7', 'تحليل البيانات الضخمة', 'Big Data Analytics', 3, 2, 2, ['BFCR.1.03'], 6),
            ('BFBIL6.8', 'ذكاء الأعمال باستخدام لوحات المعلومات (Power BI / Tableau)', 'Business Intelligence using Dashboards (Power BI / Tableau)', 3, 2, 2, ['BFBIL5.1'], 6),
            ('BFBIL7.9', 'البرمجة وتحليل البيانات باستخدام SQL/R/Python', 'Programming and Data Analysis using SQL, R, and Python', 3, 2, 2, ['BFCR.2.08'], 7),
            ('BFBIL7.10', 'تقنيات إنترنت الأشياء والبلوك تشين', 'Internet of Things (IoT) and Blockchain Technologies', 3, 2, 2, ['BFCR.2.10'], 7),
            ('BFBIL7.11', 'الذكاء الاصطناعي التوليدي والتنبؤي في اتخاذ القرار', 'Generative and Predictive Artificial Intelligence in Decision Making', 3, 2, 2, ['BFCR.2.08'], 7),
            ('BFBIL7.12', 'نظم المعلومات الجغرافية في النقل والخدمات اللوجستية', 'Geographic Information Systems (GIS) in Transportation and Logistics', 3, 2, 2, ['BFBIL5.2'], 7),
            ('BFBIL7.13', 'مشروع تدريب مهني في ذكاء الأعمال والخدمات اللوجستية', 'Internship in BIL', 4, 2, 4, INTERNSHIP, 7),
            ('BFBIL8.14', 'الروبوتات والأتمتة في المستودعات', 'Robotics and Automation in Warehouses', 3, 2, 2, ['BFBIL5.3'], 8),
            ('BFBIL8.15', 'تقنية العملاء الأذكياء ونماذج ذكاء الأعمال', 'Intelligent Agents Technology and Business Intelligence Models', 3, 2, 2, ['BFBIL5.1'], 8),
            ('BFBIL8.16', 'التعلم الآلي والتعلم العميق في الأعمال', 'Machine Learning and Deep Learning in Business', 3, 2, 2, ['BFCR.3.11'], 8),
            ('BFBIL8.17', 'الأمن السيبراني والحوكمة السحابية', 'Cybersecurity and Cloud Governance', 3, 2, 2, ['BFBIL5.2'], 8),
            ('BFBIL8.18', 'مشروع تخرج في ذكاء الأعمال والخدمات اللوجستية', 'Graduation Project in BIL', 4, 2, 4, GRADUATION, 8),
        ],
        'electives': [
            ('BFBIL7.19', 'قضايا متقدمة في قواعد البيانات', 'Advanced Topics in Databases', 2, 2, ['BFBIL6.6']),
            ('BFBIL6.20', 'التوأمة الرقمية والخدمات اللوجستية', 'Digital Twin and Logistics', 2, 2, ['BFBIL5.3']),
            ('BFBIL6.21', 'إدارة الأزمات ومرونة سلاسل الإمداد', 'Crisis Management and Supply Chain Resilience', 3, 0, ['BFBIL5.3']),
            ('BFBIL8.22', 'التحليلات الجغرافية للأعمال', 'GeoBusiness Analytics', 2, 2, ['BFBIL7.12']),
            ('BFBIL8.23', 'التتبع الذكي وإدارة الأصول اللوجستية', 'Smart Tracking and Logistics Asset Management', 2, 2, ['BFBIL7.10']),
        ],
    },
]

ELECTIVE_POOLS = [
    {'id': 'university', 'requiredHours': 4,
     'descriptionAr': 'يختار الطالب مقررين (4 ساعات معتمدة) من متطلبات الجامعة الاختيارية.',
     'descriptionEn': 'Students choose two courses (4 credit hours) from the university elective requirements.'},
    {'id': 'faculty', 'requiredHours': 6,
     'descriptionAr': 'يختار الطالب مقررين (6 ساعات معتمدة) من متطلبات الكلية الاختيارية.',
     'descriptionEn': 'Students choose two courses (6 credit hours) from the faculty elective requirements.'},
    {'id': 'department', 'requiredHours': 6,
     'descriptionAr': 'يختار الطالب مقررين (6 ساعات معتمدة) من متطلبات البرنامج الاختيارية.',
     'descriptionEn': 'Students choose two courses (6 credit hours) from the program elective requirements.'},
]


def elective_term(prerequisites: list, terms_of: dict, slots: tuple) -> int:
    """The first elective slot level after every prerequisite; past the slots, the level after the latest one."""
    latest = max((terms_of[p] for p in prerequisites), default=0)
    for slot in slots:
        if slot > latest:
            return slot
    return latest + 1


def shared_terms() -> tuple[dict, dict]:
    terms = {t: [] for t in range(1, 9)}
    terms_of = {}

    for term, codes in SHARED_TERMS.items():
        for code in codes:
            if code in FACULTY:
                ar, en, cr, th, pr, pre = FACULTY[code]
                terms[term].append(course(code, ar, en, term, 'faculty', cr, th, pr, True, pre))
            else:
                ar, en, cr, th, pr, pre, _ = UNIVERSITY[code]
                terms[term].append(course(code, ar, en, term, 'university', cr, th, pr, True, pre))
            terms_of[code] = term

    for code, (ar, en, pre, term) in UNIVERSITY_ELECTIVES.items():
        terms[term].append(course(code, ar, en, term, 'university', 2, 2, 0, False, pre))
        terms_of[code] = term

    for code, (ar, en, th, pr, pre) in FACULTY_ELECTIVES.items():
        term = elective_term(pre, terms_of, FACULTY_ELECTIVE_TERMS)
        terms[term].append(course(code, ar, en, term, 'faculty', 3, th, pr, False, pre))
        terms_of[code] = term

    return terms, terms_of


def build() -> dict:
    shared, shared_terms_of = shared_terms()
    departments = []

    for program in PROGRAMS:
        terms = {t: [dict(c, prerequisites=list(c['prerequisites'])) for c in shared[t]] for t in shared}
        terms_of = dict(shared_terms_of)

        for code, ar, en, cr, th, pr, pre, term in program['courses']:
            credits = 0
            if pre == INTERNSHIP:
                pre = []
            elif pre == GRADUATION:
                pre, credits = [], GRADUATION_PROJECT_CREDITS
            terms[term].append(course(code, ar, en, term, 'specialization', cr, th, pr, True, pre, credits))
            terms_of[code] = term

        for code, ar, en, th, pr, pre in program['electives']:
            term = elective_term(pre, terms_of, PROGRAM_ELECTIVE_TERMS)
            if term not in PROGRAM_ELECTIVE_TERMS:
                late = ', '.join(p for p in pre if terms_of[p] >= PROGRAM_ELECTIVE_TERMS[-1])
                note(f'{program["id"]}: elective {code} needs {late}, taught in level {terms_of[late.split(", ")[0]]}, so it is listed in level {term} '
                     f'(the level tables put the program elective in levels 5 and 6).')
            terms[term].append(course(code, ar, en, term, 'specialization', 3, th, pr, False, pre))
            terms_of[code] = term

        departments.append({
            'id': program['id'],
            'nameEn': program['nameEn'],
            'nameAr': program['nameAr'],
            'summaryEn': program['summaryEn'],
            'summaryAr': DESCRIPTIONS['programs'][program['id']],
            'totalCredits': TOTAL_CREDITS,
            'electivePools': ELECTIVE_POOLS,
            'promotionRequirements': [],
            'terms': [{'id': t, 'labelEn': str(t), 'labelAr': str(t), 'courses': terms[t]} for t in range(1, 9)],
        })

    return {
        'source': 'كلية الأعمال - الخطة الدراسية 2026 (PDF, September 2026), Business Faculty, Syrian Private University.',
        'departments': departments,
    }


def report() -> None:
    note('Program ids kept from the old plan: hr = Smart Human Resource Management, accounting = Smart Accounting, marketing = Digital Marketing, finance = Finance Technology (FinTech), mis = Business Intelligence and Logistics. The old Business Administration program (management) is not in the 2026 plan.')
    note('University requirements use the UR codes and credits of the university requirements table: English Language (1) and (2) are 3 credit hours (the level tables say 2 and use the old codes REQU101/102/103/204). University electives are 2 credit hours each, two of them (4); the level 3 table says 3. They are listed in level 3, and English Language (3), which needs English (2) from level 3, in level 5, the level tables\' two university elective places. The second foreign language keeps the code UR-2-11/15 that the Requirements Unit uses for this faculty (the PDF writes UR-2-(11-15)).')
    note('Prerequisites written with old codes: REQU102 (BFCR.2.08) is UR-2-02 Computer Skills; ASCR.1.03 (BFCR.4.14) is BFCR.1.03 Mathematics for Business; REQU204 (BFCR.3.18) is UR-2-04 English Language (2).')
    note('Prerequisites whose name and code disagree in the PDF follow the name: "Principles of FinTech (1)" written as BFCR.3.13 is BFCR.3.12 (FinTech 5.04, 6.07, 7.10, 8.14, 8.19, 5.21); "Principles of Artificial Intelligence" written as BFCR.3.12 is BFCR.3.11 (FinTech 7.11, BIL 8.16); "Entrepreneurship and Innovation" written as BFCR.4.17 is BFCR.4.15 (FinTech 8.16); "Mathematics for Business" written as BFCR.2.28 is BFCR.1.03 (BIL 6.7).')
    note('Codes printed two ways in the PDF use the form of the level tables and prerequisites: BFSACC5.01-6.08 is BFSAC5.01-6.08, BFSHR709 is BFSHR7.09, "BFFinTech 6.05" and BFFinTech7.11 are BFFinTech.6.05 and BFFinTech.7.11 (prerequisites write the FinTech codes as BFFITE), BFBIL8.13 (requirements table) is BFBIL7.13 (level 7 table). Codes the PDF always writes without a leading zero are kept as written: BFSAC5.4, BFDM6.5-7.9, BFBIL5.1-7.9.')
    note('BFFinTech.6.08 RegTech and Digital Financial Compliance is missing from the FinTech requirements table but is in the level 6 table and in the course descriptions, and the table\'s totals (56 credits) count it; it is taken from the level 6 table.')
    note(f'Internships (level 7, 4 credits, 2 theory + 4 practical) need 90% of the faculty requirements, which is not a count of all completed credits, so no credit prerequisite is set. Graduation projects (level 8) need 80% of the plan: {GRADUATION_PROJECT_CREDITS} of {TOTAL_CREDITS} credits.')
    note('Faculty electives (choose two) are listed in level 3, the first faculty elective place, except English for Business and Accounting for Partnerships, whose prerequisites are in level 3: they are in level 4, the second place. Program electives (choose two) are listed in level 5, or level 6 when a prerequisite is in level 5.')
    note('English titles keep the PDF\'s wording with spelling fixed (Graduation, Management, Scientific, Language). Three titles where the English column contradicts the Arabic follow the Arabic and the level tables: BFSAC5.4 "Advanced Managerial Accounting" (requirements table: "Managerial Accounting2"), BFDM8.18 "Graduation Project in DM" (requirements table: "Graduation Project in Accounting"), BFDM6.21 "Green and Sustainable Marketing" (the English column says "Digital Marketing for Hospitality and Tourism"; the Arabic title and description are green marketing).')
    note('The DM graduation project level 8 table says 90% of the plan; the requirements table and section 8 say 80%, which is used.')
    note('Program summaries are each program\'s "academic identity" paragraph (section 16, item a). Course descriptions are chapter 4 of the PDF in Arabic (business-2026-descriptions.json); English descriptions and summaries are translations. The PDF has no promotion rules, so none are set.')
    note('Import manually after review: php artisan faculty:import-study-plan business-administration database/seeders/Data/study_plans/business-administration.json --user=<id> --replace-edited --write (saves an unpublished draft; publish it from the board).')


def check(data: dict) -> None:
    for department in data['departments']:
        courses = [c for t in department['terms'] for c in t['courses']]
        ids = [c['id'] for c in courses]
        assert len(ids) == len(set(ids)), department['id']
        term_of = {c['id']: c['term'] for c in courses}
        for c in courses:
            for pre in c['prerequisites']:
                assert pre in term_of, (department['id'], c['code'], pre)
                assert term_of[pre] < c['term'], (department['id'], c['code'], pre)
            assert c['descriptionAr'] and c['descriptionEn'] or c['type'] == 'university', c['code']
        required = sum(c['credits'] for c in courses if c['required'])
        # 134 = compulsory courses + 4 university elective + 6 faculty elective + 6 program elective credits
        assert required + 4 + 6 + 6 == TOTAL_CREDITS, (department['id'], required)
        program = [c for c in courses if c['type'] == 'specialization']
        assert sum(c['credits'] for c in program if c['required']) == 56, department['id']
        assert len([c for c in program if not c['required']]) == 5, department['id']


if __name__ == '__main__':
    data = build()
    check(data)
    report()
    (HERE / 'business-administration.json').write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (HERE / 'business-administration.report.md').write_text('# Conversion report\n\n' + '\n'.join(REPORT) + '\n', encoding='utf-8')
    print('wrote business-administration.json and business-administration.report.md')
