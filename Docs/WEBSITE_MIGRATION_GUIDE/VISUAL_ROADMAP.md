# Visual Roadmap

## Migration Sequence

```mermaid
flowchart TD
    A["Baseline and inventories"] --> B["Classify legacy URLs, files, and archives"]
    B --> C["Build current-scope public foundation"]
    C --> D["Build redirect and legacy continuity layer"]
    D --> E["Add canonical, hreflang, sitemap, and robots output"]
    E --> F{"Launch gates passed?"}
    F -- "No" --> G["Fix blockers and re-test"]
    G --> F
    F -- "Yes" --> H["Controlled public cutover"]
    H --> I["First 72 hours monitoring"]
    I --> J["First 30 days triage and refinement"]
```

## What Must Happen Before Launch

```mermaid
flowchart LR
    A["Public pages exist"] --> E["Safe cutover"]
    B["Legacy URLs resolve"] --> E
    C["Files and PDFs survive"] --> E
    D["Monitoring is live"] --> E
```

## What A Bad Migration Looks Like

```mermaid
flowchart LR
    A["Old URL"] --> B["404 or homepage redirect"]
    B --> C["Link equity weakens"]
    C --> D["Crawl confusion grows"]
    D --> E["Traffic and visibility erode"]
```

## What A Professional Migration Looks Like

```mermaid
flowchart LR
    A["Old URL"] --> B["301 to best equivalent"]
    B --> C["Canonical new or archive URL"]
    C --> D["Correct language and metadata signals"]
    D --> E["Faster signal transfer and safer recovery"]
```

## Release Mindset

The visual summary is simple:

- inventory first
- continuity second
- launch last

Not the other way around.
