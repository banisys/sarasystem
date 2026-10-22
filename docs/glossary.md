# Glossary

Domain terms used in this project. Include the Persian term when the UI or users use it.

## Domains
| Term | Persian | Meaning |
|---|---|---|
| LMS | سامانه مدیریت یادگیری | Learning Management System: courses, content, exams, learner progress. Also the name of the legacy project at `../LMS`. |
| TMS | سامانه مدیریت آموزش | Training Management System: planning and running training programs, trainers, schedules, enrollments. |
| SIS | سامانه اطلاعات دانش‌آموزی | Student Information System: students, classes, attendance, grades, report cards, parents. |

## Organization & school structure
| Term | Persian | Meaning |
|---|---|---|
| Saraye Danesh | سرای دانش | The school chain that uses the system; "sarayedanesh" is also the name of the original Laravel 6 system. |
| Head office | ستاد | Central office over all schools (roles `main_office_*`). |
| Instance | | A site/tenant in LMS's multi-site setup. Each school branch is one instance; most data has `instance_id`. |
| School | مدرسه | A school/branch (`schl_schools`), tied to an instance and with a manager and gender. |
| Academic year | سال تحصیلی | `schl_years`, from about 1 Shahrivar to 31 Mordad. Most operational data is year-scoped. |
| Grade | پایه | Node in the grade tree: stage (دبستان، متوسطه اول، متوسطه دوم) → grade (هفتم…), with a major for upper secondary (تجربی، ریاضی، انسانی). |
| Shared / optional | مشترک | Grades or classes shared across grades/majors; a student can have optional classes besides the main class. |
| Classroom | کلاس | A class of students in a school/grade/year (`schl_classrooms`). |
| Classroom plan | برنامه کلاس | A course (subject) taught in a classroom by a teacher (+ supporter), possibly shared with other classrooms. |
| Weekly schedule | برنامه هفتگی | Recurring time slots of classroom plans. |
| Activity / activity session | فعالیت / جلسه | Teaching activity and its individual sessions (often online classes via Adobe Connect), with attendance and homework. |
| Supporter | پشتیبان | Staff member who follows up on students for a course/class. |
| Left student | دانش‌آموز انصرافی | A student who left the school mid-year. |
| Advising time | وقت مشاوره | Booked counselling/advising slots. |
| Report card | کارنامه | Term/year result of a student; lesson scores feed it. |
| Exam balance | تراز آزمون | Normalized/weighted exam score per balance type. |

## Finance & admin
| Term | Persian | Meaning |
|---|---|---|
| Main contract | قرارداد اصلی / شهریه | Student tuition contract (no `parent_id`). |
| Supplementary contract | قرارداد تکمیلی | Extra contract attached to a main contract (`parent_id` set). |
| School service | خدمات مدرسه | Items/services sold to students (books, packages), requested via service requests. |
| Leave | مرخصی | Staff leave request, approved through leave groups/operators. |
| Cartable | کارتابل | Approval inbox/workflow: records waiting for a user's review, routed through entity groups and verifiers. |
| Entity group | گروه | Group of users used for routing approvals (verifiers) and access. |
| Block | بلوک / ویجت | A configurable widget placed in theme regions (header, sidebar, …) per route and instance. |
