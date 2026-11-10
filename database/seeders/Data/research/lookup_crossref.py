"""Find the DOI of register papers that do not state one, on Crossref.

    python -X utf8 lookup_crossref.py [spu_sci_research.json] [crossref_matches.json]

Crossref is the public registry behind DOIs. A paper is matched only when
Crossref returns the same title (ignoring case and punctuation, or nearly
identical) in the journal with the paper's ISSN. When the ISSN finds nothing,
the search runs again without it and then accepts only an identical title.
Anything doubtful is left out. Earlier answers are kept in the output file, so
re-running only asks about papers not looked up yet.
"""

import difflib
import json
import os
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request

API = 'https://api.crossref.org/works'
HEADERS = {'User-Agent': 'spu-website-research-import/1.0 (https://v2.spu.edu.sy)'}


def norm(title):
    """Letters and digits only: no markup (<scp>), accents (Behçet) or punctuation."""
    title = re.sub(r'<[^>]+>', '', title or '')
    title = unicodedata.normalize('NFKD', title).encode('ascii', 'ignore').decode('ascii')
    return re.sub(r'[^a-z0-9]+', '', title.lower())


NOT_THE_PAPER = re.compile(r'^\s*(correction|erratum|corrigendum|retraction|retracted)\b', re.I)


def search(title, issn=None):
    params = {'query.bibliographic': title, 'rows': '5', 'select': 'DOI,title,ISSN,container-title'}
    if issn:
        params['filter'] = 'issn:' + issn
    url = API + '?' + urllib.parse.urlencode(params)
    for attempt in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=HEADERS), timeout=40) as response:
                return json.load(response)['message']['items']
        except Exception:  # noqa: BLE001 - network hiccups are retried, then given up
            time.sleep(2 * (attempt + 1))
    return None


def best(title, items, exact_only):
    wanted = norm(title)
    for item in items or []:
        crossref_title = (item.get('title') or [''])[0]
        found = norm(crossref_title)
        # A correction or retraction notice repeats the title but is not the paper.
        if not found or (NOT_THE_PAPER.search(crossref_title) and not NOT_THE_PAPER.search(title)):
            continue
        if found == wanted:
            return item, 'exact'
        # Near: one title is the other cut short (a dropped subtitle), never a reworded one.
        shorter, longer = sorted((found, wanted), key=len)
        if not exact_only and len(shorter) >= 40 and longer.startswith(shorter) and difflib.SequenceMatcher(None, found, wanted).ratio() >= 0.8:
            return item, 'near'
    return None, None


def main():
    source = sys.argv[1] if len(sys.argv) > 1 else 'spu_sci_research.json'
    target = sys.argv[2] if len(sys.argv) > 2 else 'crossref_matches.json'
    papers = json.load(open(source, encoding='utf-8'))['papers']
    matches = json.load(open(target, encoding='utf-8')) if os.path.exists(target) else {}

    for index, paper in enumerate(papers, 1):
        number = paper['research_number']
        if paper.get('doi') or number in matches:
            continue
        issn = re.sub(r'[^0-9Xx-]', '', paper.get('issn') or '') or None
        item, how = (best(paper['title'], search(paper['title'], issn), False) if issn else (None, None))
        if item is None:
            item, how = best(paper['title'], search(paper['title']), True)
            how = how and how + ' title, other ISSN'
        else:
            how = how + ' title, same ISSN'
        matches[number] = {
            'doi': item['DOI'].lower() if item else None,
            'match': how,
            'crossref_title': (item.get('title') or [''])[0] if item else None,
            'journal': (item.get('container-title') or [''])[0] if item else None,
        }
        print('%3d/%d %s %s' % (index, len(papers), number, matches[number]['doi'] or '-'), flush=True)
        with open(target, 'w', encoding='utf-8', newline='\n') as handle:
            json.dump(matches, handle, ensure_ascii=False, indent=1, sort_keys=True)
            handle.write('\n')
        time.sleep(0.3)

    found = sum(1 for match in matches.values() if match['doi'])
    print('looked up %d, found %d' % (len(matches), found))


if __name__ == '__main__':
    main()
