#!/usr/bin/env python3
"""Builds the Requirements Unit's starting course list from the faculty study plans.

Every faculty study plan in ../study_plans/ marks its university requirement
courses with type "university". The same course carries a different code in
each faculty (Arabic Language is REQU103 in Medicine, UNRE103 in Dentistry and
UR-2-01 in AI), so the unit lists it once and keeps each faculty's code. A
faculty study plan course links to the unit course whose list holds its
faculty and code (FacultyPageService), so the codes below must match the plans.

Courses are grouped by the reviewed CATALOG below, not by title, because the
plans spell the same course differently. A university course that no entry
names (Pharmacy's "Elective University Requirement (n)" slots) stays a slot:
its link opens the elective list instead of one course.

Run from the repository root:
    python3 database/seeders/Data/centers/build_requirements_unit.py
"""

from __future__ import annotations

import collections
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PLANS = ROOT.parent / "study_plans"
OUTPUT = ROOT / "requirements-unit-courses.json"

FACULTIES = [
    "medicine",
    "dentistry",
    "pharmacy",
    "artificial-intelligence",
    "building-construction-engineering",
    "petroleum",
    "business-administration",
]

SHARED = ["artificial-intelligence", "building-construction-engineering", "business-administration"]

# id, Arabic title, English title, required, prerequisite ids, {faculty: [codes]}
CATALOG = [
    ("arabic-language", "اللغة العربية", "Arabic Language", True, [], {
        "medicine": ["REQU103"], "pharmacy": ["REQU101"], "dentistry": ["UNRE103"],
        "petroleum": ["REQU-1-05"], **{f: ["UR-2-01"] for f in SHARED},
    }),
    ("english-language-1", "اللغة الإنكليزية (1)", "English Language (1)", True, [], {
        "medicine": ["REQU101"], "pharmacy": ["REQU105"], "dentistry": ["UNRE105"],
        "petroleum": ["REQU-1-01"], **{f: ["UR-2-03"] for f in SHARED},
    }),
    ("english-language-2", "اللغة الإنكليزية (2)", "English Language (2)", True, ["english-language-1"], {
        "medicine": ["REQU204"], "pharmacy": ["REQU207"], "dentistry": ["UNRE207"],
        "petroleum": ["REQU-2-07"], **{f: ["UR-2-04"] for f in SHARED},
    }),
    ("computer-skills", "مهارات الحاسوب", "Computer Skills", True, [], {
        "medicine": ["REQU102"], "pharmacy": ["REQU103"], "dentistry": ["UNRE101"],
        "petroleum": ["REQU-1-03"], **{f: ["UR-2-02"] for f in SHARED},
    }),
    ("english-language-3", "اللغة الإنكليزية (3)", "English Language (3)", False, ["english-language-2"], {
        "medicine": ["REQU305"], "dentistry": ["UNRE302"], **{f: ["UR-2-10"] for f in SHARED},
    }),
    ("scientific-thinking", "مهارات التفكير والبحث العلمي", "Scientific Thinking and Research Skills", False, [], {
        "medicine": ["REQU106"], "dentistry": ["UNRE104"], **{f: ["UR-2-07"] for f in SHARED},
    }),
    ("sociology", "علم الاجتماع", "Sociology", False, [], {
        "medicine": ["REQU108"], "dentistry": ["UNRE108"], **{f: ["UR-2-06"] for f in SHARED},
    }),
    ("introduction-to-law", "مدخل إلى القانون", "Introduction to Law", False, [], {
        "medicine": ["REQU109"], "dentistry": ["UNRE110"], **{f: ["UR-2-05"] for f in SHARED},
    }),
    ("management-fundamentals", "أساسيات الإدارة ومهاراتها", "Management Fundamentals and Skills", False, [], {
        "medicine": ["REQU110"], "dentistry": ["UNRE112"], **{f: ["UR-2-08"] for f in SHARED},
    }),
    ("environmental-science", "علم البيئة", "Environmental Science", False, [], {
        "medicine": ["REQU111"], "dentistry": ["UNRE114"], **{f: ["UR-2-09"] for f in SHARED},
    }),
    ("second-foreign-language", "لغة أجنبية حية ثانية", "Second Foreign Language", False, [], {
        "medicine": ["REQU107"], "dentistry": ["UNRE106"],
        "artificial-intelligence": ["UR-2-13", "UR-2-14"],
        "building-construction-engineering": ["UR-2-11", "UR-2-12", "UR-2-13", "UR-2-14", "UR-2-15"],
        "business-administration": ["UR-2-11/15"],
    }),
    ("physical-mental-skills", "المهارات الحركية البدنية والذهنية", "Physical and Mental Motor Skills", False, [], {
        "building-construction-engineering": ["UR-2-16"], "business-administration": ["UR-2-16"],
    }),
    ("professional-ethics", "أخلاقيات وسلوكيات المهنة", "Professional Ethics and Conduct", False, [], {
        "building-construction-engineering": ["UR-2-17"], "business-administration": ["UR-2-17"],
    }),
]


def university_courses(faculty: str) -> dict[str, dict]:
    plan = json.loads((PLANS / f"{faculty}.json").read_text(encoding="utf-8"))
    found: dict[str, dict] = {}

    def walk(node):
        if isinstance(node, dict):
            for course in node.get("courses", []) if isinstance(node.get("courses"), list) else []:
                if isinstance(course, dict) and course.get("type") == "university" and course.get("code"):
                    found.setdefault(str(course["code"]), course)
            for value in node.values():
                walk(value)
        elif isinstance(node, list):
            for value in node:
                walk(value)

    walk(plan)
    return found


def most_common(values: list):
    return collections.Counter(values).most_common(1)[0][0] if values else 0


def number(value):
    value = float(value or 0)
    return int(value) if value.is_integer() else value


def build() -> list[dict]:
    plans = {faculty: university_courses(faculty) for faculty in FACULTIES}
    used = set()
    courses = []

    for course_id, title_ar, title_en, required, prerequisites, codes in CATALOG:
        links, credits, theory, practical = [], [], [], []
        for faculty in FACULTIES:
            for code in codes.get(faculty, []):
                source = plans[faculty].get(code)
                if source is None:
                    raise SystemExit(f"{faculty} has no university course {code} for {course_id}")
                if bool(source.get("required", True)) != required:
                    raise SystemExit(f"{faculty} {code} is not {'required' if required else 'elective'} like {course_id}")
                used.add((faculty, code))
                hours = source.get("hours") or {}
                credits.append(number(source.get("credits")))
                theory.append(number(hours.get("theory")))
                practical.append(number(hours.get("practical")))
                links.append({"faculty": faculty, "code": code, "credits": number(source.get("credits"))})

        default_credits = most_common(credits)
        for link in links:
            # A faculty that counts the course differently keeps its own credits.
            if link["credits"] == default_credits:
                del link["credits"]

        courses.append({
            "id": course_id,
            "code": "",
            "titleAr": title_ar,
            "titleEn": title_en,
            "descriptionAr": "",
            "descriptionEn": "",
            "required": required,
            "credits": default_credits,
            "hours": {"theory": most_common(theory), "practical": most_common(practical)},
            "prerequisites": prerequisites,
            "faculties": links,
            "teachers": {
                "theory": {"memberIds": [], "enabled": True},
                "practical": {"memberIds": [], "enabled": True},
            },
            "specificationUrl": "",
            "lessons": [],
        })

    slots = sorted(f"{faculty} {code}" for faculty, plan in plans.items() for code in plan if (faculty, code) not in used)
    print("University courses left as slots (link to the list):", ", ".join(slots) or "none")

    return courses


if __name__ == "__main__":
    data = build()
    OUTPUT.write_text(json.dumps({"courses": data}, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Wrote {len(data)} courses to {OUTPUT.relative_to(ROOT.parents[3])}")
