"""Builds pharmacy.json: the Faculty of Pharmacy's master's program page.

Source: the faculty's request to open the Professional Master's in Pharmaceutics and
Pharmaceutical Technology (2026). Like the AI page, it keeps what a student needs:
overview, structure, courses, learning outcomes, assessment, the thesis and training,
and admission. The proposal's justification, mission, vision, objectives, graduate
profile, capacity, equipment, staffing, governance and KPIs are left out. The source is
Arabic only; English is a translation. It gives course hours but no per-course credits,
descriptions or learning outcomes, so the compulsory courses show their hours.

Run: python3 build_pharmacy.py  (writes the JSON next to it)
"""

import json
from pathlib import Path

# code, theory, practical, Arabic title, English title
COMPULSORY = [
    ("PT-601", 2, 2, "الصيدلة الصناعية", "Industrial Pharmacy"),
    ("PT-602", 2, 2, "التكنولوجيا الصيدلانية", "Pharmaceutical Technology"),
    ("PT-603", 2, 0, "الصيدلة الفيزيائية", "Physical Pharmacy"),
    ("PT-604", 2, 1, "الصيدلة الحيوية والحركية الدوائية", "Biopharmaceutics and Pharmacokinetics"),
    ("PT-605", 1, 2, "تصميم الأشكال الصيدلانية الصلبة", "Design of Solid Dosage Forms"),
    ("PT-606", 1, 2, "تصميم الأشكال السائلة وشبه الصلبة", "Design of Liquid and Semi-solid Dosage Forms"),
    ("PT-607", 2, 0, "أنظمة إيصال الدواء الحديثة", "Modern Drug Delivery Systems"),
    ("PT-608", 2, 0, "ضمان الجودة والمراقبة الدوائية (GMP)", "Quality Assurance and Drug Control (GMP)"),
    ("PT-609", 1, 2, "التحليل الصيدلاني التطبيقي", "Applied Pharmaceutical Analysis"),
    ("PT-610", 1, 1, "دراسات الثباتية الدوائية", "Drug Stability Studies"),
    ("PT-611", 1, 1, "منهجيات البحث والإحصاء الحيوي", "Research Methodology and Biostatistics"),
    ("PT-612", 2, 0, "الصيدلة الجرثومية", "Pharmaceutical Microbiology"),
]

# code, credits, Arabic title, English title
ELECTIVES = [
    ("PT-621", 2, "التكنولوجيا الحيوية الصيدلانية", "Pharmaceutical Biotechnology"),
    ("PT-623", 2, "الذكاء الاصطناعي وتطوير المستحضرات الدوائية", "Artificial Intelligence in Pharmaceutical Product Development"),
    ("PT-624", 2, "علم البوليميرات الصيدلانية", "Pharmaceutical Polymer Science"),
    ("PT-625", 2, "تقنيات النانو في الصيدلة", "Nanotechnology in Pharmacy"),
    ("PT-626", 2, "الشؤون التنظيمية وتسجيل الأدوية", "Regulatory Affairs and Drug Registration"),
]

TRAINING = [
    ("PT-631", 4, "التدريب العملي المتقدم: مختبرات وخطوط إنتاج", "Advanced Practical Training: Laboratories and Production Lines"),
    ("PT-699", 10, "رسالة الماجستير", "Master's Thesis"),
]

# group (ar, en), outcomes [(ar, en)]
OUTCOMES = [
    (("المعارف والفهم", "Knowledge and understanding"), [
        ("يشرح المبادئ العلمية المتقدمة في الصيدلانيات وفيزياء الأشكال الصيدلانية.",
         "Explain the advanced scientific principles of pharmaceutics and the physics of dosage forms."),
        ("يصف تقنيات التصنيع الدوائي وأنظمة إيصال الدواء الحديثة.",
         "Describe pharmaceutical manufacturing technologies and modern drug delivery systems."),
        ("يوضح متطلبات ضمان الجودة وممارسات التصنيع الجيد والدلائل التنظيمية.",
         "Explain the requirements of quality assurance, good manufacturing practice and regulatory guidelines."),
    ]),
    (("المهارات الذهنية", "Intellectual skills"), [
        ("يحلل المشكلات التقنية في تصميم الصيغ الدوائية ويقترح حلولاً مبنية على الدليل.",
         "Analyze technical problems in formulation design and propose evidence-based solutions."),
        ("يقيم جودة المستحضرات وثباتيتها وتوافرها الحيوي ويفسر البيانات.",
         "Evaluate the quality, stability and bioavailability of products and interpret the data."),
        ("يصمم دراسات بحثية تطبيقية ويحلل نتائجها إحصائياً.",
         "Design applied research studies and analyze their results statistically."),
    ]),
    (("المهارات المهنية والعملية", "Professional and practical skills"), [
        ("يصمم ويحضر الأشكال الصيدلانية المختلفة مخبرياً وصناعياً.",
         "Design and prepare the various dosage forms at laboratory and industrial scale."),
        ("يطبق التقنيات التحليلية الآلية في مراقبة الجودة.",
         "Apply instrumental analytical techniques in quality control."),
        ("يوثق العمليات ويطبق إجراءات ضمان الجودة وفق GMP.",
         "Document processes and apply quality assurance procedures in line with GMP."),
    ]),
    (("المهارات العامة والمنقولة", "General and transferable skills"), [
        ("يستخدم مصادر المعلومات والتقنيات الرقمية في التعلم المستمر.",
         "Use information sources and digital technologies for lifelong learning."),
        ("يلتزم بأخلاقيات البحث والمهنة والمسؤولية المجتمعية.",
         "Uphold research and professional ethics and social responsibility."),
    ]),
]

# type (ar, en), weight, targeted outcomes (ar, en)
ASSESSMENT = [
    (("امتحانات نظرية تحريرية", "Written theory exams"), "30%",
     ("تقيس المعارف والمهارات الذهنية.", "Assess knowledge and intellectual skills.")),
    (("امتحانات ومهام عملية مخبرية", "Practical laboratory exams and tasks"), "25%",
     ("تقيس المهارات المهنية والعملية.", "Assess professional and practical skills.")),
    (("مشاريع وتقارير بحثية", "Research projects and reports"), "20%",
     ("تقيس المهارات الذهنية والمنقولة.", "Assess intellectual and transferable skills.")),
    (("عروض تقديمية علمية", "Scientific presentations"), "10%",
     ("تقيس المهارات العامة والتواصل.", "Assess general and communication skills.")),
    (("تقييم التدريب الميداني", "Field training evaluation"), "15%",
     ("يقيس المهارات المهنية والمنقولة.", "Assesses professional and transferable skills.")),
]

CONDITIONS = [
    ("أن يكون حاصلاً على إجازة في الصيدلة (بكالوريوس صيدلة من جامعة معترف بها).",
     "Hold a degree in pharmacy (a bachelor's in pharmacy from a recognized university)."),
    ("استيفاء الحد الأدنى من المعدل التراكمي وفق لائحة الدراسات العليا في الجامعة.",
     "Meet the minimum cumulative average set by the university's graduate studies regulations."),
    ("اجتياز مقابلة القبول و/أو اختبار القبول إن وجد.",
     "Pass the admission interview and/or the admission test, where there is one."),
    ("استيفاء متطلبات إتقان اللغة الإنجليزية وفق معايير الجامعة.",
     "Meet the university's English language proficiency requirements."),
    ("تقديم الوثائق الرسمية المطلوبة كاملة ضمن المدة المحددة.",
     "Submit all the required official documents within the set period."),
]

CRITERIA = [
    (("المعدل التراكمي في مرحلة الإجازة", "Bachelor's cumulative average"), "50%"),
    (("نتيجة مقابلة / اختبار القبول", "Admission interview / test result"), "25%"),
    (("الخبرة العملية ذات الصلة (الصناعة الدوائية)", "Relevant work experience (pharmaceutical industry)"), "15%"),
    (("النشاط البحثي والعلمي", "Research and scientific activity"), "10%"),
]

TEXT = {
    "ar": {
        "title": "ماجستير التأهيل والتخصص في الصيدلانيات وتكنولوجيا الصيدلة",
        "summary": "برنامج ماجستير تأهيل وتخصص في كلية الصيدلة يُعدّ متخصصين في تصميم الأدوية وتطويرها وتصنيعها وضبط جودتها، مدته ثلاث سنوات وبإجمالي 45 ساعة معتمدة.",
        "seoTitle": "ماجستير الصيدلانيات وتكنولوجيا الصيدلة | كلية الصيدلة",
        "seoDescription": "الخطة الدراسية لماجستير التأهيل والتخصص في الصيدلانيات وتكنولوجيا الصيدلة في الجامعة السورية الخاصة: المقررات، ومخرجات التعلم، والتقييم، والرسالة والتدريب، وشروط القبول.",
        "overview": [
            "يشهد قطاع الصناعة الدوائية في الجمهورية العربية السورية نمواً متسارعاً وحاجةً متزايدة إلى كوادر علمية متخصصة قادرة على مواكبة التطورات التقنية في مجالات تصميم الأدوية وتصنيعها وضبط جودتها.",
            "يُعد هذا البرنامج استجابة مباشرةً لاحتياجات الصناعة الدوائية الوطنية، ويهدف إلى إعداد متخصصين يجمعون بين الأساس العلمي الرصين للصيدلانيات والتطبيق التقني المتقدم في التكنولوجيا الصيدلية، بما يتوافق مع المعايير الأكاديمية الدولية ومتطلبات ضمان الجودة في التعليم العالي.",
        ],
        "facts": [
            ("الدرجة العلمية", "ماجستير تأهيل وتخصص"),
            ("مدة الدراسة", "ثلاث سنوات"),
            ("الساعات المعتمدة", "45 ساعة"),
            ("رسالة الماجستير", "10 ساعات معتمدة"),
        ],
        "coding": "التوزيع الزمني: السنة الأولى للمقررات النظرية والمهارات المتقدمة (فصلان دراسيان)، والسنة الثانية للمقررات التطبيقية والاختيارية والتدريب العملي في المختبرات وخطوط الإنتاج، والسنة الثالثة لإعداد رسالة الماجستير ومناقشتها.",
        "terms": {
            "compulsory": ("المقررات الإجبارية", "", ""),
            "electives": ("المقررات الاختيارية", "", "يختار الطالب منها مقررات بما يعادل 4 ساعات معتمدة."),
            "thesis": ("التدريب العملي والرسالة", "", ""),
        },
        "projectTitle": "رسالة الماجستير والتدريب العملي",
        "projectIntro": "يُتمّ الطالب التدريب العملي المتقدم في المختبرات وخطوط الإنتاج خلال السنة الثانية، ويُعِدّ رسالة الماجستير ويناقشها في السنة الثالثة.",
        "projectRules": [
            "التدريب العملي المتقدم في المختبرات وخطوط الإنتاج (PT-631) بأربع ساعات معتمدة.",
            "رسالة الماجستير (PT-699) بعشر ساعات معتمدة.",
            "تُقيّم رسالة الماجستير من قبل لجنة حكم متخصصة وفق معايير معلنة.",
        ],
        "teachingNote": "يعتمد نظام الدرجات التراكمي وفق لائحة الدراسات العليا في الجامعة، وتُنوَّع أساليب التقييم لتغطي جميع مجالات مخرجات التعلم.",
    },
    "en": {
        "title": "Professional Master's in Pharmaceutics and Pharmaceutical Technology",
        "summary": "A professional master's program at the Faculty of Pharmacy that prepares specialists in drug design, development, manufacturing and quality control, over three years and 45 credit hours.",
        "seoTitle": "Master's in Pharmaceutics & Pharmaceutical Technology | Faculty of Pharmacy",
        "seoDescription": "Study plan of the Professional Master's in Pharmaceutics and Pharmaceutical Technology at Syrian Private University: courses, learning outcomes, assessment, thesis and training, and admission requirements.",
        "overview": [
            "The pharmaceutical industry in Syria is growing fast and increasingly needs specialists able to keep pace with technical advances in drug design, manufacturing and quality control.",
            "The program answers the needs of the national pharmaceutical industry directly. It prepares specialists who combine a sound scientific grounding in pharmaceutics with advanced technical practice in pharmaceutical technology, in line with international academic standards and quality assurance requirements in higher education.",
        ],
        "facts": [
            ("Degree", "Professional Master's"),
            ("Duration", "Three years"),
            ("Credit hours", "45 hours"),
            ("Master's thesis", "10 credit hours"),
        ],
        "coding": "Timeline: year one covers the theory courses and advanced skills (two semesters); year two the applied and elective courses and practical training in laboratories and on production lines; year three the preparation and defense of the master's thesis.",
        "terms": {
            "compulsory": ("Compulsory courses", "", ""),
            "electives": ("Elective courses", "", "Students choose courses worth 4 credit hours."),
            "thesis": ("Practical training and thesis", "", ""),
        },
        "projectTitle": "Master's thesis and practical training",
        "projectIntro": "Students complete advanced practical training in laboratories and on production lines in year two, then prepare and defend their master's thesis in year three.",
        "projectRules": [
            "Advanced practical training in laboratories and on production lines (PT-631), 4 credit hours.",
            "Master's thesis (PT-699), 10 credit hours.",
            "The master's thesis is assessed by a specialized examination committee against published criteria.",
        ],
        "teachingNote": "Grades follow the cumulative system of the university's graduate studies regulations, and assessment methods are varied to cover every domain of the learning outcomes.",
    },
}


def course(code, title_ar, title_en, locale, credits=0, theory=0, practical=0, elective=False):
    ar = locale == "ar"
    return {
        "id": code,
        "code": code,
        "title": title_ar if ar else title_en,
        "titleAlt": title_en if ar else title_ar,
        "elective": elective,
        "credits": credits,
        "theory": theory,
        "practical": practical,
        "prerequisites": [],
        "prerequisiteNote": "",
        "description": "",
        "outcomes": [],
        "grading": "",
        "coverage": {},
    }


def build(locale: str) -> dict:
    ar = locale == "ar"
    i = 0 if ar else 1
    text = TEXT[locale]
    terms = [
        ("compulsory", 27, [course(c, tar, ten, locale, theory=t, practical=p) for c, t, p, tar, ten in COMPULSORY]),
        ("electives", 4, [course(c, tar, ten, locale, credits=cr, elective=True) for c, cr, tar, ten in ELECTIVES]),
        ("thesis", None, [course(c, tar, ten, locale, credits=cr) for c, cr, tar, ten in TRAINING]),
    ]

    return {
        "title": text["title"],
        "summary": text["summary"],
        "seoTitle": text["seoTitle"],
        "seoDescription": text["seoDescription"],
        "payload": {
            "overview": [{"text": p} for p in text["overview"]],
            "facts": [{"label": label, "value": value} for label, value in text["facts"]],
            "coding": text["coding"],
            "terms": [
                {
                    "id": term_id,
                    "label": text["terms"][term_id][0],
                    "theme": text["terms"][term_id][1],
                    "note": text["terms"][term_id][2],
                    "credits": credits,
                    "courses": courses,
                }
                for term_id, credits, courses in terms
            ],
            "outcomes": [
                {"code": "", "group": group[i], "text": outcome[i]}
                for group, outcomes in OUTCOMES
                for outcome in outcomes
            ],
            "coverageLegend": "",
            "assessment": [
                {"type": kind[i], "weight": weight, "description": target[i]}
                for kind, weight, target in ASSESSMENT
            ],
            "projectTitle": text["projectTitle"],
            "projectIntro": text["projectIntro"],
            "projectRules": [{"text": rule} for rule in text["projectRules"]],
            "admission": {
                "intro": "",
                "conditions": [{"text": c[i]} for c in CONDITIONS],
                "majors": [],
                "criteria": [{"label": label[i], "weight": weight, "description": ""} for label, weight in CRITERIA],
                "note": "",
            },
            "degreeRequirements": [],
            "teachingNote": text["teachingNote"],
        },
    }


if __name__ == "__main__":
    data = {"ar": build("ar"), "en": build("en")}
    out = Path(__file__).with_name("pharmacy.json")
    out.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {out}")
