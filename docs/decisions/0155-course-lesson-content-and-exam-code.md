# 0155 — Mixed lesson content and exam attachment by code

- **Date:** 2026-11-26
- **Status:** accepted

## Context

A self-paced lesson may combine text, a video, handouts and an exam. Previously, a type hid some form fields, and staff created exams separately from the lesson.

## Options considered

- Keep the primary type and use the exam context's standalone creation flow.
- Always show the content fields and attach an existing exam by code.

## Decision

The exam-copying part of this decision was superseded by [ADR 0160](0160-link-original-lesson-exam.md).

Remove the type. Each lesson supports rich text, one private video, up to five private files and one exam attachment by code. An authorized editor can enter the code of an independent exam from the selected branch or primary branch where they have permission to view exams. The form copies its settings, questions or bank selections to the lesson's existing exam context. The copy receives its own code and attempts. The lesson row still opens exam management. The exam field is available when Exam is enabled.

## Consequences

Staff can reuse the same source in several lessons. The original exam's participants and attempts stay with the original. Bank-based copies keep using the banks. Existing lesson exams and files are preserved. If a legacy lesson already has multiple exams, they remain available and the form rejects adding another. A lesson with more than five legacy attachments must be reduced to five when its form is saved. The video upload [sets the lesson duration](0156-course-lesson-video-duration.md). [ADR 0157](0157-course-lesson-audio.md) adds an optional audio track.
