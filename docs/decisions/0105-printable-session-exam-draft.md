# 0105 — Printable paper from a session exam draft

- **Date:** 2026-11-16
- **Status:** accepted

## Context
Staff preparing an online exam from an exam session need to see and print a paper before saving the form.

## Options considered
- Save the exam first and print it from a separate page.
- Preview and print the current form in a modal while the draft remains open.

## Decision
The admin creation page's «پیش‌نمایش آزمون جلسه» action opens a read-only modal with a representative paper drawn in stable order from the active questions of the selected banks. Questions are unique across sections. The modal uses the existing creation authorization and the current branch, and requires enough questions for the selected counts. It displays choice text and answer space without the marking key. «چاپ آزمون» prints the same document shown in the modal.

## Consequences
Previewing and printing do not create an exam, write to the banks, or change how questions are drawn for online attempts. Randomized attempts can differ from the printed paper.

[ADR 0108](0108-manual-admin-exam-question-selection.md) adds explicit choices to the paper when the editor has hand-picked questions from a bank.
