# Overview

## What it is
SaraSystem is an education management platform that combines three areas:
- **LMS** (Learning Management System): courses, content, exams, learner progress
- **TMS** (Training Management System): planning and running training programs, trainers, enrollments
- **SIS** (Student Information System): schools, students, classes, attendance, grades, report cards, parents

It is a **redesign of the existing LMS project** (`../LMS`, see [legacy-lms.md](legacy-lms.md)): some features carry over, some are added, some are dropped.

SaraSystem is a **generic product**: one installation serves one organization, which may be a school chain, a company (internal training) or a language institute with branches. The organization's units (schools / branches / departments) are **tenants** ([ADR 0002](decisions/0002-tenancy-model.md)). Features must not assume the organization is a school.

The first deployment target is the **Saraye Danesh (سرای دانش)** K-12 school chain in Iran: about 20 schools/branches plus a head office (ستاد), online/virtual schools, about 11k students and about 50k users. The legacy system is mainly used for SIS and school operations: students, classes, online sessions, exams, contracts/payments and staff HR.

## Users
From the legacy role list, grouped:
- **Head office (ستاد):** CEO, main-office managers (education, finance, informatics), developers/superadmins
- **School management:** school manager, deputies (education, executive, disciplinary), school finance, school informatics, student registration officer
- **Teaching staff:** teachers, supervisor teachers, supporters (پشتیبان), psychologists, consultants, exam managers, content creators
- **Other staff:** employees, janitors, building managers
- **Students** and **parents**

The lists above are for a school deployment. A company deployment has trainees, trainers and training managers; a language institute has learners, teachers and branch managers. Roles are per tenant (a person can hold different roles in different tenants).

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
- Iranian education system for school deployments: grade tree دبستان / متوسطه اول / متوسطه دوم with majors, and an academic year from Shahrivar to Mordad. These must be optional/configurable, not hard-coded, because company and language-institute deployments don't have them.
- Live legacy data must be migratable (production DB in `../LMS`, about 300 tables)
- _(TODO: hosting, performance, legal, etc.)_
