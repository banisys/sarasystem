# 0095 — Session exams without a start cutoff

- **Date:** 2026-11-14
- **Status:** accepted

## Context

An eligible learner may need to take a published exam after the class session ends.

## Options considered

- Clear the saved end time to remove the cutoff. Restoring it would require recovering the previous value or reading the current session schedule.
- Keep the saved window and provide a per-exam setting to ignore the cutoff for participation.

## Decision

Store a Boolean on the exam, off by default. The session exam form allows enabling it during creation or from the exam list before the first attempt, under the existing settings permissions. The saved opening time still applies. With the option enabled, each attempt gets the configured duration from its own start, even if that duration extends past the session end.

## Consequences

Turning the option off restores the saved cutoff. Session membership, publication, the attempt limit and retake rules still apply. Existing exams retain their cutoff until the option is enabled.
