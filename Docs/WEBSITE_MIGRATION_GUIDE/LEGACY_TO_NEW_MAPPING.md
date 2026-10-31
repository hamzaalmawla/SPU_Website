# Legacy To New Mapping

## Mapping Rule

Do not force every old module into a fully rebuilt modern module immediately.

Use a two-track model:

1. canonical rebuild now for current-scope content
2. archive or continuity preservation now for content that is still public but not yet fully rebuilt

That is the safest way to preserve search value without lying about implementation readiness.

## Decision Categories

Every legacy entity should be mapped into one of these categories:

| Category | Meaning | Best use case |
|---|---|---|
| canonical rebuild now | fully represented in the new information architecture | homepage, core landing pages, current-scope CMS pages |
| archive now, remodel later | still public and valuable, but not yet fully rebuilt | historical news, research archives, long-tail institutional content |
| exact redirect to equivalent | old URL can move safely to a direct equivalent | renamed pages, relocated profiles, moved PDFs |
| intentional retirement | content is obsolete and has no useful public replacement | legally retired or duplicate pages only after review |

## Old-To-New Entity Mapping

| Old source | Proven old role | Recommended target | Launch priority | Notes |
|---|---|---|---|---|
| `jx_site_static_pages` | static pages | page system | critical | part of current public foundation |
| `jx_categories` | main public content nodes | page system plus archive layer | critical | likely the largest public visibility surface |
| `jx_items` | child media and attachments | media system plus legacy file inventory | critical | many old pages depend on files |
| `jx_docs` | language-specific menu and static links | pages, menu items, or continuity layer | high | do not drop public link value |
| `jx_home_photos` | homepage media | homepage sections and media | high | current-scope foundation |
| `jx_member_categories` | research and member content tree | archive layer, later dedicated publication or profile system | high | strong Webometrics relevance |
| `jx_member_items` | research and publication attachments | media system plus archive layer | high | preserve file discoverability |
| `jx_councils` | profiles and people pages | profile system or archive profile layer | high | preserve public references |
| `jx_councils1` | alternate profile structure | reconciliation input plus archive layer | high | do not discard automatically |
| `jx_good_students` | honor students | `honor_students` | medium-high | target table already exists |
| `jx_graduated_students` | alumni archive | `alumni` | medium-high | target table already exists |
| `jx_faqs` | public FAQ content | `faqs` and translations | medium | useful if kept high quality |
| `jx_complaint_cats` | complaint categories | `complaint_categories` | low-medium | operational, not ranking-critical |
| `jx_complaints` | complaint submissions | `complaints` | low-medium | not a migration visibility driver |
| `jx_config`, `jx_config1` | settings, subsite metadata | settings and continuity rules | critical support | key for legacy context and subpaths |
| `jx_languages` | language lookup | `languages` | support | already represented |
| `jx_countries`, `jx_cities` | lookups | `countries`, `cities` | support | already represented |

## Practical Mapping Rules

### Rebuild now when:

- the page is in current scope
- the page is a top landing page
- the page has strong backlinks
- the page is needed for current institutional messaging

### Archive now when:

- the page still has public value
- the section is out of current sprint scope
- there is no safe canonical rebuild yet
- deleting it would create needless search loss

### Retire only when:

- the content is intentionally obsolete
- there is no meaningful public replacement
- the decision is documented
- the team accepts the loss of that URL

## Required Preservation Fields

At minimum, preserve enough legacy metadata to support:

- source table
- source ID
- language context
- original explicit URL if present
- query-string signature if present
- old title or name fields
- old parent or hierarchy reference
- file provenance for attachments

Without that metadata, troubleshooting later becomes expensive and unreliable.

## Mapping Anti-Patterns

Avoid:

- mapping by title similarity alone
- throwing all unresolved content into one generic archive page
- deleting attachments because the new schema looks cleaner without them
- assuming out-of-scope content has no ranking value

## SPU-Specific Priority Reminder

Because Webometrics and search visibility depend heavily on institutional footprint, high-value migration priorities are not limited to the homepage.

The team should give special attention to:

- research and publication content
- faculty or profile pages
- archived institutional pages with backlinks
- public PDFs and policy files

## Related Documents

- [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
- [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md)
- [OLD_SYSTEM_FINDINGS.md](OLD_SYSTEM_FINDINGS.md)
- [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
