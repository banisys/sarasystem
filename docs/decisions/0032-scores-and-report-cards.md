# 0032 — School module, part B: score periods, the score book and report cards

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Part A ([ADR 0031](0031-school-structure.md)) gave a school its grades, curricula with coefficients, classes' grades and pupils' records per academic year. Part B adds scores and report cards.

The legacy LMS:
- typed report cards by hand: a fixed list of period types in code (مهر، آبان، آذر، نوبت اول، مستمر، …), a hand-typed average, one `lesson_score` row per subject in the report card form;
- had no score book for teachers.

## Options considered
- **Periods:** fixed in code (LMS) / **defined per academic year in the head office** («دوره‌های نمره‌دهی»). Each period has:
  - a title and the days it covers (its absences and homework);
  - a closing date for teachers' entry;
  - a «published» flag.
- **Scale:** from the stage (part A): **numeric out of 20**, or **descriptive** with the four levels of primary school's descriptive assessment (خیلی خوب، خوب، قابل قبول، نیاز به تلاش بیشتر). Each score can have a note.
- **Who enters:**
  - **The subject's teacher in the class**, meaning the teacher of a schedule of that class for that subject ([ADR 0023](0023-class-schedules-and-sessions.md)), until the period closes or is published.
  - **Whoever may edit the class**, at any time; they also correct after publishing.
  - Learners: the class's current attendees. Subjects: the class grade's curriculum (for its major or all).
- **Where:**
  - **User panel «دفتر نمره»:** the teacher's subjects per class, a page per subject and period.
  - **Admin «دفتر نمره»:** an action on a class (page and list), added through Class's `ClassroomExtensions`, which gets an `actions` part.
- **Report cards:** stored and computed once / **worked out when shown** from the record (grade, major), the curriculum (subjects, coefficients) and the period's scores:
  - **weighted average of the scored subjects** for numeric scales (none for descriptive);
  - absences (and excused ones) and lateness in the period's days;
  - the average of the graded homework of those days, each scaled to 20 ([ADR 0030](0030-homework-submissions.md)).

  Nothing goes stale when a score is corrected.
- **Who sees report cards:** learners and their guardians ([ADR 0027](0027-guardians.md)), **only for published periods**, in the user panel «کارنامه» with a PDF. Staff get the PDF of any period from «پرونده‌های تحصیلی» (also unpublished, for checking).
- **PDF:** the mPDF setup of contracts is moved to a core helper, `App\Support\PersianPdf`, which contracts and report cards both use.

## Decision
- **Tables:**
  - `school_score_periods` (`ScorePeriod`, primary tenant): academic year, title, covered days, `entry_closes_on`, `is_published`, order;
  - `school_scores` (`Score`, per tenant): period, learner, subject, class, score or level ([DescriptiveLevel](../../Modules/School/app/Enums/DescriptiveLevel.php)), note, who entered it and when; one per period, learner and subject.
- **Services:**
  - [ScoreBook](../../Modules/School/app/Services/ScoreBook.php): a class's subjects, scale, learners and periods; `refusal()`, `record()` (a number 0–20 or a level; an empty row removes the score), and a teacher's `assignmentsOf()`.
  - [ReportCards](../../Modules/School/app/Services/ReportCards.php): `build()`, `publishedFor()`, `html()`, `pdf()`.
- **Admin:**
  - «مدرسه → دوره‌های نمره‌دهی» (primary tenant; row action «انتشار کارنامه» / «لغو انتشار»);
  - «دفتر نمره» on classes with a grade;
  - «کارنامه (PDF)» on academic records.
- **User panel:**
  - `/school/scores` («دفتر نمره», `School::ScoreBooks`, `School::ScoreBook`);
  - `/school/report-cards` («کارنامه» / «کارنامه‌ی {نام}» with `?for=`, `School::ReportCards`) and its PDF.
  - Header links appear only for users who have something there.
- **Demo:** the demo year's first periods (مهر، آبان، آذر، نوبت اول).

## Consequences
- Teachers enter their own subjects' scores; editors correct them. Publishing a period opens its report cards to families and closes entry for teachers.
- A pupil's report card follows their record for the year: a pupil without a record (no class with a grade) has none.
- No year-final scores, «نوبت» formulas (مستمر + پایانی), resits or rankings yet; a period's card is its own scores. These come later on top of the same scores.
- No activity scores (the LMS teacher notebook): the period score is entered directly.
