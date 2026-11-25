# 0140 — Primary content type for self-paced course activities

- **Date:** 2026-11-25
- **Status:** accepted

## Context

The LMS activity editor classifies an activity as a file, video, or exam. SaraSystem already supports activities containing text, video, attachments, and links to the optional Exam module in the same lesson.

## Options considered

- Split activities into mutually exclusive content types and give each type separate storage and rendering rules.
- Store the primary content type on the existing lesson and keep its content and access rules.

## Decision

Use a stored enum with the LMS options «فایل»، «ویدیو» and «آزمون». The activity editor saves the type and the table shows it as a badge. New activities default to «فایل», which includes text and document activities. The migration labels lessons with an existing video as «ویدیو» and defaults the others to «فایل». Existing exam associations stay available, and editors can classify their activity explicitly.

Staff configure exams from the existing «آزمون‌ها» row action. Exam must be enabled to assign the exam type. Staff can retain a saved exam type while editing other fields when Exam is disabled. Changing the type preserves the lesson body, attachments, video and any linked exam.

## Consequences

The activity type is available from both the course edit page and the direct activities page. Content, publication and access still follow the existing lesson and exam rules.
