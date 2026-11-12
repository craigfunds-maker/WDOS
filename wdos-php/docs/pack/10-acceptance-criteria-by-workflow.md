# 10. Acceptance criteria for each workflow (W01 to W30)

Built from the workflow catalogue in WDOS (`lib/handoff_fns.php`), which HQ sees under Digital coverage. "Declared" is the developer's own statement. **No row has been verified by a named person yet, so verified coverage is 0%.** Frequency, minutes and weight are labelled estimates to be replaced by real volumes. Test numbers refer to the 36 acceptance tests in the Handoff register.

A workflow is accepted when a named person, not the developer, has watched it meet the line "Accepted when" on the deployed test copy and has recorded it on the Digital coverage page.


## Membership

### W01. Member registration without OTP

- **Trigger:** A woman opens the join page or an invitation link
- **Inputs and automatic action:** Language, network, details, place, PIN
- **Data created (proof of completion):** Person ID, membership number, account
- **Exceptional path:** Email already used, phone already signs in a member, under 18, connection lost half way (the form resumes).
- **Accepted when:** A first-time user registers in each of the five languages with no OTP; a second registration with the same phone and no email is refused with a plain message; the account, Person ID and membership number exist once.
- **Tests:** T03,T04. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 15 minutes by hand, weight 10.
- **Verified by:** ____________ **on** ____________

### W02. Network assignment by age

- **Trigger:** Registration
- **Inputs and automatic action:** Date of birth checked against the age rule
- **Data created (proof of completion):** WGMN or WNNN membership
- **Exceptional path:** Date of birth missing or under 18: registration stops with a reason.
- **Accepted when:** Ages 18 to 29 land in WNNN and 30 and above in WGMN, on the server, whatever the screen sent.
- **Tests:** T09,T10. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 5 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________

### W03. Seven-day journey and automatic activation

- **Trigger:** Registration
- **Inputs and automatic action:** Seven daily steps, self-screening, automatic outcome
- **Data created (proof of completion):** Active member, or more time given
- **Exceptional path:** Journey not finished by day 7: extra days are given automatically. Possible duplicate: goes to the HQ exception list.
- **Accepted when:** A member who completes the steps is active on day 7 with no staff action, and the audit log says the decision was automatic. No step needs a call or a video.
- **Tests:** T06,T07. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 60 minutes by hand, weight 10.
- **Verified by:** ____________ **on** ____________

### W04. Referral link and counting

- **Trigger:** Member shares her link
- **Inputs and automatic action:** Share link, opened, registered, completed counted apart
- **Data created (proof of completion):** Referral record
- **Exceptional path:** A link opened but not used is not counted as a member.
- **Accepted when:** Opened, registered and completed are counted separately and agree with the member records.
- **Tests:** T08. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 10 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________

### W05. Pause, return and closure by the member

- **Trigger:** Member request
- **Inputs and automatic action:** Member chooses in Settings
- **Data created (proof of completion):** Lifecycle status and record
- **Exceptional path:** A leader who closes membership: the seat must end first.
- **Accepted when:** Status changes at once, the record and history stay, and the member can return.
- **Tests:** none yet. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 15 minutes by hand, weight 2.
- **Verified by:** ____________ **on** ____________

### W06. WNNN member turning 30

- **Trigger:** Thirtieth birthday within 30 days
- **Inputs and automatic action:** Notice to the member, list for HQ, HQ moves her
- **Data created (proof of completion):** Transition recorded, Person ID kept
- **Exceptional path:** A WNNN leader turning 30 still holds a seat. No automatic move: management decision D08 is open.
- **Accepted when:** The member is told, HQ sees the list, and a move keeps the same Person ID and full history.
- **Tests:** T31. **Declared:** partly digital; exceptions covered: no. **Estimate:** monthly, 20 minutes by hand, weight 1.
- **Verified by:** ____________ **on** ____________


## Chapters

### W07. Automatic chapter placement

- **Trigger:** Registration or change of place
- **Inputs and automatic action:** Chapter with room found and seat taken in one transaction
- **Data created (proof of completion):** Chapter assignment, or pending placement
- **Exceptional path:** Every chapter in the place is full: the next chapter opens, or the member waits as pending placement if automatic opening is off.
- **Accepted when:** Fifty-one registrations for one place give 50 in chapter 001 and 1 in chapter 002, also when they arrive at the same moment. Nobody is refused and nobody is in two chapters.
- **Tests:** T11,T12,T15. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 20 minutes by hand, weight 8.
- **Verified by:** ____________ **on** ____________

### W08. Chapter status by membership

- **Trigger:** A member joins or leaves
- **Inputs and automatic action:** Recount on every change
- **Data created (proof of completion):** Developing, eligible, active, at capacity
- **Exceptional path:** A chapter over capacity without a recorded exception appears on the integrity check.
- **Accepted when:** Status follows the count at 30, 40 and 50 (or the country's own figures) without anyone setting it.
- **Tests:** T13. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 15 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________

### W09. Transfer, merge and split

- **Trigger:** HQ decision
- **Inputs and automatic action:** HQ action with reason and approval reference
- **Data created (proof of completion):** History kept, counts corrected
- **Exceptional path:** Move into a full chapter needs a written reason and shows as a capacity exception.
- **Accepted when:** After a transfer, merge or split the old and new chapter, dates, reason and approver are on the member's history and both counts are right.
- **Tests:** T14. **Declared:** digital; exceptions covered: yes. **Estimate:** monthly, 30 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________


## Leadership

### W10. Appointment to a position

- **Trigger:** Approved appointment
- **Inputs and automatic action:** HQ records position, area, status, He-for-She
- **Data created (proof of completion):** Seat with scoped dashboard
- **Exceptional path:** Seat already held; He-for-She Champion offered a seat that is not deputy, assistant or secretary: refused.
- **Accepted when:** The appointed leader sees only the seat's area and network, tested by direct requests to the server and not only on screen.
- **Tests:** T16,T18. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 30 minutes by hand, weight 5.
- **Verified by:** ____________ **on** ____________

### W11. Leave, suspension, resignation and replacement

- **Trigger:** Change in a leader's availability
- **Inputs and automatic action:** HQ changes the state of the seat
- **Data created (proof of completion):** Access removed or restored, history kept
- **Exceptional path:** Leader returns from leave: access is restored with the same seat.
- **Accepted when:** Access to the leadership dashboard stops in the same minute the state changes; history is kept.
- **Tests:** T21. **Declared:** digital; exceptions covered: yes. **Estimate:** monthly, 30 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________

### W12. Vacancy register

- **Trigger:** Continuous
- **Inputs and automatic action:** Positions with no holder listed by area
- **Data created (proof of completion):** Vacancy list
- **Exceptional path:** None.
- **Accepted when:** Every seat with no holder in scope is listed for countries and for states where the list is loaded.
- **Tests:** none yet. **Declared:** digital; exceptions covered: no. **Estimate:** weekly, 30 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________


## Work

### W13. Task assignment, delegation and approval

- **Trigger:** A leader or HQ assigns
- **Inputs and automatic action:** Assign, start, submit, approve or send back
- **Data created (proof of completion):** Closed task with evidence
- **Exceptional path:** Task sent back with a reason; delegated part stays linked to its parent.
- **Accepted when:** A task goes from assigned to approved with one accountable person, and a leader cannot assign outside the area.
- **Tests:** T23. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 15 minutes by hand, weight 6.
- **Verified by:** ____________ **on** ____________

### W14. Task reminders and escalation

- **Trigger:** Due date
- **Inputs and automatic action:** Automatic on the due date and after
- **Data created (proof of completion):** Reminder and escalation notice
- **Exceptional path:** Assignee does not act: supervisor, then HQ, are told.
- **Accepted when:** Reminders and escalation go out on the configured days with nobody pressing a button.
- **Tests:** none yet. **Declared:** digital; exceptions covered: yes. **Estimate:** daily, 10 minutes by hand, weight 5.
- **Verified by:** ____________ **on** ____________


## Reporting

### W15. Periodic leadership report

- **Trigger:** Reporting period opens
- **Inputs and automatic action:** Open, write, submit, review
- **Data created (proof of completion):** Reviewed report
- **Exceptional path:** No activity in the period: a nil declaration with a reason. Late: marked late. Returned: corrected and submitted again as a new version.
- **Accepted when:** An approved report is locked, earlier versions are kept, and a submitted figure is never shown as approved.
- **Tests:** none yet. **Declared:** digital; exceptions covered: yes. **Estimate:** monthly, 45 minutes by hand, weight 6.
- **Verified by:** ____________ **on** ____________

### W16. Automatic roll-up report to chapter

- **Trigger:** Continuous
- **Inputs and automatic action:** Counted from records, no typing
- **Data created (proof of completion):** Figures by level with time taken
- **Exceptional path:** A diaspora country with no state level still adds up.
- **Accepted when:** Totals from chapter to country to continent equal the member records, with no typed totals.
- **Tests:** T22. **Declared:** digital; exceptions covered: no. **Estimate:** monthly, 60 minutes by hand, weight 4.
- **Verified by:** ____________ **on** ____________


## Meetings

### W17. Scheduling, invitation and reply

- **Trigger:** A leader or HQ schedules
- **Inputs and automatic action:** Schedule, notify, attend or decline
- **Data created (proof of completion):** Meeting with replies
- **Exceptional path:** Invitee declines; meeting cancelled: all invited are told.
- **Accepted when:** Everyone in the chosen audience is notified in their own time zone and can reply.
- **Tests:** T34. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 30 minutes by hand, weight 6.
- **Verified by:** ____________ **on** ____________

### W18. Attendance, minutes and action items

- **Trigger:** Meeting ends
- **Inputs and automatic action:** Mark attendance, write minutes, actions become tasks
- **Data created (proof of completion):** Minutes and tasks
- **Exceptional path:** Attendance not marked: it shows as not marked, not as absent.
- **Accepted when:** Closing a meeting saves the minutes and creates one task per action for its owner.
- **Tests:** T34. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 45 minutes by hand, weight 5.
- **Verified by:** ____________ **on** ____________

### W19. Annual calendar and reminders

- **Trigger:** Seven days and one day before
- **Inputs and automatic action:** HQ enters the date, WDOS reminds
- **Data created (proof of completion):** Reminder to the network in scope
- **Exceptional path:** None.
- **Accepted when:** Reminders go out seven days and one day before to the right audience with nobody pressing Send.
- **Tests:** T34. **Declared:** digital; exceptions covered: no. **Estimate:** monthly, 30 minutes by hand, weight 2.
- **Verified by:** ____________ **on** ____________


## Communication

### W20. Announcement to any level down to chapter and one member

- **Trigger:** HQ or a leader
- **Inputs and automatic action:** Write, choose audience, send or schedule
- **Data created (proof of completion):** Notice in each member's language where written
- **Exceptional path:** A language version is missing: the member receives the main message.
- **Accepted when:** An announcement reaches exactly the chosen audience down to one chapter or one member, and never the other network unless chosen.
- **Tests:** T25. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 30 minutes by hand, weight 7.
- **Verified by:** ____________ **on** ____________

### W21. Birthday, anniversary and inspiration messages

- **Trigger:** The date
- **Inputs and automatic action:** Automatic, no Send button
- **Data created (proof of completion):** Message in the member's language
- **Exceptional path:** Phone notifications off: the message is still in the app inbox.
- **Accepted when:** Messages go out on their dates in the member's language. Today weekly inspiration has English and French texts only.
- **Tests:** T24. **Declared:** digital; exceptions covered: no. **Estimate:** daily, 20 minutes by hand, weight 4.
- **Verified by:** ____________ **on** ____________

### W22. Messages between a member and HQ

- **Trigger:** Member or HQ writes
- **Inputs and automatic action:** Thread with replies and status
- **Data created (proof of completion):** Answered thread
- **Exceptional path:** Unanswered threads stay in Open conversations.
- **Accepted when:** A member's message reaches the shared HQ inbox and the reply reaches the member, signed.
- **Tests:** none yet. **Declared:** digital; exceptions covered: no. **Estimate:** daily, 15 minutes by hand, weight 5.
- **Verified by:** ____________ **on** ____________

### W23. SMS, WhatsApp and voice delivery

- **Trigger:** none
- **Inputs and automatic action:** Not built. In-app and phone notifications only; email when switched on
- **Data created (proof of completion):** none
- **Exceptional path:** Not applicable.
- **Accepted when:** NOT BUILT. Acceptance to be written when a provider is chosen (decision needed).
- **Tests:** none yet. **Declared:** not built; exceptions covered: no. **Estimate:** daily, 20 minutes by hand, weight 6.
- **Verified by:** ____________ **on** ____________


## Community

### W24. Chapter space, circles and moderation

- **Trigger:** Member posts
- **Inputs and automatic action:** Post, report, hide, blocked words held
- **Data created (proof of completion):** Moderated space
- **Exceptional path:** Blocked word: held. Three reports: hidden until HQ decides.
- **Accepted when:** Nothing is deleted, and each moderation decision is logged.
- **Tests:** none yet. **Declared:** digital; exceptions covered: yes. **Estimate:** weekly, 20 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________


## Learning

### W25. Institute enrolment and progress sync

- **Trigger:** none
- **Inputs and automatic action:** Not connected. The Institute keeps its own accounts until the key is set and the Institute calls WDOS
- **Data created (proof of completion):** none
- **Exceptional path:** Not applicable.
- **Accepted when:** NOT CONNECTED. Accepted when the Institute calls WDOS with the integration key and one Person ID is recognised by both, with no second registration.
- **Tests:** T26. **Declared:** not built; exceptions covered: no. **Estimate:** weekly, 30 minutes by hand, weight 5.
- **Verified by:** ____________ **on** ____________


## Support

### W26. Private complaint or safeguarding report

- **Trigger:** Member report
- **Inputs and automatic action:** Member files, HQ case handler works it
- **Data created (proof of completion):** Case with reference and outcome
- **Exceptional path:** A case about an HQ administrator is hidden from that administrator.
- **Accepted when:** Only HQ handlers can read a case; the member reads only the outcome written for her; nothing is sanctioned automatically.
- **Tests:** T27. **Declared:** digital; exceptions covered: yes. **Estimate:** monthly, 45 minutes by hand, weight 2.
- **Verified by:** ____________ **on** ____________

### W27. Account recovery without OTP

- **Trigger:** Member forgets her PIN
- **Inputs and automatic action:** Fingerprint, email link, or HQ reset
- **Data created (proof of completion):** New PIN
- **Exceptional path:** No email and no fingerprint: Chapter Lead reset in person, or HQ temporary password.
- **Accepted when:** A member regains access with no OTP, cannot learn from the screen whether another person's account exists, and old sessions end. Marked part until the security design is reviewed.
- **Tests:** T35. **Declared:** partly digital; exceptions covered: yes. **Estimate:** weekly, 15 minutes by hand, weight 3.
- **Verified by:** ____________ **on** ____________


## Governance

### W28. Policy rules and decision register

- **Trigger:** Management decision
- **Inputs and automatic action:** Versioned rules with effective dates; decisions recorded with approver
- **Data created (proof of completion):** Rule in force, audit entry
- **Exceptional path:** A rule change starts on its date; the old value stays in history.
- **Accepted when:** Every rule and decision shows who approved it and when, and the audit log holds the change.
- **Tests:** T13. **Declared:** digital; exceptions covered: no. **Estimate:** monthly, 30 minutes by hand, weight 1.
- **Verified by:** ____________ **on** ____________

### W29. Backups, restore and monitoring

- **Trigger:** none
- **Inputs and automatic action:** A super administrator takes a full backup from the dashboard with their own password; restore is a command-line tool for an empty database. Scheduled backups and monitoring still depend on the database and hosting services
- **Data created (proof of completion):** Backup file; restore row counts
- **Exceptional path:** Restore into a database that is not empty is refused.
- **Accepted when:** A backup taken from WDOS is restored into an empty TiDB database and the row counts match. Done on a local copy only so far.
- **Tests:** T30. **Declared:** partly digital; exceptions covered: no. **Estimate:** weekly, 60 minutes by hand, weight 2.
- **Verified by:** ____________ **on** ____________


## Programmes

### W30. Programme beneficiary records (WHI, WESAP, YES, SNARP and others)

- **Trigger:** none
- **Inputs and automatic action:** Not built. Only calendar dates are held
- **Data created (proof of completion):** none
- **Exceptional path:** Not applicable.
- **Accepted when:** NOT BUILT. Only calendar dates are held.
- **Tests:** none yet. **Declared:** not built; exceptions covered: no. **Estimate:** monthly, 30 minutes by hand, weight 4.
- **Verified by:** ____________ **on** ____________
