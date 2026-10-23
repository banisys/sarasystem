<?php

namespace Modules\Exam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Http\Controllers\Concerns\ChecksParticipant;
use Modules\Exam\Http\Requests\AnswersRequest;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ExamTaking;

/**
 * Start, save (autosave while answering) and submit the user's attempt.
 */
class AttemptController extends Controller
{
    use ChecksParticipant;

    public function store(Request $request, Exam $exam, ExamTaking $taking): RedirectResponse
    {
        $this->ensureParticipant($exam, $request->user());

        $taking->start($exam, $request->user());

        return to_route('exams.show', $exam);
    }

    public function update(AnswersRequest $request, Exam $exam, ExamTaking $taking): JsonResponse
    {
        $taking->save($this->runningAttempt($request, $exam, $taking), $request->answers());

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    public function submit(AnswersRequest $request, Exam $exam, ExamTaking $taking): RedirectResponse
    {
        $taking->submit($this->runningAttempt($request, $exam, $taking), $request->answers());

        return to_route('exams.show', $exam)->with('status', 'پاسخ‌های شما ثبت شد.');
    }

    /**
     * @throws ValidationException when there is none (its time may be over: it was just closed)
     */
    private function runningAttempt(Request $request, Exam $exam, ExamTaking $taking): ExamAttempt
    {
        $this->ensureParticipant($exam, $request->user());

        return $taking->currentAttempt($exam, $request->user())
            ?? throw ValidationException::withMessages(['answers' => 'آزمون در جریانی ندارید یا زمان آن تمام شده است.']);
    }
}
