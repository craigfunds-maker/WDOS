# 07. Support routing and escalation

How a problem travels from the person who has it to the person who can fix it. Built parts are marked Built. Times are proposals for approval, not promises WDOS enforces.

## First line: self-service (Built)

| Need | Where |
|---|---|
| How to do something | The spoken guide on each joining screen, the guide videos, Help |
| Forgot the password | Sign in with fingerprint, or the email link (members with an email), or ask the Chapter Lead in person, or ask HQ |
| Wrong details | Settings |
| Pause or leave | Settings |

## Routes

| Kind of problem | Who raises it | Goes to | Channel in WDOS | Proposed time to first answer |
|---|---|---|---|---|
| Question about WODDI or membership | Member | Chapter Lead, then HQ | My chapter; Messages with HQ | 2 working days |
| Cannot sign in | Member | Chapter Lead in person, or HQ | Reset PIN; Members, New temporary password | Same day |
| Wrong chapter or wrong place | Member or leader | HQ | Messages with HQ | 3 working days |
| Operational issue in an area | Leader | Supervisor, then HQ | My reports, Raise an issue, Escalate to | By severity: red and critical 1 day |
| Late report, late task | WDOS | Leader, supervisor, then HQ | Automatic reminders and escalation | Automatic |
| Complaint or safeguarding concern | Anyone | HQ case handlers only | Help and private reports | `TO DECIDE`; proposed 1 day |
| Possible duplicate account, journey exception | WDOS | HQ | Dashboard, Exceptions for HQ | 3 working days |
| Fault in WDOS itself | Anyone, through HQ | IT | Messages with HQ, then IT | By severity, below |
| Leader's seat or term | Leader | HQ | Messages with HQ | 3 working days |

## Faults in WDOS: severity

| Level | Meaning | Example | Action |
|---|---|---|---|
| 1 Critical | Site down, data exposed or lost, sign-in broken for all | `/api/config` does not answer | IT at once; restore last good deploy; document 09 if data is involved |
| 2 High | A core workflow fails for many | Registration fails in one language | Fix or roll back within 1 day |
| 3 Medium | A workaround exists | A report column is wrong | Next planned release |
| 4 Low | Wording, appearance | A label not translated | Batch with others |

## Unresolved cases

A conversation not marked resolved stays in Open conversations for all HQ staff. A private report stays open until a handler writes an outcome. A handler never sees a case about themselves. Nothing is closed automatically and no member is sanctioned automatically.

`TO DECIDE`: the named person who reviews open items each week, and the time limits above.

## Not built

A help desk with ticket numbers for ordinary questions, SMS or WhatsApp support, a phone line, and measured answer times. "Support incidents" on the dashboard counts open private reports only.
