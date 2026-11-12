# 08. Data migration plan

Moving existing member and leader records into this WDOS. No figures are given here because nobody has counted the source data with evidence yet. Step 1 produces the real numbers.

## What exists in WDOS for this

- An import of accounts from the earlier WDOS (v90.1), described in `README-DEPLOY.md`, "Bringing members across".
- Duplicate checks at registration and a list of possible duplicates for HQ. Nothing is merged or removed automatically.
- One permanent Person ID per person; memberships, seats and chapter history are dated, not overwritten.
- Automatic chapter placement, including bulk placement of members who have no chapter.

## Steps

| Step | What happens | Proof kept |
|---|---|---|
| 1. Inventory | List every source: earlier WDOS, spreadsheets, forms, WhatsApp lists. For each: owner, number of rows, fields, date, whether consent was recorded. | Inventory sheet |
| 2. Decide what moves | `TO DECIDE`: which sources, and whether people with no recorded consent are moved, invited to re-register, or left out. | Decision D-__ in the register |
| 3. Field mapping | Map each source field to WDOS: names, date of birth, phone (with country code), email if any, country, state or region, LGA or district, community, network, language. Age decides the network: 18 to 29 WNNN, 30 and above WGMN. | Mapping table |
| 4. Clean | Fix phone formats; remove blank rows; flag under-18s, missing dates of birth and places that do not match the lists. Do the cleaning on a copy, never on the original. | Before and after counts |
| 5. Duplicates | Match on phone, then email, then name with date of birth. A person keeps one Person ID. Doubtful pairs go to a person to decide. | List of pairs and decisions |
| 6. Rehearse | Import into a test copy with the database emptied first. Never rehearse on the live copy. | Import log |
| 7. Reconcile | Source rows = imported + rejected + merged. Totals by network and by country must agree with the source. Check the Handoff register integrity checks: all none. | Signed reconciliation sheet |
| 8. Leaders | Seats are not imported as text. HQ appoints each leader in WDOS so the Agreement and onboarding apply. `TO DECIDE` whether existing leaders skip any step. | Seat list |
| 9. Go live | Take a backup. Import. Reconcile again. Then tell members how to sign in. | Backup file, reconciliation |
| 10. First sign-in | Imported members have no password in WDOS. `TO DECIDE` the route: email link where an email exists; otherwise Chapter Lead reset in person or HQ temporary password. No OTP. | Count of members who have signed in |

## Risks

| Risk | Guard |
|---|---|
| A wave of messages reaches real people during import | Import with notifications held; send one approved welcome afterwards |
| Shared phones: several women on one number | Phone alone must not merge two people. Name and date of birth are checked too. A phone can sign in only one account, so the others need an email or a leader reset. `TO DECIDE` how shared phones are handled. |
| Wrong network from a wrong date of birth | Flag ages under 18 and over 100 for a person to check |
| Personal data copied into chats or tools | Work only on WODDI machines and accounts; never paste real records into an AI tool, an issue or an email |
| Cross-border transfer of personal data | `TO DECIDE` with legal advice: which data protection laws apply for each country served, and where the database region should be |

## Entry and exit

Start when: sources are inventoried, decisions at steps 2, 8 and 10 are recorded, and a restore has been rehearsed. Finish when: the reconciliation sheet is signed by two people and integrity checks show none.
