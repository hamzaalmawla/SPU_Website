"""Builds petroleum.json: the Faculty of Petroleum Engineering's master's program page.

Source: the faculty's proposal for the Professional Master's (MSc) in Nanotechnology and its
Applications in Petroleum and Energy Engineering (15-09-2026). Like the AI page, it keeps what
a student needs: overview, structure, courses, learning outcomes with their coverage matrix,
assessment, and the graduation project and training. The proposal's justification, vision,
mission, objectives, graduate profile, capacity, laboratories and budget, staffing and quality
system are left out. The source is Arabic; English is a translation. Its courses have no codes,
so each has an id only and the cards show none.

Run: python3 build_petroleum.py  (writes the JSON next to it)
"""

import json
from pathlib import Path

PLOS = ["PLO1", "PLO2", "PLO3", "PLO4", "PLO5", "PLO6", "PLO7", "PLO8", "PLO9", "PLO10", "PLO11", "PLO12"]


def coverage(row: str) -> dict:
    """One matrix row, as in the source: twelve cells of I, R, M or -."""
    cells = row.split()
    assert len(cells) == len(PLOS), row
    return {plo: cell for plo, cell in zip(PLOS, cells) if cell != "-"}


# id, term, elective, theory, practical, credits, Arabic title, English title, coverage row
COURSES = [
    ("np-fundamentals", "term-1", False, 2, 2, 3,
     "أسس تكنولوجيا النانو وتطبيقاتها في الطاقة", "Fundamentals of Nanotechnology and its Energy Applications",
     "I I I I - - I I I I I -"),
    ("np-porous-flow", "term-1", False, 2, 2, 3,
     "التدفق المتقدم للسوائل في الأوساط المسامية", "Advanced Fluid Flow in Porous Media",
     "- I I R - - - - - - - -"),
    ("np-numerical-modeling", "term-1", False, 2, 2, 3,
     "الرياضيات والنمذجة العددية للأنظمة الهندسية", "Mathematics and Numerical Modeling of Engineering Systems",
     "- - I R I I - - - I - -"),
    ("np-synthesis-characterization", "term-1", False, 2, 2, 3,
     "تقنيات تحضير وتوصيف المواد النانوية", "Synthesis and Characterization of Nanomaterials",
     "R I R - - I R - - I R I"),
    ("np-materials-corrosion", "term-2", False, 2, 2, 3,
     "المواد المتقدمة والتآكل", "Advanced Materials and Corrosion",
     "R R R - - I R - - I R -"),
    ("np-ai-ml", "term-2", False, 2, 2, 3,
     "الذكاء الاصطناعي وتعلم الآلة في هندسة الطاقة", "Artificial Intelligence and Machine Learning in Energy Engineering",
     "- R R R M R - R - R - R"),
    ("np-research-methodology", "term-2", False, 2, 2, 3,
     "منهجية البحث العلمي", "Research Methodology",
     "- - R R R M R I R M M R"),
    ("np-nanofluids", "term-2", False, 2, 2, 3,
     "الموائع النانوية وتطبيقاتها في أنظمة الطاقة والانتقال الحراري", "Nanofluids and their Applications in Energy Systems and Heat Transfer",
     "R R R R - R M R - R R R"),
    ("np-nano-eor", "term-3", False, 2, 2, 3,
     "الاستخلاص المعزز للنفط باستخدام تكنولوجيا النانو (Nano-EOR)", "Nanotechnology-Enhanced Oil Recovery (Nano-EOR)",
     "R M M R R R M - - R R R"),
    ("np-alternative-energy", "term-3", False, 2, 2, 3,
     "تطبيقات تكنولوجيا النانو في الطاقات البديلة", "Nanotechnology Applications in Alternative Energy",
     "R R R R - R R M R R R R"),
    ("np-oil-gas-1", "term-3", True, 2, 2, 3,
     "تطبيقات النانو المتقدمة في صناعة النفط والغاز 1", "Advanced Nano Applications in the Oil and Gas Industry 1",
     "R M M R - R R - - R M R"),
    ("np-carbon-capture", "term-3", True, 2, 2, 3,
     "تكنولوجيا النانو في احتجاز الكربون وتخزينه والاستدامة البيئية", "Nanotechnology in Carbon Capture and Storage and Environmental Sustainability",
     "R R M R R R R M M R M R"),
    ("np-oil-gas-2", "term-3", True, 2, 2, 3,
     "تطبيقات النانو المتقدمة في صناعة النفط والغاز 2", "Advanced Nano Applications in the Oil and Gas Industry 2",
     "R M M R R R R R - R M R"),
    ("np-energy-efficiency", "term-3", True, 2, 2, 3,
     "تطبيقات تكنولوجيا النانو في كفاءة الطاقة وترشيد استهلاكها", "Nanotechnology Applications in Energy Efficiency and Conservation",
     "R R M M R R - M R R M R"),
    ("np-graduation-project", "term-4", False, 0, 6, 3,
     "مشروع التخرج", "Graduation Project",
     "M M M M M M M M M M M M"),
]

TERMS = [
    ("term-1", "الفصل الدراسي الأول", "First semester", None, "", ""),
    ("term-2", "الفصل الدراسي الثاني", "Second semester", None, "", ""),
    ("term-3", "الفصل الدراسي الثالث", "Third semester", 12,
     "مقرران إجباريان، ويختار الطالب مقررين من المقررات الاختيارية الأربعة.",
     "Two compulsory courses, and students choose two of the four electives."),
    ("term-4", "الفصل الدراسي الرابع", "Fourth semester", None, "", ""),
]

# code, group, Arabic, English
OUTCOMES = [
    ("PLO1", "K", "يمتلك معرفة متقدمة في تكنولوجيا النانو والمواد النانوية وخصائصها وتطبيقاتها في الطاقة وهندسة البترول.",
     "Has advanced knowledge of nanotechnology and nanomaterials, their properties and their applications in energy and petroleum engineering."),
    ("PLO2", "K", "يطبق مبادئ تكنولوجيا النانو في الاستخلاص المعزز بالنانو، والآبار والحفر والإنتاج، والتآكل والترسبات، وتطبيقات الطاقة.",
     "Applies nanotechnology principles to nano-enhanced recovery, wells, drilling and production, corrosion and deposits, and energy applications."),
    ("PLO3", "S", "يحلل المشكلات الهندسية المعقدة ذات الصلة بتطبيقات النانو ويقترح حلولاً علمية مناسبة.",
     "Analyzes complex engineering problems related to nano applications and proposes suitable scientific solutions."),
    ("PLO4", "S", "يوظف الرياضيات والنمذجة العددية والمحاكاة متعددة الفيزياء لتحليل الأنظمة والظواهر المرتبطة بتطبيقات النانو.",
     "Uses mathematics, numerical modeling and multiphysics simulation to analyze systems and phenomena related to nano applications."),
    ("PLO5", "S", "يوظف الذكاء الاصطناعي وتعلم الآلة وتحليل البيانات في التنبؤ والتحسين ودعم القرار في هندسة الطاقة.",
     "Uses artificial intelligence, machine learning and data analysis for prediction, optimization and decision support in energy engineering."),
    ("PLO6", "S", "يصمم وينفذ بحثاً علمياً أصيلاً، ويختار المنهجية المناسبة ويحلل النتائج ويستخلص الاستنتاجات.",
     "Designs and carries out original scientific research, choosing a suitable methodology, analyzing results and drawing conclusions."),
    ("PLO7", "C", "يستخدم الأساليب والتقنيات التجريبية لتحضير المواد النانوية وتوصيفها وتقييم أدائها وتحليل بياناتها.",
     "Uses experimental methods and techniques to synthesize and characterize nanomaterials, evaluate their performance and analyze their data."),
    ("PLO8", "C", "يوظف تكنولوجيا النانو في تطوير حلول للطاقة النظيفة وكفاءة الطاقة والاستدامة.",
     "Uses nanotechnology to develop solutions for clean energy, energy efficiency and sustainability."),
    ("PLO9", "C", "يطبق مفاهيم النانو في احتجاز الكربون وتخزينه وتقييم الأثر البيئي والاستدامة.",
     "Applies nano concepts to carbon capture and storage and to assessing environmental impact and sustainability."),
    ("PLO10", "C", "يتواصل علمياً ومهنياً بكفاءة، ويعرض النتائج ويكتب التقارير والأبحاث العلمية.",
     "Communicates effectively in scientific and professional settings, presents results and writes reports and research papers."),
    ("PLO11", "C", "يلتزم بأخلاقيات البحث العلمي ومتطلبات السلامة والمسؤولية البيئية والمهنية.",
     "Upholds research ethics, safety requirements and environmental and professional responsibility."),
    ("PLO12", "C", "يعمل بفاعلية ضمن فرق متعددة التخصصات ويتعاون مع الجهات الأكاديمية والصناعية.",
     "Works effectively in multidisciplinary teams and cooperates with academic and industrial partners."),
]

GROUPS = {
    "K": ("المعرفة (K)", "Knowledge (K)"),
    "S": ("المهارات الذهنية والمعرفية (S)", "Intellectual and cognitive skills (S)"),
    "C": ("المهارات العملية والمهنية (C)", "Practical and professional skills (C)"),
}

# type, purpose, examples (each Arabic, English)
ASSESSMENT = [
    (("الاختبارات التحريرية", "Written exams"), ("قياس المعرفة والفهم والتحليل", "Assess knowledge, understanding and analysis"),
     ("أسس النانو، التدفق، المواد، الرياضيات", "Nano fundamentals, flow, materials, mathematics")),
    (("الواجبات والمسائل", "Assignments and problems"), ("قياس التطبيق والتحليل وحل المشكلات", "Assess application, analysis and problem solving"),
     ("النمذجة العددية والتدفق", "Numerical modeling and flow")),
    (("المشروعات التطبيقية", "Applied projects"), ("تكامل المعرفة والمهارات", "Integrate knowledge and skills"),
     ("Nano-EOR والذكاء الاصطناعي والطاقة النظيفة", "Nano-EOR, artificial intelligence and clean energy")),
    (("التقييم العملي/المخبري", "Practical / laboratory assessment"), ("قياس المهارات التجريبية", "Assess experimental skills"),
     ("تحضير وتوصيف المواد النانوية", "Synthesis and characterization of nanomaterials")),
    (("التقارير والعروض", "Reports and presentations"), ("قياس التواصل العلمي", "Assess scientific communication"),
     ("تقارير وعروض ومناقشات", "Reports, presentations and discussions")),
    (("البحث العلمي", "Scientific research"), ("قياس تصميم وتنفيذ البحث", "Assess research design and execution"),
     ("مقترح البحث والمنهجية وتحليل النتائج", "Research proposal, methodology and analysis of results")),
    (("مشروع التخرج", "Graduation project"), ("قياس الإتقان والبحث الأصيل", "Assess mastery and original research"),
     ("التجارب/النمذجة والتحليل والمناقشة", "Experiments or modeling, analysis and discussion")),
    (("المناقشة النهائية", "Final defense"), ("قياس الفهم والدفاع العلمي", "Assess understanding and scientific defense"),
     ("مناقشة الرسالة", "Defense of the thesis")),
    (("الاستبيانات", "Surveys"), ("تقييم غير مباشر داعم", "Supporting indirect assessment"),
     ("الطلبة والخريجون وأصحاب العمل", "Students, graduates and employers")),
]

TEXT = {
    "ar": {
        "title": "ماجستير تأهيل وتخصص في تكنولوجيا النانو وتطبيقاتها في هندسة البترول والطاقة",
        "summary": "برنامج ماجستير تأهيل وتخصص في كلية هندسة البترول يجمع تكنولوجيا النانو وهندسة البترول والطاقة والنمذجة الحاسوبية والذكاء الاصطناعي، على أربعة فصول دراسية وبإجمالي 39 ساعة معتمدة.",
        "seoTitle": "ماجستير تكنولوجيا النانو في هندسة البترول والطاقة | كلية هندسة البترول",
        "seoDescription": "الخطة الدراسية لماجستير التأهيل والتخصص في تكنولوجيا النانو وتطبيقاتها في هندسة البترول والطاقة في الجامعة السورية الخاصة: المقررات، ومخرجات التعلم ومصفوفة تغطيتها، والتقييم، ومشروع التخرج والتدريب.",
        "overview": [
            "نظرًا للتطورات العالمية في تكنولوجيا النانو وتطبيقاتها في قطاع الطاقة والبترول، وحرص الجامعة السورية الخاصة على الريادة الأكاديمية والبحثية في المجالات الهندسية الحديثة، أحدثت كلية هندسة البترول برنامج ماجستير تأهيل وتخصص في تكنولوجيا النانو وتطبيقاتها في هندسة البترول والطاقة.",
            "يتكون البرنامج من مقررات دراسية تخصصية موزعة على ثلاثة فصول دراسية، إضافةً إلى مشروع تخرج تطبيقي في الفصل الرابع. ويتضمن الفصل الثالث مقررين اختياريين يختارهما الطالب من أربعة مقررات تخصصية. ويبلغ مجموع الساعات التدريسية الأسبوعية 54 ساعة بإجمالي 39 ساعة معتمدة، بواقع ساعة معتمدة لكل ساعة نظرية وساعة معتمدة لكل ساعتين عمليتين.",
        ],
        "facts": [
            ("الدرجة العلمية", "ماجستير تأهيل وتخصص"),
            ("مدة الدراسة", "أربعة فصول دراسية"),
            ("الساعات المعتمدة", "39 ساعة"),
            ("الساعات التدريسية الأسبوعية", "54 ساعة"),
        ],
        "coding": "يختار الطالب المقررين الاختياريين وفق ما يقره مجلس الكلية وتوافر الكادر التدريسي والإمكانات المتاحة، ويجوز لمجلس الكلية تحديث قائمة المقررات الاختيارية بما ينسجم مع أهداف البرنامج والقرارات النافذة.",
        "coverageLegend": "I = تقديم، R = تعزيز، M = إتقان. تُحدد التغطية النهائية للطالب وفق المقررات الاختيارية التي يختارها.",
        "projectTitle": "مشروع التخرج",
        "projectIntro": "مشروع التخرج مشروع تطبيقي تخصصي ينجزه الطالب تحت إشراف عضو هيئة تدريس مختص، ويرتبط بأحد مجالات تكنولوجيا النانو وتطبيقاتها في هندسة البترول أو الطاقة. ويمكن أن يتضمن عملاً تجريبياً، أو نمذجة ومحاكاة حاسوبية، أو دراسة تطبيقية متقدمة. ويخضع المشروع للتقييم والمناقشة أمام لجنة مختصة وفق الأنظمة والقرارات النافذة في الجامعة ومجلس التعليم العالي، وتخصص له 6 ساعات عملية أسبوعياً تعادل 3 ساعات معتمدة.",
        "requirements": [
            "يُشترط لنيل درجة الماجستير إتمام تدريب تطبيقي أو ميداني في جهة أكاديمية أو بحثية أو صناعية ذات صلة بمجالات هندسة البترول والطاقة، وفق ضوابط يعتمدها مجلس الكلية. التدريب متطلب إلزامي للتخرج لا تُخصص له ساعات معتمدة، ويُثبت بتقرير تدريب أو وثيقة معتمدة من جهة التدريب.",
        ],
    },
    "en": {
        "title": "Professional Master's in Nanotechnology and its Applications in Petroleum and Energy Engineering",
        "summary": "A professional master's program at the Faculty of Petroleum Engineering that brings together nanotechnology, petroleum and energy engineering, computational modeling and artificial intelligence, over four semesters and 39 credit hours.",
        "seoTitle": "Master's in Nanotechnology for Petroleum & Energy | Faculty of Petroleum Engineering",
        "seoDescription": "Study plan of the Professional Master's in Nanotechnology and its Applications in Petroleum and Energy Engineering at Syrian Private University: courses, learning outcomes and their coverage, assessment, and the graduation project and training.",
        "overview": [
            "Given the worldwide advances in nanotechnology and its applications in the energy and petroleum sector, and Syrian Private University's commitment to academic and research leadership in modern engineering fields, the Faculty of Petroleum Engineering has established a professional master's program in nanotechnology and its applications in petroleum and energy engineering.",
            "The program consists of specialized courses over three semesters and an applied graduation project in the fourth. The third semester includes two electives chosen from four specialized courses. The plan has 54 weekly teaching hours and 39 credit hours in total: one credit hour per theory hour and one per two practical hours.",
        ],
        "facts": [
            ("Degree", "Professional Master's"),
            ("Duration", "Four semesters"),
            ("Credit hours", "39 hours"),
            ("Weekly teaching hours", "54 hours"),
        ],
        "coding": "Students choose their two electives as approved by the Faculty Council and according to the staff and facilities available. The Faculty Council may update the list of electives in line with the program's objectives and the regulations in force.",
        "coverageLegend": "I = Introduced, R = Reinforced, M = Mastered. A student's final coverage depends on the electives they choose.",
        "projectTitle": "Graduation project",
        "projectIntro": "The graduation project is an applied, specialized project the student completes under the supervision of a faculty member, in one of the fields of nanotechnology and its applications in petroleum or energy engineering. It may involve experimental work, computational modeling and simulation, or an advanced applied study. It is assessed and defended before a specialized committee under the regulations of the university and the Council of Higher Education, and has 6 practical hours a week, equal to 3 credit hours.",
        "requirements": [
            "The master's degree requires applied or field training at an academic, research or industrial organization related to petroleum and energy engineering, under rules approved by the Faculty Council. The training is a compulsory graduation requirement with no credit hours, documented by a training report or a certificate from the training organization.",
        ],
    },
}


def build(locale: str) -> dict:
    ar = locale == "ar"
    i = 0 if ar else 1
    text = TEXT[locale]
    terms = []

    for term_id, label_ar, label_en, credits, note_ar, note_en in TERMS:
        courses = [
            {
                "id": course_id,
                "code": "",
                "title": title_ar if ar else title_en,
                "titleAlt": title_en if ar else title_ar,
                "elective": elective,
                "credits": cr,
                "theory": theory,
                "practical": practical,
                "prerequisites": [],
                "prerequisiteNote": "",
                "description": "",
                "outcomes": [],
                "grading": "",
                "coverage": coverage(row),
            }
            for course_id, term, elective, theory, practical, cr, title_ar, title_en, row in COURSES
            if term == term_id
        ]
        terms.append({
            "id": term_id,
            "label": label_ar if ar else label_en,
            "theme": "",
            "note": note_ar if ar else note_en,
            "credits": credits,
            "courses": courses,
        })

    return {
        "title": text["title"],
        "summary": text["summary"],
        "seoTitle": text["seoTitle"],
        "seoDescription": text["seoDescription"],
        "payload": {
            "overview": [{"text": p} for p in text["overview"]],
            "facts": [{"label": label, "value": value} for label, value in text["facts"]],
            "coding": text["coding"],
            "terms": terms,
            "outcomes": [{"code": code, "group": GROUPS[group][i], "text": ar_text if ar else en_text} for code, group, ar_text, en_text in OUTCOMES],
            "coverageLegend": text["coverageLegend"],
            "assessment": [
                {"type": kind[i], "weight": "", "description": f"{purpose[i]}{'، مثل: ' if ar else '. For example: '}{examples[i]}."}
                for kind, purpose, examples in ASSESSMENT
            ],
            "projectTitle": text["projectTitle"],
            "projectIntro": text["projectIntro"],
            "projectRules": [],
            "admission": {"intro": "", "conditions": [], "majors": [], "criteria": [], "note": ""},
            "degreeRequirements": [{"text": r} for r in text["requirements"]],
            "teachingNote": "",
        },
    }


if __name__ == "__main__":
    data = {"ar": build("ar"), "en": build("en")}
    out = Path(__file__).with_name("petroleum.json")
    out.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {out}")
