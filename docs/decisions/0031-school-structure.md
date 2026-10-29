# 0031 — School module, part A: stages, grades, curricula and academic records

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Classes ([ADR 0022](0022-classes.md)–[ADR 0030](0030-homework-submissions.md)) are generic. A school also needs:
- its grade tree (دوره ← پایه, majors in upper secondary);
- each grade's subjects with coefficients (ضریب);
- which grade a class is;
- each pupil's grade per academic year.

Later it needs scores and report cards (part B, its own ADR).

The legacy LMS `School` module:
- had `schl_years`, a grade tree (`schl_grades`), and subjects per grade **and year** with `score_factor` (`schl_courses`, remade every year);
- had report cards with a fixed list of periods and a hand-typed average;
- had no score book for teachers.

Its other school parts (exam balances, supporter reports to parents, left students, school services, advising times) are left out for now, by the user's choice. LMS data is not migrated.

## Options considered
- **Where:** in Class / Student, or **a `School` module on top of them**. Class and Student stay generic and don't know School. An organization that is not a school doesn't enable it.
- **Academic year:** a new table / **Class's terms** (a school year is a term).
- **Grades:** fixed in code (the Iranian system) / **defined in the head office**:
  - stages, each with its score scale for part B (numeric out of 20, or descriptive);
  - grades in stages; a grade «رشته دارد» for upper secondary;
  - majors.

  The order learners move up in is the stages' order, then the grades'.
- **Curriculum:** per grade and year (LMS) / **per grade (and optionally major)**: subject (Class's subjects), coefficient, weekly hours. It is set once and not copied every year. A row without a major is for every major.
- **A class's grade:** a column in Class / **a School table and a Class hook**. `ClassroomExtensions` (Class) lets modules add form fields (which load and save themselves), page entries, and list columns and filters. School adds «پایه / رشته».
- **The pupil's grade per year («پرونده‌ی تحصیلی»):**
  - **Filled in automatically:** an attendee who joins a top-level class with a grade and a term gets that grade, major and class for the term. Changing the class's grade moves its learners' records. The record can also be edited by hand.
  - **Promotion:** a bulk action makes next year's records one grade up, in the same major when the next grade has majors and with no class yet. Learners in the last grade are counted as graduated. Those who already have a record for that year are skipped.

## Decision
- **Module:** `Modules/School`, enabled; it depends on Class and Student.
- **Tables:**
  - primary tenant: `school_stages` (`Stage`: title, scale, order), `school_grades` (`Grade`: stage, title, `has_majors`, order), `school_majors` (`Major`);
  - `school_curricula` (`Curriculum`: grade, major or null, subject, coefficient, weekly hours, order);
  - per tenant: `school_classroom_grades` (`ClassroomGrade`), `school_enrollments` (`Enrollment`: user, term, grade, major, class; one per tenant, user and term).
- **Service** [Enrollments](../../Modules/School/app/Services/Enrollments.php): `setClassroomGrade()`, `memberJoined()` (on `ClassMember::created`), `save()` (one record per term; a major only and always for grades with majors), `promote()`.
- **Admin group «مدرسه»:**
  - «پرونده‌های تحصیلی»: per tenant, with filters by year, grade, major and class, and the bulk action «ارتقا به سال بعد».
  - Primary tenant only: «دوره‌های تحصیلی», «پایه‌ها و برنامه‌ی درسی» (a grade's page has the tab «برنامه‌ی درسی»), «رشته‌ها».
  - Classes get «پایه / رشته» in the form, on the page, and as a list column and filter.
  - The learner page gets the tab «پرونده‌ی تحصیلی».
- **Demo:** `php artisan module:seed School` sets up the three Iranian stages and their grades, the three majors, grade 7's curriculum (the demo subjects), and «هفتم الف» of each branch as grade 7.

## Consequences
- A school's subjects, coefficients and grades are in place for scores and report cards (part B): periods per academic year, the teacher's score book, report cards with weighted averages.
- Admission (Student programs) doesn't set a grade yet. A pupil's record starts when they join a class with a grade, or is made by hand.
- Grades and majors are not deleted while classes or records use them; stages while they have grades.
