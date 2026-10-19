"""Convert the research office's register (the spu_sci MySQL dump) to spu_sci_research.json.

    python -X utf8 convert_spu_sci.py <dump.sql> [out.json]

The register keeps a free-text study type; the office's newer system ("gamma")
uses fixed codes, and so does the website. This maps each paper to those codes,
merges papers entered twice, merges researchers entered twice, and keeps the
original free text in the paper's internal notes so nothing is lost.

Researchers' emails and phone numbers are left out on purpose: they are
personal data and this file is committed.
"""

import json
import re
import sys
from collections import OrderedDict

TOKEN = re.compile(r"'((?:[^'\\]|\\.)*)'|(NULL)|(-?\d+(?:\.\d+)?)")


def rows(src, table):
    match = re.search(r"INSERT INTO `%s` \([^)]*\) VALUES\n(.*?);\n" % table, src, re.S)
    if not match:
        raise SystemExit('No %s data in the dump.' % table)
    out = []
    for line in match.group(1).split('\n'):
        line = line.strip().rstrip(',')
        if not line.startswith('('):
            continue
        out.append([None if null else (number if number else unescape(text)) for text, null, number in TOKEN.findall(line)])
    return out


def unescape(value):
    return re.sub(r"\\(.)", lambda m: {'n': '\n', 'r': '\r', 't': '\t', '0': ''}.get(m.group(1), m.group(1)), value)


def clean(value):
    value = (value or '').replace('‎', '').replace('‏', '')
    return re.sub(r'\s+', ' ', value).strip()


def clean_title(value):
    return clean(value).strip('"\'“”‘’ ')


def title_key(value):
    return re.sub(r'[^a-z0-9]+', '', clean_title(value).lower())


def name_key(value):
    return re.sub(r'[^a-z0-9؀-ۿ]+', '', clean(value).lower())


NOT_RESEARCH = r'corrigendum|erratum|editorial|commentary|تصحيح|غير بحثية'


def publication_type(study):
    text = study.lower()
    if re.search(NOT_RESEARCH, text):
        return 'other'
    if re.search(r'case report|case study|case series|تقرير حال|حالة سريرية|دراسة حالة', text):
        return 'case_report'
    if re.search(r'review|meta-analys|metaanalys|مراجعة|تحليل تلوي', text):
        return 'review_article'
    if re.search(r'conference|مؤتمر', text):
        return 'conference_paper'
    if re.search(r'book chapter|فصل', text):
        return 'book_chapter'
    return 'original_research'


def study_type(study):
    text = study.lower()
    if text == '':
        return None
    if re.search(r'review|meta-analys|metaanalys|مراجعة|تحليل تلوي|statement|guideline|' + NOT_RESEARCH, text):
        return 'other'
    if re.search(r'fundamental|أساسي', text):
        return 'fundamental'
    if re.search(r'applied|machine learning|تطبيقي', text):
        return 'applied'
    if re.search(r'randomi|clinical|trial|case report|case study|case series|سريري|تقرير حال|عشوائي', text):
        return 'clinical'
    if re.search(r'theoretical|نظري', text):
        return 'theoretical'
    if re.search(r'cross[- ]?sectional|observational|cohort|survey|questionnaire|retrospective|prospective|case[- ]control|'
                 r'experimental|in vitro|in vivo|laborator|animal|descriptive|analytical|مقطعي|استبيان|رصدي|استعادي|'
                 r'multi-?cent|multi-?national|exploratory|diagnostic|evaluation|validation|qualitative|'
                 r'مخبري|تجريبي|وصفي|تحليلي|متعددة|استكشافي|تقييمي', text):
        return 'empirical'
    return None


def venue(place):
    place = clean(place)
    # "Cureus – peer-reviewed open access journal …": keep the journal's name.
    name = re.split(r'\s+[–—-]\s+|\s*\(', place, maxsplit=1)[0]
    name = re.sub(r'^(?:مجلة\s+)?(?:Q[1-4]\s+)?', '', name).strip(' ,،:')
    return name or None


def valid_date(value):
    if not value or not re.match(r'^\d{4}-\d{2}-\d{2}$', value) or int(value[:4]) < 1950:
        return None
    return value


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    src = open(sys.argv[1], encoding='utf-8').read()
    out_path = sys.argv[2] if len(sys.argv) > 2 else 'spu_sci_research.json'

    researchers = rows(src, 'researchers')
    papers = rows(src, 'researches')
    team = rows(src, 'research_team')

    # Researchers entered twice become one; the first registration wins.
    canonical = {}
    people = OrderedDict()
    for rid, name, _email, _phone, department, status, _created in researchers:
        key = name_key(name)
        if not key:
            continue
        if key not in people:
            people[key] = {
                'source_id': int(rid),
                'full_name': clean(name),
                'affiliation': clean(department) or None,
                'registration_status': 'registered' if status == 'مسجل' else 'pending',
            }
        elif status == 'مسجل':
            people[key]['registration_status'] = 'registered'
            people[key]['affiliation'] = people[key]['affiliation'] or clean(department) or None
        canonical[int(rid)] = people[key]['source_id']

    authors = {}
    for _id, research_id, researcher_id, _role in sorted(team, key=lambda row: int(row[0])):
        if research_id is None or researcher_id is None or int(researcher_id) not in canonical:
            continue
        listed = authors.setdefault(int(research_id), [])
        person = canonical[int(researcher_id)]
        if person not in listed:
            listed.append(person)

    # A paper entered twice is kept once: the most complete entry, with every author.
    merged = OrderedDict()
    for row in papers:
        rid, number, title, _ptype, study, place, issn, info, _by, created, _updated, published, rank = row
        key = title_key(title)
        if not key:
            continue
        study = clean(study)
        info = clean(info)
        info = None if re.fullmatch(r'[-\s]*', info or '') else info
        candidate = {
            'source_id': int(rid),
            'research_number': clean(number),
            'title': clean_title(title),
            'publication_type': publication_type(study),
            'study_type': study_type(study),
            'venue': venue(place),
            'issn': clean(issn) or None,
            'publication_info': info,
            'published_at': valid_date(published),
            'journal_rank': clean(rank) or None,
            'original_study_type': study or None,
            'original_venue': clean(place) or None,
            'researchers': list(authors.get(int(rid), [])),
        }
        score = sum(1 for field in ('venue', 'issn', 'publication_info', 'published_at') if candidate[field])
        if key not in merged:
            merged[key] = (score, candidate)
            continue
        kept_score, kept = merged[key]
        for person in candidate['researchers']:
            if person not in kept['researchers']:
                kept['researchers'].append(person)
        if score > kept_score:
            candidate['researchers'] = kept['researchers']
            merged[key] = (score, candidate)

    data = {
        'source': 'spu_sci research register',
        'researchers': list(people.values()),
        'papers': [paper for _score, paper in merged.values()],
    }
    with open(out_path, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(data, handle, ensure_ascii=False, indent=1)
        handle.write('\n')

    print('researchers %d (from %d rows), papers %d (from %d rows), without date %d, without researchers %d' % (
        len(data['researchers']), len(researchers), len(data['papers']), len(papers),
        sum(1 for paper in data['papers'] if not paper['published_at']),
        sum(1 for paper in data['papers'] if not paper['researchers']),
    ))


if __name__ == '__main__':
    main()
