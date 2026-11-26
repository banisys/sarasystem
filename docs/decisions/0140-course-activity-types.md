# 0140 — Primary content type for self-paced course lessons

- **Date:** 2026-11-25
- **Status:** superseded by [ADR 0155](0155-course-lesson-content-and-exam-code.md)

## Context

The LMS activity editor classifies an activity as a file, video, or exam. SaraSystem already supports lessons containing text, video, attachments, and links to the optional Exam module in the same lesson.

## Options considered

- Split lessons into mutually exclusive content types and give each type separate storage and rendering rules.
- Store the primary content type on the existing lesson and keep its content and access rules.

## Decision

Use a stored enum with the LMS options «فایل»، «ویدیو» and «آزمون». The lesson editor saves the type and the table shows it as a badge. New lessons default to «فایل», which includes text and document lessons. The migration labels lessons with an existing video as «ویدیو» and defaults the others to «فایل». Existing exam associations stay available, and editors can classify their lesson explicitly.

Changing the type immediately switches the form between file upload, video upload and exam management. The exam form links to the lesson’s exam page once the lesson is saved. Staff can also open it from the «آزمون‌ها» row action. Exam must be enabled to assign the exam type. Staff can retain a saved exam type while editing other fields when Exam is disabled. Changing the type preserves the lesson body, attachments, video and any linked exam.

## Consequences

Staff manage the lesson type on the dedicated lessons page. Content, publication and access still follow the existing lesson and exam rules.
