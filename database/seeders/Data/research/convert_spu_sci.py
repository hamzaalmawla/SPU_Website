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
import os
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


DOI = re.compile(r'\b10\.\d{4,9}/[^\s;،,]+', re.I)


def doi(*texts):
    """The DOI written in the register's text, e.g. "… DOI: 10.1097/MD.0000000000037708"."""
    for text in texts:
        match = DOI.search(text or '')
        if match:
            return match.group(0).rstrip('.)]').lower()
    return None


# ── Faculty ──────────────────────────────────────────────────────────────────
# The register does not record a paper's faculty, so it is read from the
# paper's subject: words in the journal's name weigh more than words in the
# title. Rules are whole-word and checked in this order of specificity.
FACULTY_TERMS = {
    'dentistry': {
        'journal': r'dent|oral|orthodon|endodon|periodon|prosthodon|odont|stomatolog|maxillofac|craniofac',
        'title': r'dental|dentists?|dentition|tooth|teeth|orthodontics?|orthodontic|endodontics?|endodontic|periodontal|periodontitis|'
                 r'prosthodontic|oral health|oral hygiene|oral mucosa|oral cavity|caries|carious|molars?|incisors?|premolars?|canines?|'
                 r'dentures?|endocrowns?|crowns?|pulp|pulpal|pulpotomy|occlusal|occlusion|malocclusion|maxillary|mandibular|mandible|'
                 r'maxilla|gingival|gingivitis|enamel|dentin|dentine|root canals?|implants?|alveolar|cleft lip|cleft palate|'
                 r'temporomandibular|tmj|orthognathic|veneers?|zirconia|glass ionomer|brackets?|aligners?|periapical|apices|'
                 r'odontoma|bruxism|halitosis|mouthwash|toothbrush\w*|twin block|facemask therapy|class ii+|herpes labialis|'
                 r'third molar|jaw|jawbones?|panoramic',
    },
    'pharmacy': {
        'journal': r'pharma\w*|drug|medicinal chemistry|natural product|phytochem|antibiotics|molecules|pathogens|toxicolog|'
                   r'biochemistry|chemistry|chromatograph',
        'title': r'pharmaceutical|pharmacist|pharmacy|pharmacokinetic\w*|synthesis|synthesized|derivatives?|formulations?|'
                 r'tablets?|nanoparticles?|hplc|chromatograph\w*|spectrophotometr\w*|extracts?|essential oils?|antimicrobial activity|'
                 r'antibacterial activity|antifungals?|antioxidant|phytochemical|docking|qsar|bioavailability|dissolution|'
                 r'excipients?|zebrafish|metabolomic\w*|in vitro activity|antiviral activity|antileishmanial|drug use|'
                 r'antibiotic usage|self-medication|medication use',
    },
    'artificial-intelligence': {
        'journal': r'ieee|acm|computer science|computing|informatics|artificial intelligence|software|intelligent systems',
        'title': r'machine learning|deep learning|neural networks?|artificial intelligence|software|devops|algorithms?|'
                 r'cloud computing|blockchain|cybersecurity|internet of things|iot|data mining|big data|information systems?|'
                 r'robot\w*|natural language processing|chatbots?|chatgpt|large language models?|image processing|'
                 r'computer vision(?! syndrome)|maturity model|cmmi|prediction model|predictive model',
    },
    'business-administration': {
        'journal': r'business|management|marketing|economic\w*|finance|accounting|entrepreneur\w*',
        'title': r'marketing|accounting|economics?|economy|financial|banking|entrepreneur\w*|e-commerce|supply chain|'
                 r'human resources?|organizational performance|job satisfaction|employees?|customers?|consumers?|brand',
    },
    'petroleum': {
        'journal': r'petroleum|oil and gas|energy fuels|geoscience|reservoir',
        'title': r'petroleum|oil fields?|reservoirs?|drilling|crude oil|natural gas|hydrocarbons?|refinery|refining|'
                 r'well logging|porosity|permeability',
    },
    'building-construction-engineering': {
        'journal': r'construction|civil engineering|structural|building|concrete|cement',
        'title': r'concrete|cement|structural|construction|civil engineering|buildings?|seismic|asphalt|soil|beams?|'
                 r'columns?|reinforced',
    },
}
MEDICAL = (r'case reports?|patients?|clinical|syndrome|diseases?|cancer|tumou?rs?|medical|medicine|health|hospitals?|'
           r'surgery|surgical|covid|infections?|students?|nurs\w*|diagnos\w*|therap\w*|treatment|cureus|lancet|heliyon|'
           r'oncolog\w*|cardi\w*|pediatric|paediatric|psychi\w*|depress\w*|anxiety|blood|virus|bacteri\w*|lungs?|renal|'
           r'kidneys?|liver|pregnan\w*|women|child\w*|infants?|neonat\w*|epidemiolog\w*|prevalence|vaccin\w*|stroke|'
           r'otolaryng\w*|ear|nose|throat|laryn\w*|literature review|systematic review|meta-analysis|physician\w*|doctors?|'
           r'injur\w*|wounds?|diabet\w*|amputation|trauma\w*|ocular|fractures?')


def faculty_scores(paper):
    title = ' ' + paper['title'].lower() + ' '
    journal = ' ' + ' '.join(filter(None, [paper.get('venue'), paper.get('original_venue')])).lower() + ' '
    scores = {}
    for faculty, terms in FACULTY_TERMS.items():
        journal_hits = set(re.findall(r'\b(?:%s)' % terms['journal'], journal))
        title_hits = set(re.findall(r'\b(?:%s)\b' % terms['title'], title))
        score = 3 * len(journal_hits) + 2 * len(title_hits)
        if score:
            scores[faculty] = (score, sorted(journal_hits | title_hits))
    return scores


def subject_faculty(paper):
    """(faculty, reason, confident) from the paper's subject alone."""
    scores = faculty_scores(paper)
    if scores:
        ranked = sorted(scores.items(), key=lambda item: -item[1][0])
        faculty, (score, words) = ranked[0]
        runner_up = ranked[1][1][0] if len(ranked) > 1 else 0
        if score > runner_up:
            return faculty, 'subject: ' + ', '.join(words[:4]), score >= 3 and score >= 2 * runner_up
    text = (paper['title'] + ' ' + (paper.get('original_venue') or '')).lower()
    if re.search(r'\b(?:%s)' % MEDICAL, text):
        return 'medicine', 'subject: clinical or health topic', False
    return None, None, False


def assign_faculties(papers):
    """Each paper's faculty: its subject when clear, else its researchers' usual faculty."""
    subject = {paper['research_number']: subject_faculty(paper) for paper in papers}

    # A researcher's usual faculty, from the papers whose subject is clear.
    usual = {}
    for paper in papers:
        faculty, _reason, confident = subject[paper['research_number']]
        if faculty and confident:
            for person in paper['researchers']:
                usual.setdefault(person, {}).setdefault(faculty, 0)
                usual[person][faculty] += 1

    for paper in papers:
        faculty, reason, _confident = subject[paper['research_number']]
        # Co-authors decide only when the subject says nothing at all: a
        # clinical case report stays medical even if its authors also
        # wrote pharmacology papers.
        if faculty is None:
            votes = {}
            for person in paper['researchers']:
                for candidate, count in usual.get(person, {}).items():
                    votes[candidate] = votes.get(candidate, 0) + count
            if votes:
                ranked = sorted(votes.items(), key=lambda item: -item[1])
                if len(ranked) == 1 or ranked[0][1] > ranked[1][1]:
                    faculty = ranked[0][0]
                    reason = 'co-authors\' other papers'
        paper['faculty'] = faculty
        paper['faculty_reason'] = reason


def valid_date(value):
    if not value or not re.match(r'^\d{4}-\d{2}-\d{2}$', value) or int(value[:4]) < 1950:
        return None
    return value


FACULTY_NAMES = {
    'medicine': 'Medicine', 'dentistry': 'Dentistry', 'pharmacy': 'Pharmacy',
    'artificial-intelligence': 'AI Engineering', 'business-administration': 'Business Administration',
    'petroleum': 'Petroleum Engineering', 'building-construction-engineering': 'Building & Construction Engineering',
}


def write_report(papers, path):
    """A list for the research office to check each paper's faculty and link."""
    lines = [
        '# Research register: faculty and link of each paper',
        '',
        'Generated by `convert_spu_sci.py`. The register does not record faculties, so each paper\'s faculty',
        'is suggested from its subject (journal and title words) or, when the subject says nothing, from its',
        'co-authors\' other papers. On import, a researcher who is on the staff pages decides first.',
        'DOIs come from the register\'s own text or, failing that, from Crossref when the title matched.',
        'Anything wrong can be corrected on the paper in the admin (Research publications).',
        '',
        '| Faculty | Papers | With a DOI link |',
        '|---|---|---|',
    ]
    by_faculty = {}
    for paper in papers:
        by_faculty.setdefault(paper['faculty'] or '', []).append(paper)
    for faculty, items in sorted(by_faculty.items(), key=lambda item: -len(item[1])):
        lines.append('| %s | %d | %d |' % (FACULTY_NAMES.get(faculty, 'None'), len(items), sum(1 for p in items if p['doi'])))
    for faculty, items in sorted(by_faculty.items(), key=lambda item: -len(item[1])):
        lines += ['', '## %s (%d)' % (FACULTY_NAMES.get(faculty, 'No faculty'), len(items)), '',
                  '| No. | Title | Journal | Why this faculty | DOI |', '|---|---|---|---|---|']
        for paper in sorted(items, key=lambda p: p['research_number']):
            cell = lambda value: (value or '').replace('|', '/')
            lines.append('| %s | %s | %s | %s | %s |' % (
                paper['research_number'], cell(paper['title']), cell(paper['venue']), cell(paper['faculty_reason']),
                ('[%s](https://doi.org/%s)' % (paper['doi'], paper['doi'])) if paper['doi'] else '-'))
    with open(path, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write('\n'.join(lines) + '\n')


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
            'doi': doi(info, place, title),
            'publication_info': info,
            'published_at': valid_date(published),
            'journal_rank': clean(rank) or None,
            'original_study_type': study or None,
            'original_venue': clean(place) or None,
            'researchers': list(authors.get(int(rid), [])),
        }
        score = sum(1 for field in ('venue', 'issn', 'publication_info', 'published_at', 'doi') if candidate[field])
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

    papers_out = [paper for _score, paper in merged.values()]
    assign_faculties(papers_out)

    # DOIs: the one written in the register first, else the Crossref match
    # (lookup_crossref.py), which only keeps papers whose title matched.
    crossref_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'crossref_matches.json')
    crossref = json.load(open(crossref_path, encoding='utf-8')) if os.path.exists(crossref_path) else {}
    for paper in papers_out:
        if paper['doi']:
            paper['doi_source'] = "the register's publication details"
        elif (crossref.get(paper['research_number']) or {}).get('doi'):
            match = crossref[paper['research_number']]
            paper['doi'] = match['doi']
            paper['doi_source'] = 'Crossref (%s)' % match['match']
        else:
            paper['doi_source'] = None

    data = {
        'source': 'spu_sci research register',
        'researchers': list(people.values()),
        'papers': papers_out,
    }
    with open(out_path, 'w', encoding='utf-8', newline='\n') as handle:
        json.dump(data, handle, ensure_ascii=False, indent=1)
        handle.write('\n')

    write_report(papers_out, os.path.splitext(out_path)[0] + '.report.md')

    print('researchers %d (from %d rows), papers %d (from %d rows), without date %d, without researchers %d' % (
        len(data['researchers']), len(researchers), len(data['papers']), len(papers),
        sum(1 for paper in data['papers'] if not paper['published_at']),
        sum(1 for paper in data['papers'] if not paper['researchers']),
    ))


if __name__ == '__main__':
    main()
