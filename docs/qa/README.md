# QA / QC — client feedback

During acceptance and handover, **every client comment is logged** here before code changes.

## Process

1. **Capture** — create `FB-NNN-short-slug.md` from the template; add a row to [`feedback-log.md`](feedback-log.md).
2. **Classify** — bug / clarification / change / deferred.
3. **Link** — requirement IDs (`docs/EasyReportCreator-Requirements.md`), sample project, stage (e.g. UAT Wed 9 Sep).
4. **Act** — implement or defer; update the ticket Status.
5. **Verify** — retest on **at least two** sample DCFs when the issue is project-agnostic.

## Stages

| Stage | When | Focus |
|---|---|---|
| UAT-1 | Wed 9 Sep 2026 | Live deploy + Vitens sample counts |
| UAT-2 | Thu 10 Sep 2026+ | Multi-project properties (FB-001), handover polish |
| Post-handover | After Thu 10 | Further feedback rounds as agreed |

## Files

| Path | Role |
|---|---|
| `feedback-log.md` | Index of all feedback |
| `FB-*.md` | One ticket per feedback item |
| `_template-feedback.md` | Copy this for new items |
