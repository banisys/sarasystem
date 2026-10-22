# Overview

## What it is
SaraSystem is an education management platform that combines three areas:
- **LMS** (Learning Management System): courses, content, exams, learner progress
- **TMS** (Training Management System): planning and running training programs, trainers, enrollments
- **SIS** (Student Information System): schools, students, classes, attendance, grades, report cards, parents

It is a **redesign of the existing LMS project** (`../LMS`, see [legacy-lms.md](legacy-lms.md)): some features carry over, some are added, some are dropped.

The current organization using it is the **Saraye Danesh (سرای دانش)** K-12 school chain in Iran: about 20 schools/branches plus a head office (ستاد), online/virtual schools, about 11k students and about 50k users. The legacy system is mainly used for SIS and school operations: students, classes, online sessions, exams, contracts/payments and staff HR.

## Users
From the legacy role list, grouped:
- **Head office (ستاد):** CEO, main-office managers (education, finance, informatics), developers/superadmins
- **School management:** school manager, deputies (education, executive, disciplinary), school finance, school informatics, student registration officer
- **Teaching staff:** teachers, supervisor teachers, supporters (پشتیبان), psychologists, consultants, exam managers, content creators
- **Other staff:** employees, janitors, building managers
- **Students** and **parents**

_(TODO: confirm which user groups SaraSystem targets; is it single-organization or multi-organization (SaaS)?)_

## Goals
- _(TODO)_

## Non-goals / out of scope
- _(TODO: features of LMS that are deliberately dropped go here.)_

## Core features
| Feature | Domain | Source | Status | Notes |
|---|---|---|---|---|
| _(TODO)_ | | from LMS / new | planned | |

## Constraints
- Persian-first UI (RTL, Jalali dates); English also supported in LMS
- Iranian education system: grade tree دبستان / متوسطه اول / متوسطه دوم with majors, and an academic year from Shahrivar to Mordad
- Live legacy data must be migratable (production DB in `../LMS`, about 300 tables)
- _(TODO: hosting, performance, legal, etc.)_
