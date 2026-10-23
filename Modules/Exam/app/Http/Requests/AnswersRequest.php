<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Answers of a running attempt, keyed by answer id: an option key or text.
 */
class AnswersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<int|string, string|null>
     */
    public function answers(): array
    {
        return $this->validated('answers') ?? [];
    }
}
