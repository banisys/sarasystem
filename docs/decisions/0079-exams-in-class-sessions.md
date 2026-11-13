# 0079 — Independent exams in class sessions

- **Date:** 2026-11-10
- **Status:** accepted

[ADR 0089](0089-shared-question-library-and-session-exam-generation.md) replaced new template assignments with generation from the question library. Existing session copies and their results remain supported.

## Context
The user wants to use Exam in class sessions, and explicitly chose independent results and attempt limits for every session. Exam and Class must remain independently switchable (ADR 0041).

## Options considered
- **Link the same exam to several sessions** — easy reuse, but a learner's attempts and results would be shared. Rejected by the user.
- **Add session IDs to attempts** — separates attempts, but couples the attempt lifecycle, administration and grading to an additional scope.
- **Create an independent exam per session from a prepared exam** — retains the existing taking/scoring lifecycle and makes every sitting's settings editable in the existing admin resource. Chosen.
- **Copy named participants** versus **resolve session membership dynamically** — copied names become stale; the host module instead supplies a tenant-scoped participant query.

## Decision
- Exam owns the optional `ExamContextSource` extension contract, `ExamContext` value object and container-owned `ExamContexts` registry. Class registers `SessionExamSource` only while Exam is enabled. Class uses this contract and no Exam models; Exam knows no Class models, tables or permissions. Neither manifest declares a hard dependency.
- Nullable `exams.context_type`, `context_id`, and `template_exam_id` identify the host and original exam. Only the original-exam reference has a foreign key (within Exam). A composite unique key prevents duplicate assignment of the same prepared exam to the same context/tenant. Existing standalone exams keep their named/role participants.
- «آزمون‌های جلسه» opens the Exam-owned context page. In the user panel, the button is in the «جزئیات جلسه» tab. Class staff (teachers, substitutes and current non-attendee staff, per `SessionAccess`) and class editors may assign any published standalone exam in their tenant, publish/unpublish session copies, and review/grade only attempts attached to that session. These local capabilities do not grant global Exam administration permissions.
- The session exams page opens on the exam list, followed by the creation and grading tabs. Creating a draft opens the exam list, and returning from grading opens the results tab.
- Assignment locks the prepared exam, copies its settings and bank sections, and replaces its window with the session's current start/end. It copies neither explicit participants nor attempts. Repeating an assignment returns the existing copy. A different session gets a different exam. Question banks remain shared; each attempt still snapshots questions when started (ADR 0013).
- Participants are class members with an attendee role whose joined/left dates include the session date (inclusive, null is unbounded). Membership is checked dynamically for lists, navigation, opening, start, autosave and submission. Explicit users/roles on a contextual exam cannot bypass this rule; their fields are hidden in its admin form.
- Cancelled/deleted sessions and unregistered/disabled hosts fail closed. Guardians have no acting-as-child exam link or access. Existing results are retained when a session disappears or an exam is unpublished; global authorized exam administrators can still inspect them.
- The context page shows paginated attempts to its managers only. A separate review page shows answers and permits manual grading of written answers after submission, with bounds validation, grader attribution and a transaction/attempt lock. Choice scores and answers from another attempt cannot be submitted as grades. Participant results continue to honor the existing result-visibility setting.
- The person who assigned a copy can delete it from the context page while they manage the session and it has no attempts. [ADR 0088](0088-delete-unused-session-exams.md) covers legacy copies and the lock shared with attempt start.
- The daily-operations tutorial includes an optional Exam mission with a state check and automatic completion/reset. The system-map manifest records the Class → Exam connection.

## Consequences
- No new package, core hook or hard cross-module database dependency is needed. Disabling either module removes the integration while retaining data.
- Times are copied once. Moving a session later does not reschedule its exam: authorized admins edit the session copy in «آزمون‌ها». This is explained on the page and in the tutorial. The saved end time caps attempt duration by default. [ADR 0095](0095-session-exams-without-a-start-cutoff.md) adds a setting for late starts with the full attempt duration.
- Unpublishing blocks even in-progress participant requests; it preserves saved answers. Cancelling the session also blocks entry. Neither action deletes results or automatically submits attempts.
- Results remain local to Exam; no automatic transfer into School score books/report cards, no cross-session averages or notifications. Abandoned attempts still close lazily, including when a manager opens their review page.
- The host reference intentionally has no foreign key: deletion leaves historical copies. Exam scoring settings still use the live session copy; bank edits affect future starts, not already-served questions.
