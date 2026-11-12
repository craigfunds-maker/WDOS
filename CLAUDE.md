# CLAUDE.md — WDOS engineering agent instructions

You are supporting Azeez, WODDI IT Lead, in developing a **production-ready WDOS** for WGMN and WNNN. Read `WDOS_PRODUCTION_HANDOFF.md` completely before suggesting architecture or writing code. Read all four attached `.docx` source files and inspect `WDOS_Interactive_Prototype.html` only as a design reference. Do not treat the prototype as secure backend code, a database schema, or finished localization.

## Mandatory non-negotiable requirements
1. WODDI slogan and UX: **NO WOMAN IS LEFT OUT**. Aim for rural, low-digital-literacy, low-bandwidth, mobile users. Use simple language, icons with labels, short progressive screens, optional captioned video/audio and text alternatives.
2. Language dropdown must appear first, in exact alphabetical order: Arabic, English, French, Portuguese, Swahili; all mandatory flows and messages localized, with Arabic RTL.
3. No OTP in ordinary registration/login/onboarding. Still use **secure** account and recovery architecture; never implement unsafe PIN-only server credentials, security-question guessing or identity enumeration. If the security design is unresolved, mark the work blocked and implement only guarded mock interfaces until reviewed.
4. Seven-day ordinary screening and activation is automatic end to end. No routine staff review, scheduled officer calls, or per-member manual activation; missed calls/video must have equal self-service alternatives. Exceptional contested/security/safeguarding cases are separate restricted governance cases.
5. Chapter is always supported as a final operational tier for Africa and diaspora; every scope-aware dashboard, task, roster, report, meeting, announcement and community journey includes chapter. 30–50 capacity rules, unlimited sibling chapters and pending placement must work.
6. Retain source-specific hierarchy differences: WGMN Coordinator vs WNNN Lead; Africa WNNN Country Lead/DCL in proposed Africa doc while diaspora says Country Representative/DCR for both. Do not silently resolve—use pending decision D01.
7. He-for-She Champions may be approved deputies or admin secretaries in either network; classification is NOT general ordinary-member eligibility. Campus Ambassadors are institution outreach roles, not geographical level 7.
8. One permanent Person ID; all memberships/appointments/chapter assignment histories effective-dated. Institute and Community integrate securely without duplicating persons.
9. Enforce permissions on server/database by person, appointment, network, jurisdiction and chapter. Never rely on hiding a button.
10. Production work must include tests, observability, migrations, backups, privacy controls, rollback and full operational handoff.

## Operating rules for Claude
- At task start: inspect existing repository, application stack, schema, tests, deployment and requirements; report actual current state, gaps and dependencies. **Do not assume a greenfield rewrite.**
- Write a short plan for ONE bounded milestone, including acceptance criteria and files to edit, before coding.
- Avoid guessing policy decisions listed in section 19. Create ADR/open decision records and safe defaults that do not silently commit WODDI to a policy. Stop only the affected feature; continue independent safe work.
- Do not invent real user counts, approvals, existing API routes, countries activated, costs, deployments, certificates or translation approvals. Distinguish fixtures from production records.
- Before making any schema change: provide migration, data impact, backward compatibility, indexes, constraints, rollback or recovery.
- Use typed contracts, accessible semantic UI, internationalization keys, audit events and explicit failure states. Avoid broad `any` type or untested direct database writes.
- Include test cases for non-happy paths: concurrency at 50, shared phones, network interruption, cross-network leakage, deputy scope, Arabic RTL, queue retries and no-OTP recovery.
- Never upload real WODDI user PII into third-party Claude prompts/issue text. Use synthetic fixtures and redacted logs. Respect WODDI access, vendor contracts and privacy review.
- Keep each commit cohesive, mention requirement IDs, and provide evidence from tests actually run. Never claim tests passed unless tool output confirms it.
- Produce working screens, not merely screenshots. Keep documentation synced after each milestone.
- Clarify architecture/security blockers to Azeez with concrete alternative approaches and risks; do not silently weaken security to satisfy no-OTP.

## Required response format for each development milestone
1. Current-state findings grounded in inspected files.
2. Requirement IDs / source mapping.
3. Implementation plan and design choices.
4. Code/schema/API changes with exact file paths.
5. Testing performed: commands and observed results; failures stated plainly.
6. Open decisions and security risks.
7. Deployment/migration/rollback notes.
8. Handoff checklist and what remains.

## Suggested milestone order
M00 audit; M01 requirements/ADR/permissions; M02 design system/i18n; M03 identity/no-OTP security proof; M04 geo+chapter database; M05 registration and seven-day engine; M06 chapter allocation concurrency; M07 appointments/He-for-She/diaspora; M08 dashboard RBAC; M09 tasks+reports; M10 communications; M11 Institute/Community adapters; M12 meetings, support & safeguards; M13 migration; M14 security, load, accessibility, restore; M15 staged deployment.

## Engineering examples are not mandates
The handoff API routes, events, relational entities and technology stack are **design proposals**. Confirm actual repository architecture before adopting them. Keep business invariants and acceptance tests even if implementation details change.

## Critical reference files
- `WDOS_PRODUCTION_HANDOFF.md` — authoritative consolidated working requirements and test mapping.
- `WDOS_Integrated_Implementation_Amendments.docx` — latest explicit changes.
- `WDOS_Proposed_WGMN_WNNN_Leadership_Structure(1).docx` — proposed Africa titles and six-level hierarchy.
- `WODDI_WGMN_WNNN_Diaspora_Leadership_Structure(1).docx` — diaspora model and country CR/DCR distinction.
- `WDOS_Chapter_Membership_Management_Specification(1).docx` — chapters and member allocation contract.
- `WDOS_Interactive_Prototype.html` — visual-only demonstration.

### Initial prompt Azeez can paste into Claude
> Read CLAUDE.md, WDOS_PRODUCTION_HANDOFF.md and all four source DOCX files thoroughly. Inspect the existing WDOS repository, infrastructure, current deployment and tests. Do not begin modifying production. Produce (1) current-state inventory; (2) requirements-to-existing-code traceability; (3) gap/risks report; (4) recommended architecture preserving existing working code; (5) permission matrix and chapter hierarchy data model; (6) ADRs for unresolved policy items, especially no-OTP account recovery and WNNN Africa country titles; (7) phased implementation backlog with acceptance tests. Clearly separate documented requirements from technical proposals, and call out any item you cannot verify. Do not ask to remove chapter from any workflow.

### Prompt to begin a specific milestone after audit
> Implement only milestone [Mxx] from the approved plan. First describe exact acceptance criteria and affected files. Use synthetic test data. Preserve all prior requirements, especially chapter-level scope, five languages and fully automated seven-day onboarding. Write migration and rollback instructions; implement unit, integration and end-to-end security tests; run them and report actual results. Do not bypass an unresolved management policy with a hard-coded guess.

### Prompt for independent acceptance review
> Act as an independent WDOS QA/security reviewer. Compare the repository against every Q01–Q48 answer and the latest amendments, including the 36 mandatory acceptance scenarios. Produce a traceability matrix with status Implemented / Partial / Missing / Not Verified, evidence paths and test results. Explicitly test chapter-level permissions, 50-member race condition, no-OTP recovery, zero-touch seven-day screening, Arabic RTL, all five languages, He-for-She deputy/secretary privileges and diaspora Country→City→Chapter skip. Do not equate prototype demo behavior with production functionality.
