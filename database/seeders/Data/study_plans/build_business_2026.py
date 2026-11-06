#!/usr/bin/env python3
"""Builds business-administration.json and its report from the Faculty of Business 2026 study plan.

Usage: python3 -I build_business_2026.py   (writes both files next to it)

Source: "الخطة الدراسية - كلية الأعمال (2026)" (Markdown). It replaces the Faculty of Management
Sciences plan that convert_management_docx.py produced. Five programs, 134 credit hours each:
university 15, faculty 57, specialization 62.

The program ids of the old plan are kept so the departments page and staff profiles stay linked:
hr = Smart Human Resource Management, accounting = Smart Accounting, marketing = Digital Marketing,
finance = FinTech, mis = Business Intelligence and Logistics. The old Business Administration
program (management) has no successor and is dropped.

The source leaves gaps for the specialization years. Each one is filled as listed in the report
(never silently): English titles are translated, a specialization course's hours are its credits
(theory), specialization courses have no prerequisites, and each "department elective" is a
placeholder course, since the source lists none.

Import (manual, after review): php artisan faculty:import-study-plan business-administration
database/seeders/Data/study_plans/business-administration.json --user=<id> --replace-edited --write
"""
import json
import re
from pathlib import Path

HERE = Path(__file__).parent
REPORT = []


def note(line: str) -> None:
    REPORT.append(f"- {line}")


def course_id(code: str) -> str:
    """Lowercase URL-safe, as the study plan requires: BFCR.1.01 -> bfcr-1-01, UR-2-11/15 -> ur-2-11-15."""
    return re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')


def course(code, title_ar, title_en, term, kind, credits, theory, practical, required=True, prerequisites=(), description_ar='', description_en='', id_=None):
    return {
        'id': id_ or course_id(code),
        'code': code,
        'titleEn': title_en,
        'titleAr': title_ar,
        'term': term,
        'type': kind,
        'required': required,
        'forced': False,
        'credits': credits,
        'prerequisites': [course_id(p) for p in prerequisites],
        'prerequisiteCredits': 0,
        'instructor': None,
        'hours': {'theory': theory, 'practical': practical},
        'descriptionEn': description_en,
        'descriptionAr': description_ar,
        'lessons': [],
    }


# ── University requirements (15 CR: 11 compulsory, 4 elective) ───────────────
UNIVERSITY = {
    'UR-2-01': ('مهارات اللغة العربية', 'Arabic Language', 2, 2, 0, []),
    'UR-2-02': ('مهارات الحاسوب', 'Computer Skills', 3, 2, 2, []),
    'UR-2-03': ('اللغة الإنكليزية (1)', 'English Language (1)', 3, 3, 0, []),
    'UR-2-04': ('اللغة الإنكليزية (2)', 'English Language (2)', 3, 3, 0, ['UR-2-03']),
}
UNIVERSITY_ELECTIVES = [
    ('UR-2-10', 'اللغة الإنكليزية (3)', 'English Language (3)', ['UR-2-04']),
    ('UR-2-07', 'مهارات التفكير والبحث العلمي', 'Scientific Thinking', []),
    ('UR-2-11/15', 'لغة أجنبية حية ثانية (فرنسية/ألمانية/روسية/إسبانية/فارسية)', 'Second Foreign Language (French/German/Russian/Spanish/Persian)', []),
    ('UR-2-06', 'علم الاجتماع', 'Social Science', []),
    ('UR-2-05', 'مدخل إلى القانون', 'Introduction to Law', []),
    ('UR-2-08', 'أساسيات الإدارة ومهاراتها', 'Management Science', []),
    ('UR-2-09', 'علم البيئة', 'Environmental Science', []),
    ('UR-2-17', 'أخلاقيات وسلوكيات المهنة', 'Career Ethics and Behaviors', []),
    ('UR-2-16', 'المهارات الحركية والبدنية والذهنية', 'Motor, Physical and Mental Skills', []),
]

# ── Faculty requirements (57 CR: 51 compulsory, 6 elective) ───────────────────
# code: (Arabic, English, credits, theory, practical, prerequisites, Arabic description, English description)
FACULTY = {
    'BFCR.1.01': ('مبادئ التسويق', 'Principles of Marketing', 3, 3, 0, [],
                  'يقدم إطاراً متكاملاً لفهم العملية التسويقية وعناصر المزيج التسويقي والأسواق وسلوك العميل.',
                  'An integrated framework for understanding the marketing process, the marketing mix, markets and customer behavior.'),
    'BFCR.1.02': ('مبادئ الإدارة', 'Management Principles', 3, 3, 0, [],
                  'يتناول المفاهيم الأساسية للإدارة (تخطيط، تنظيم، قيادة، رقابة) واتخاذ القرارات والتنظيم الذكي.',
                  'The core concepts of management (planning, organizing, leading, controlling), decision making and smart organization.'),
    'BFCR.1.03': ('رياضيات الأعمال', 'Mathematics for Business', 3, 2, 2, [],
                  'يعطي الأدوات الجبرية والرياضية والقيمة الزمنية للنقود وتحليل النماذج الكمية.',
                  'Algebraic and mathematical tools, the time value of money and the analysis of quantitative models.'),
    'BFCR.1.04': ('اقتصاد الأعمال', 'Business Economics', 3, 2, 2, [],
                  'يغطي التحليل الجزئي (السوق والطلب والتكاليف) والتحليل الكلي (الناتج والمؤشرات والتضخم) والتحول الرقمي.',
                  'Microeconomic analysis (markets, demand and costs), macroeconomic analysis (output, indicators and inflation) and the digital transformation.'),
    'BFCR.1.05': ('إدارة المشاريع والتنمية المستدامة', 'Project Management and Sustainable Development', 3, 3, 0, [],
                  'يغطي مراحل دورة حياة المشروع والوقت والتكلفة والجودة وأبعاد الاستدامة البيئية والاقتصادية.',
                  'The stages of the project life cycle, time, cost and quality, and the environmental and economic dimensions of sustainability.'),
    'BFCR.2.06': ('مبادئ المحاسبة 1', 'Principles of Accounting 1', 3, 2, 2, [],
                  'يتناول المعادلة المحاسبية، التسجيل والترحيل وإعداد القوائم المالية والدورة المحاسبية.',
                  'The accounting equation, recording and posting, preparing financial statements and the accounting cycle.'),
    'BFCR.2.07': ('مبادئ إدارة الموارد البشرية', 'Principles of Human Resources Management', 3, 3, 0, [],
                  'يغطي التخطيط والاستقطاب والتدريب وإدارة الأداء والتعويضات والتحول الرقمي في الموارد البشرية.',
                  'Planning, recruitment, training, performance management, compensation and the digital transformation of human resources.'),
    'BFCR.2.08': ('مبادئ برمجة الأعمال', 'Principles of Programming for Business', 3, 2, 2, ['UR-2-02'],
                  'يركز على التفكير الخوارزمي والبرمجة ومعالجة بيانات الأعمال وتطبيقاتها.',
                  'Algorithmic thinking, programming, and processing business data and its applications.'),
    'BFCR.2.09': ('إحصاء الأعمال', 'Statistics for Management', 3, 2, 2, ['BFCR.1.03'],
                  'يتناول الإحصاء الوصفي والتحليلي والاحتمالات واختبار الفرضيات والارتباط والانحدار.',
                  'Descriptive and inferential statistics, probability, hypothesis testing, correlation and regression.'),
    'BFCR.2.10': ('تقنيات الثورة الصناعية الحديثة وتطبيقاتها', 'Applications of (4IR)', 3, 2, 2, [],
                  'يتناول الذكاء الاصطناعي، إنترنت الأشياء، البلوك تشين، الأتمتة، والتوأمة الرقمية.',
                  'Artificial intelligence, the Internet of Things, blockchain, automation and digital twins.'),
    'BFCR.3.11': ('مبادئ الذكاء الاصطناعي', 'Principles of Artificial Intelligence', 3, 2, 2, ['BFCR.2.09'],
                  'يغطي المفاهيم الأساسية للتعلم الآلي والتنبؤ والاستخدام المسؤول والتطبيقات الإدارية.',
                  'The core concepts of machine learning, prediction, responsible use and management applications.'),
    'BFCR.3.12': ('مبادئ تكنولوجيا مالية (1)', 'Principles of FinTech (1)', 3, 2, 2, ['BFCR.1.03'],
                  'يركز على نماذج أعمال FinTech والمدفوعات والابتكار المالي الرقمي والسيبرانية.',
                  'FinTech business models, payments, digital financial innovation and cybersecurity.'),
    'BFCR.3.13': ('مبادئ المحاسبة 2', 'Principles of Accounting 2', 3, 2, 2, ['BFCR.2.06'],
                  'يغطي المعالجات المتقدمة للأصول والالتزامات وحقوق الملكية والقوائم المالية.',
                  'Advanced treatment of assets, liabilities, equity and the financial statements.'),
    'BFCR.4.14': ('بحوث العمليات', 'Operations Research', 3, 2, 2, ['BFCR.1.03'],
                  'يغطي البرمجة الخطية، مسائل النقل والتخصيص، وتحسين استخدام الموارد.',
                  'Linear programming, transportation and assignment problems, and optimizing the use of resources.'),
    'BFCR.4.15': ('ريادة الأعمال والإبداع', 'Entrepreneurship and Innovation', 3, 3, 0, [],
                  'يركز على اكتشاف الفرص، صياغة نموذج العمل، والابتكار والتطوير.',
                  'Spotting opportunities, building a business model, and innovation and development.'),
    'BFCR.4.16': ('محاسبة إدارية ودراسات الجدوى الاقتصادية', 'Managerial Accounting and Economic Feasibility Studies', 3, 2, 2, ['BFCR.3.13'],
                  'يتناول سلوك التكاليف، نقطة التعادل، ومراحل دراسات الجدوى والتقييم.',
                  'Cost behavior, break-even analysis, and the stages of feasibility studies and evaluation.'),
    'BFCR.4.17': ('مناهج البحث العلمي', 'Research Methodology', 3, 3, 0, [],
                  'يغطي صياغة المشكلة، الفرضيات، جمع البيانات، التحليل، والأخلاقيات البحثية.',
                  'Framing the problem, hypotheses, data collection, analysis and research ethics.'),
}
# code: (Arabic, English, prerequisites, Arabic description, English description, term)
FACULTY_ELECTIVES = {
    'BFCR.1.19': ('مهارات الدخول لسوق العمل', 'Entering Labor Market Skills', [],
                  'كتابة السيرة الذاتية، اجتياز المقابلات، والتكيف المهني.',
                  'Writing a CV, getting through interviews, and adapting to the workplace.', 3),
    'BFCR.1.20': ('مهارات التواصل', 'Communication Skills', [],
                  'مهارات الاستماع والعرض والتواصل الرقمي وبناء العلاقات.',
                  'Listening, presenting, digital communication and relationship building.', 3),
    'BFCR.2.21': ('الإدارة الاستراتيجية', 'Strategic Management', ['BFCR.1.02'],
                  'تحليل البيئة الداخلية والخارجية وميزة التنافس وصياغة الاستراتيجية.',
                  'Analyzing the internal and external environment, competitive advantage and formulating strategy.', 3),
    'BFCR.2.23': ('تسويق الأعمال بين الشركات', 'B2B Marketing', ['BFCR.1.01'],
                  'سلوك الشراء التنظيمي، إدارة الحسابات والروابط بين المؤسسات.',
                  'Organizational buying behavior, account management and relationships between organizations.', 3),
    'BFCR.2.24': ('إدارة العمليات في المنظمات الذكية', 'Operations Management in Smart Organizations', ['BFCR.1.02'],
                  'تصميم العمليات، تحسين الجودة، والأتمتة في بيئة المنظمات الذكية.',
                  'Process design, quality improvement and automation in smart organizations.', 3),
    'BFCR.2.25': ('إدارة اللوجستيات', 'Logistics Management', ['BFCR.1.02'],
                  'النقل والتخزين والتوريد وسلاسل الإمداد ومؤشرات الأداء اللوجستي.',
                  'Transport, warehousing, procurement, supply chains and logistics performance indicators.', 3),
    'BFCR.3.18': ('إنكليزي الأعمال', 'English for Business', ['UR-2-04'],
                  'تطوير المهارات اللغوية والمراسلات التجارية والعروض باللغة الإنكليزية.',
                  'Language skills, business correspondence and presentations in English.', 4),
    'BFCR.4.22': ('محاسبة شركات الأشخاص', 'Accounting for Partnerships', ['BFCR.3.13'],
                  'المعالجات المحاسبية لتأسيس وتصفية وتوزيع أرباح شركات الأشخاص.',
                  'Accounting for the formation, liquidation and profit distribution of partnerships.', 4),
}

# Shared levels 1-4, in the source's level lists.
SHARED_TERMS = {
    1: ['BFCR.1.01', 'BFCR.1.02', 'BFCR.1.03', 'BFCR.1.04', 'BFCR.1.05', 'UR-2-02'],
    2: ['BFCR.2.06', 'BFCR.2.07', 'BFCR.2.08', 'BFCR.2.09', 'BFCR.2.10', 'UR-2-03'],
    3: ['BFCR.3.11', 'BFCR.3.12', 'BFCR.3.13', 'UR-2-04'],
    4: ['BFCR.4.14', 'BFCR.4.15', 'BFCR.4.16', 'BFCR.4.17', 'UR-2-01'],
}

# ── Specializations (62 CR each) ──────────────────────────────────────────────
# id, Arabic name, English name, Arabic summary, English summary, {level: [(code, Arabic, English, credits)]}
PROGRAMS = [
    ('hr', 'إدارة الموارد البشرية الذكية', 'Smart Human Resource Management',
     'يركز البرنامج على التحول الاستراتيجي لوظيفة الموارد البشرية مع دمج النظم الرقمية وتحليلات الأفراد (HR Analytics). تشمل المقررات: استقطاب المواهب، إدارة الأداء، التعويضات والرفاهية، قانون العمل والحوكمة، إدارة التغيير، والتنوع والعدالة والشمول.',
     'The program focuses on the strategic transformation of the HR function, integrating digital systems and people analytics (HR Analytics). Courses cover talent acquisition, performance management, compensation and well-being, labor law and governance, change management, and diversity, equity and inclusion.',
     {5: [('BFSHR5.01', 'إدارة الموارد البشرية في المنظمات الذكية', 'Human Resource Management in Smart Organizations', 3),
          ('BFSHR5.02', 'استقطاب المواهب وبناء العلامة التجارية لصاحب العمل', 'Talent Acquisition and Employer Branding', 3),
          ('BFSHR5.03', 'التعويضات والمكافآت ورفاهية العاملين', 'Compensation, Rewards and Employee Well-being', 3),
          ('BFSHR5.04', 'إدارة الأداء والقياس', 'Performance Management and Measurement', 3)],
      6: [('BFSHR6.05', 'التعلم والتطوير المستمر', 'Continuous Learning and Development', 3),
          ('BFSHR6.06', 'السلوك التنظيمي', 'Organizational Behavior', 3),
          ('BFSHR6.07', 'فن القيادة', 'The Art of Leadership', 3),
          ('BFSHR6.08', 'حل المشكلات واتخاذ القرار', 'Problem Solving and Decision Making', 3)],
      7: [('BFSHR7.09', 'إدارة التنوع والعدالة والشمول', 'Managing Diversity, Equity and Inclusion', 3),
          ('BFSHR7.10', 'التفاوض وإدارة النزاعات', 'Negotiation and Conflict Management', 3),
          ('BFSHR7.11', 'الإدارة الاستراتيجية للمواهب والتطوير التنظيمي', 'Strategic Talent Management and Organizational Development', 3),
          ('BFSHR7.12', 'إدارة المعرفة', 'Knowledge Management', 3),
          ('BFSHR7.13', 'مشروع تدريب مهني في إدارة الموارد البشرية', 'Professional Training Project in Human Resource Management', 4)],
      8: [('BFSHR8.14', 'نظم معلومات إدارة الموارد البشرية', 'Human Resource Information Systems', 3),
          ('BFSHR8.15', 'قانون العمل وحوكمة الموارد البشرية', 'Labor Law and HR Governance', 3),
          ('BFSHR8.16', 'قضايا معاصرة في إدارة الموارد البشرية', 'Contemporary Issues in Human Resource Management', 3),
          ('BFSHR8.17', 'التحليلات التنبؤية للموارد البشرية', 'Predictive HR Analytics', 3),
          ('BFSHR8.18', 'مشروع تخرج في إدارة الموارد البشرية الذكية', 'Graduation Project in Smart Human Resource Management', 4)]}),
    ('accounting', 'المحاسبة الذكية', 'Smart Accounting',
     'يجمع بين المعايير الدولية (IFRS/IPSAS)، الحوكمة، التدقيق، والتحليل المالي المتقدم مع التقنيات الحديثة مثل أتمتة البيانات بـ Excel/VBA، التحليلات التنبؤية، ونظم ERP، لمساعدة المحاسب في تقديم خدمات استشارية ودعم اتخاذ القرار.',
     'Combines international standards (IFRS/IPSAS), governance, auditing and advanced financial analysis with modern technologies such as data automation in Excel/VBA, predictive analytics and ERP systems, so that accountants can offer advisory services and support decision making.',
     {5: [('BFSAC5.01', 'محاسبة متوسطة I', 'Intermediate Accounting I', 3),
          ('BFSAC5.02', 'محاسبة تكاليف', 'Cost Accounting', 3),
          ('BFSAC5.03', 'محاسبة ضريبية', 'Tax Accounting', 3),
          ('BFSAC5.04', 'محاسبة إدارية متقدمة', 'Advanced Managerial Accounting', 3)],
      6: [('BFSAC6.05', 'محاسبة متوسطة II', 'Intermediate Accounting II', 3),
          ('BFSAC6.06', 'الحوكمة والتدقيق 1', 'Governance and Auditing 1', 3),
          ('BFSAC6.07', 'محاسبة شركات الأموال', 'Corporate Accounting', 3),
          ('BFSAC6.08', 'نظم المعلومات المحاسبية', 'Accounting Information Systems', 3)],
      7: [('BFSAC7.09', 'تحليل القوائم المالية', 'Financial Statement Analysis', 3),
          ('BFSAC7.10', 'الحوكمة والتدقيق 2', 'Governance and Auditing 2', 3),
          ('BFSAC7.11', 'تطبيقات الحاسوب في المحاسبة والتدقيق', 'Computer Applications in Accounting and Auditing', 3),
          ('BFSAC7.12', 'محاسبة المؤسسات المالية والمصرفية', 'Accounting for Financial and Banking Institutions', 3),
          ('BFSAC7.13', 'مشروع تدريب مهني في المحاسبة الذكية', 'Professional Training Project in Smart Accounting', 4)],
      8: [('BFSAC8.14', 'التحليلات التنبؤية في المحاسبة والتدقيق', 'Predictive Analytics in Accounting and Auditing', 3),
          ('BFSAC8.15', 'محاسبة توحيد الأعمال', 'Business Combinations Accounting', 3),
          ('BFSAC8.16', 'معايير المحاسبة الدولية', 'International Accounting Standards', 3),
          ('BFSAC8.17', 'محاسبة مالية متخصصة', 'Specialized Financial Accounting', 3),
          ('BFSAC8.18', 'مشروع تخرج في المحاسبة الذكية', 'Graduation Project in Smart Accounting', 4)]}),
    ('marketing', 'التسويق الرقمي', 'Digital Marketing',
     'يهتم ببناء استراتيجيات تسويق قائمة على البيانات والذكاء الاصطناعي، وإدارة السمعة الرقمية والعلامات التجارية، الحملات المأجورة عبر محركات البحث وشبكات التواصل، وتحليل سلوك المستهلك الرقمي واستراتيجيات المحتوى.',
     'Building marketing strategies driven by data and artificial intelligence, managing digital reputation and brands, paid campaigns on search engines and social networks, and analyzing digital consumer behavior and content strategies.',
     {5: [('BFDM5.01', 'إدارة التسويق', 'Marketing Management', 3),
          ('BFDM5.02', 'سلوك المستهلك عبر الإنترنت', 'Online Consumer Behavior', 3),
          ('BFDM5.03', 'مدخل إلى التسويق الإلكتروني', 'Introduction to E-Marketing', 3),
          ('BFDM5.04', 'إدارة المبيعات والبيع الشخصي', 'Sales Management and Personal Selling', 3)],
      6: [('BFDM6.05', 'التسويق الاستراتيجي', 'Strategic Marketing', 3),
          ('BFDM6.06', 'إدارة المنتج والعلامة التجارية الرقمية', 'Digital Product and Brand Management', 3),
          ('BFDM6.07', 'نظم المعلومات التسويقية وبحوث التسويق', 'Marketing Information Systems and Marketing Research', 3),
          ('BFDM6.08', 'إدارة العلاقة مع العملاء', 'Customer Relationship Management', 3)],
      7: [('BFDM7.09', 'التسويق عبر شبكات التواصل الاجتماعي', 'Social Media Marketing', 3),
          ('BFDM7.10', 'التسويق باستخدام محركات البحث', 'Search Engine Marketing', 3),
          ('BFDM7.11', 'الاتصالات التسويقية الإلكترونية المتكاملة', 'Integrated Digital Marketing Communications', 3),
          ('BFDM7.12', 'تطبيقات الذكاء الاصطناعي في التسويق', 'Artificial Intelligence Applications in Marketing', 3),
          ('BFDM7.13', 'مشروع تدريب مهني في التسويق الرقمي', 'Professional Training Project in Digital Marketing', 4)],
      8: [('BFDM8.14', 'التسويق الدولي في البيئة الرقمية', 'International Marketing in the Digital Environment', 3),
          ('BFDM8.15', 'استراتيجية المحتوى والتسويق الرقمي', 'Content Strategy and Digital Marketing', 3),
          ('BFDM8.16', 'استراتيجيات التسعير والتوزيع في البيئة الرقمية', 'Pricing and Distribution Strategies in the Digital Environment', 3),
          ('BFDM8.17', 'تحليل البيانات التسويقية', 'Marketing Data Analysis', 3),
          ('BFDM8.18', 'مشروع تخرج في التسويق الرقمي', 'Graduation Project in Digital Marketing', 4)]}),
    ('finance', 'التكنولوجيا المالية', 'Financial Technology (FinTech)',
     'يركز على تقاطع المالية والتكنولوجيا، من خلال دراسة خدمات المدفوعات، البلوك تشين، العقود الذكية، التكنولوجيا التنظيمية (RegTech)، تحليلات الاحتيال المالي، الذكاء الاصطناعي في المالية، والتمويل اللامركزي (DeFi).',
     'Focuses on where finance meets technology: payment services, blockchain, smart contracts, regulatory technology (RegTech), financial fraud analytics, artificial intelligence in finance and decentralized finance (DeFi).',
     {5: [('BFFinTech.5.01', 'إدارة المصارف', 'Bank Management', 3),
          ('BFFinTech.5.02', 'مبادئ إدارة الأسواق والمؤسسات المالية', 'Principles of Financial Markets and Institutions Management', 3),
          ('BFFinTech.5.03', 'إدارة مالية في الشركات المساهمة', 'Corporate Financial Management', 3),
          ('BFFinTech.5.04', 'المدفوعات الرقمية والتكنولوجيا المصرفية', 'Digital Payments and Banking Technology', 3)],
      6: [('BFFinTech.6.05', 'التحليل المالي', 'Financial Analysis', 3),
          ('BFFinTech.6.06', 'المصارف الإسلامية', 'Islamic Banking', 3),
          ('BFFinTech.6.07', 'الخدمات المصرفية الرقمية والتكنولوجيا المالية', 'Digital Banking and Financial Technology', 3),
          ('BFFinTech.6.08', 'التكنولوجيا التنظيمية والامتثال المالي الرقمي (RegTech)', 'Regulatory Technology and Digital Financial Compliance (RegTech)', 3)],
      7: [('BFFinTech.7.09', 'محاسبة المؤسسات المالية والمصرفية', 'Accounting for Financial and Banking Institutions', 3),
          ('BFFinTech.7.10', 'إدارة المحافظ الاستثمارية', 'Investment Portfolio Management', 3),
          ('BFFinTech.7.11', 'الذكاء الاصطناعي في العلوم المالية', 'Artificial Intelligence in Finance', 3),
          ('BFFinTech.7.12', 'إدارة المخاطر المالية', 'Financial Risk Management', 3),
          ('BFFinTech.7.13', 'مشروع تدريب مهني في التكنولوجيا المالية', 'Professional Training Project in Financial Technology', 4)],
      8: [('BFFinTech.8.14', 'المشتقات المالية والهندسة المالية', 'Financial Derivatives and Financial Engineering', 3),
          ('BFFinTech.8.23', 'تحليلات الاحتيال المالي الرقمي', 'Digital Financial Fraud Analytics', 3),
          ('BFFinTech.8.16', 'نماذج الأعمال والابتكار في التكنولوجيا المالية', 'Business Models and Innovation in Financial Technology', 3),
          ('BFFinTech.8.17', 'البلوك تشين والتطبيقات المالية', 'Blockchain and Financial Applications', 3),
          ('BFFinTech.8.18', 'مشروع تخرج في التكنولوجيا المالية', 'Graduation Project in Financial Technology', 4)]}),
    ('mis', 'ذكاء الأعمال والخدمات اللوجستية', 'Business Intelligence and Logistics',
     'يغطي الدورة الكاملة لبيانات سلاسل الإمداد، بدءاً من التخزين وقواعد البيانات الضخمة والنماذج التنبؤية (SQL, Python, Power BI) وصولاً إلى التطبيق اللوجستي عبر أنظمة GIS، أتمتة المستودعات بالروبوتات، والتتبع الذكي للأصول.',
     'Covers the whole supply chain data cycle, from storage, big databases and predictive models (SQL, Python, Power BI) to logistics practice with GIS, robotic warehouse automation and smart asset tracking.',
     {5: [('BFBIL5.01', 'مدخل إلى ذكاء الأعمال والخدمات اللوجستية', 'Introduction to Business Intelligence and Logistics', 3),
          ('BFBIL5.02', 'نظم المعلومات الذكية والحوسبة السحابية', 'Smart Information Systems and Cloud Computing', 3),
          ('BFBIL5.03', 'إدارة سلاسل الإمداد والخدمات اللوجستية', 'Supply Chain and Logistics Management', 3),
          ('BFBIL5.04', 'التجارة الإلكترونية والخدمات الرقمية', 'E-Commerce and Digital Services', 3)],
      6: [('BFBIL6.05', 'تنقيب البيانات وتحليلها', 'Data Mining and Analysis', 3),
          ('BFBIL6.06', 'إدارة قواعد البيانات', 'Database Management', 3),
          ('BFBIL6.07', 'تحليل البيانات الضخمة', 'Big Data Analytics', 3),
          ('BFBIL6.08', 'ذكاء الأعمال باستخدام لوحات المعلومات (Power BI / Tableau)', 'Business Intelligence with Dashboards (Power BI / Tableau)', 3)],
      7: [('BFBIL7.09', 'البرمجة وتحليل البيانات باستخدام SQL/R/Python', 'Programming and Data Analysis with SQL/R/Python', 3),
          ('BFBIL7.10', 'تقنيات إنترنت الأشياء والبلوك تشين', 'Internet of Things and Blockchain Technologies', 3),
          ('BFBIL7.11', 'الذكاء الاصطناعي التوليدي والتنبؤي في اتخاذ القرار', 'Generative and Predictive AI in Decision Making', 3),
          ('BFBIL7.12', 'نظم المعلومات الجغرافية (GIS) في النقل والخدمات اللوجستية', 'Geographic Information Systems (GIS) in Transport and Logistics', 3),
          ('BFBIL7.13', 'مشروع تدريب مهني في ذكاء الأعمال والخدمات اللوجستية', 'Professional Training Project in Business Intelligence and Logistics', 4)],
      8: [('BFBIL8.14', 'الروبوتات والأتمتة في المستودعات', 'Robotics and Warehouse Automation', 3),
          ('BFBIL8.15', 'تقنية العملاء الأذكياء ونماذج ذكاء الأعمال', 'Intelligent Agents and Business Intelligence Models', 3),
          ('BFBIL8.16', 'التعلم الآلي والتعلم العميق في الأعمال', 'Machine Learning and Deep Learning in Business', 3),
          ('BFBIL8.17', 'الأمن السيبراني والحوكمة السحابية', 'Cybersecurity and Cloud Governance', 3),
          ('BFBIL8.18', 'مشروع تخرج في ذكاء الأعمال والخدمات اللوجستية', 'Graduation Project in Business Intelligence and Logistics', 4)]}),
]


def shared_terms() -> dict:
    terms = {}

    for term, codes in SHARED_TERMS.items():
        courses = []
        for code in codes:
            if code in FACULTY:
                ar, en, cr, th, pr, pre, dar, den = FACULTY[code]
                courses.append(course(code, ar, en, term, 'faculty', cr, th, pr, True, pre, dar, den))
            else:
                ar, en, cr, th, pr, pre = UNIVERSITY[code]
                courses.append(course(code, ar, en, term, 'university', cr, th, pr, True, pre))
        terms[term] = courses

    for code, (ar, en, pre, dar, den, term) in FACULTY_ELECTIVES.items():
        terms[term].append(course(code, ar, en, term, 'faculty', 3, 3, 0, False, pre, dar, den))

    for code, ar, en, pre in UNIVERSITY_ELECTIVES:
        if code == 'UR-2-10':
            continue  # it follows English (2), so it is offered with the specialization's level 5
        terms[3].append(course(code, ar, en, 3, 'university', 2, 2, 0, False, pre))

    return terms


def build() -> dict:
    shared = shared_terms()
    departments = []
    ur210 = next(e for e in UNIVERSITY_ELECTIVES if e[0] == 'UR-2-10')

    for program_id, name_ar, name_en, summary_ar, summary_en, levels in PROGRAMS:
        terms = []
        for term in range(1, 9):
            if term <= 4:
                courses = [dict(c, prerequisites=list(c['prerequisites'])) for c in shared[term]]
            else:
                courses = [course(code, ar, en, term, 'specialization', cr, cr, 0) for code, ar, en, cr in levels[term]]
                if term in (5, 6):
                    courses.append(course('', 'متطلب قسم اختياري', 'Department elective', term, 'specialization', 3, 3, 0, False,
                                          id_=f'{program_id}-department-elective-{term}'))
                if term == 5:
                    code, ar, en, pre = ur210
                    courses.append(course(code, ar, en, term, 'university', 2, 2, 0, False, pre))
            terms.append({'id': term, 'labelEn': str(term), 'labelAr': str(term), 'courses': courses})

        departments.append({
            'id': program_id,
            'nameEn': name_en,
            'nameAr': name_ar,
            'summaryEn': summary_en,
            'summaryAr': summary_ar,
            'totalCredits': 134,
            'electivePools': [],
            'terms': terms,
        })

    return {
        'source': 'الخطة الدراسية - كلية الأعمال (2026), Faculty of Business, Syrian Private University. Replaces the Faculty of Management Sciences plan.',
        'departments': departments,
    }


def report() -> None:
    note('Program ids kept from the old plan: hr = Smart Human Resource Management, accounting = Smart Accounting, marketing = Digital Marketing, finance = FinTech, mis = Business Intelligence and Logistics.')
    note('The old Business Administration program (management) has no successor and is not in the new plan. The Business departments page links its "management" department to it, and staff may have specialization_id = management: an editor must relink them or make the department a section.')
    note('Prerequisite codes from the old numbering in the source: REQU102 (BFCR.2.08) read as UR-2-02 Computer Skills; ASCR.1.03 (BFCR.4.14) read as BFCR.1.03 Mathematics for Business; REQU204 (BFCR.3.18) read as UR-2-04 English Language (2).')
    note('English Language (1) and (2) are 3 credit hours as in the university requirements table; the level lists say 2.')
    note('The level 3 list has a university elective of 3 credit hours; university electives are 2 credit hours each (4 in total). Their options are listed in level 3, and English Language (3), which follows English (2), in level 5 with the second university elective slot.')
    note('Faculty electives (6 credit hours, two courses) are listed in level 3, except English for Business and Accounting for Partnerships, whose prerequisites are in level 3, listed in level 4. The source gives them credits only: hours are 3 theory.')
    note('Specialization courses: the source gives no English titles (translated here), no theory/practical split (hours are the credits, theory only) and no prerequisites (none set). Check each on the study plan board.')
    note('Department electives: the source names a 3 credit hour department elective in levels 5 and 6 but lists no courses, so each is a placeholder course "Department elective" without a code.')
    note('University and faculty courses keep the source\'s Arabic descriptions; English descriptions are translations. Specialization courses have no descriptions.')
    note('Import manually after review: php artisan faculty:import-study-plan business-administration database/seeders/Data/study_plans/business-administration.json --user=<id> --replace-edited --write (saves an unpublished draft; publish it from the board).')


def check(data: dict) -> None:
    for department in data['departments']:
        ids = [c['id'] for t in department['terms'] for c in t['courses']]
        assert len(ids) == len(set(ids)), department['id']
        known = set(ids)
        for term in department['terms']:
            for c in term['courses']:
                for pre in c['prerequisites']:
                    assert pre in known, (department['id'], c['code'], pre)
        required = sum(c['credits'] for t in department['terms'] for c in t['courses'] if c['required'])
        # 134 = required courses + 4 university elective + 6 faculty elective + 6 department elective credits
        assert required + 4 + 6 + 6 == 134, (department['id'], required)


if __name__ == '__main__':
    data = build()
    check(data)
    report()
    (HERE / 'business-administration.json').write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (HERE / 'business-administration.report.md').write_text('# Conversion report\n\n' + '\n'.join(REPORT) + '\n', encoding='utf-8')
    print('wrote business-administration.json and business-administration.report.md')
