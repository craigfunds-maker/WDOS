# 01. Roles and access matrix

Written from the permission checks in the code (`lib/core.php`, `lib/dashboards.php`, `lib/ops.php`, `lib/handoff_fns.php`). Every check below is made on the server. Hiding a button is never the control.

## The kinds of account

| Account | How it is created | What decides its reach |
|---|---|---|
| Member | A woman registers herself. No OTP. | Her own record, her chapter, her network. |
| Volunteer leader | HQ appoints a member to a seat. The member accepts the Volunteer Leadership Agreement and completes onboarding. | The seat: position, network, area (country, state or region, LGA or city, community cluster, chapter). |
| Deputy, assistant, administrative secretary | Same as a leader. A He-for-She Champion may hold only these seats. | Same area as the principal seat. Being a He-for-She Champion does not make someone an ordinary member. |
| Campus Ambassador | HQ appoints. | Outreach for one institution. Not a geographic level. |
| HQ administrator (`hq_admin`) | A super administrator gives HQ access. | Everything, both networks, except what is reserved below. |
| Super administrator (`super_admin`) | Set at installation. | Everything, plus staff logins and the full backup. |

A leader on leave or suspended loses the leadership dashboard at once. The appointment and the membership stay on record.

## Positions and their level

| Level | WGMN title | WNNN title |
|---|---|---|
| Country | Country Representative, Deputy Country Representative, Country Administrative Secretary | Country Lead, Deputy Country Lead, Country Administrative Secretary |
| State or region | State Coordinator, Assistant State Coordinator, State Secretary | State Lead, Assistant State Lead, State Secretary |
| LGA or district (diaspora: city) | LGA Coordinator, Assistant, LGA Administrative Secretary (City Coordinator, Assistant) | LGA Lead, Assistant, LGA Administrative Secretary (City Lead, Assistant) |
| Community cluster | Community Cluster Coordinator, Assistant, Administrative Secretary | Community Cluster Lead, Assistant, Administrative Secretary |
| Chapter | Chapter Lead, Assistant Chapter Lead, Chapter Administrative Secretary | Chapter Lead, Assistant Chapter Lead, Chapter Administrative Secretary |

**Open decision D01.** The Africa structure document says the WNNN country title is Country Lead. The diaspora document says Country Representative for both networks. WDOS holds this as a rule (`wnnn_country_title`, now "Country Lead") and does not settle it. `TO DECIDE` by management.

## What each account can do

"Own area" means the seat's area and everything under it, in the seat's own network only. A leader never sees the other network.

| Function | Member | Leader | Deputy or secretary | HQ administrator | Super administrator |
|---|---|---|---|---|---|
| Own profile, language, sign-in methods | Edit | Edit | Edit | Edit | Edit |
| Seven-day journey | Own | Own | Own | See counts and exceptions | Same |
| Chapter page | Own chapter | Chapters in own area | Chapters in own area | All | All |
| Member list | No | Own area, own network | Own area, own network | All | All |
| Reset a member's PIN in person | No | Only a leader seated in that member's own chapter, never a He-for-She Champion; at most 5 a day; HQ is told; only while the rule `recovery_by_leader` is on | Same condition | Yes (temporary password) | Yes |
| Move members between chapters | No | No | No | Yes, with the reason kept; a move into a full chapter needs a written reason and shows as a capacity exception | Same |
| Open, merge, close, suspend a chapter | No | No | No | Yes | Yes |
| Appoint, suspend, end a seat, set a term | No | No | No | Yes | Yes |
| Referral links for vacant seats | No | Seats under own seat | Seats under own seat | Any | Any |
| Tasks | Receive | Assign within own area | Assign within own area | Any | Any |
| Leader report | No | Write for own unit, review units directly below | Contribute to the unit report | Review all | Review all |
| Meetings | Invited | Schedule within own area | Schedule within own area | Any | Any |
| Announcements | Read | Send to own area, down to own chapter | Send to own area | Any audience, newsletters | Same |
| Messages with HQ | Yes | Yes | Yes | Shared HQ inbox | Shared HQ inbox |
| Private report (complaint, safeguarding) | File | File | File | Handle, but never a case about oneself | Same |
| Confidential cases list | No | No | No | Yes | Yes |
| Message templates, inspiration, guide voices | No | No | No | Yes | Yes |
| System rules and decision register | No | No | No | Yes | Yes |
| View a leader's dashboard as the leader sees it | No | No | No | Read only | Read only |
| Give or remove HQ access | No | No | No | No | Yes |
| Create or switch off staff logins | No | No | No | No | Yes |
| Reset another HQ administrator's password | No | No | No | No | Yes |
| Full backup download | No | No | No | No | Yes, with own password |

## Things nobody can do

- Read an existing password. WDOS keeps only a one-way hash. A reset makes a temporary password, shown once.
- Delete a member's history. Transfers, seats and decisions are closed with a date, not removed.
- Sanction a member automatically. Exceptional cases wait for a person at HQ.

## Not yet in WDOS

- A separate Founder or read-only executive role. Today the Founder would use an HQ administrator login. `TO DECIDE`.
- Separate HQ roles for finance, programmes or case handling. Every HQ administrator is a case handler today. `TO DECIDE` whether to split.
- HQ financial control: WDOS holds no money and no payment function.
