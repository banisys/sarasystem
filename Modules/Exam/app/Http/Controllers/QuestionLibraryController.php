<?php

namespace Modules\Exam\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\ExamSection;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Support\QuestionLibrary;

class QuestionLibraryController extends Controller
{
    public function __construct(private readonly QuestionLibrary $library) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $bankId = $request->integer('bank');
        $bank = $bankId > 0 ? $this->library->banks($actor)->findOrFail($bankId) : null;
        $banks = $this->library->banks($actor)
            ->withCount(['questions' => fn (Builder $query) => $query->where(fn (Builder $visible) => $this->library->visibleTo($visible, $actor))])
            ->latest('id')->paginate(15, pageName: 'banks_page')->withQueryString();
        $questions = $this->library->questions($actor)
            ->when($bank, fn (Builder $query) => $query->whereHas('banks', fn (Builder $banks) => $banks->whereKey($bank->id)))
            ->with(['banks' => fn ($query) => $query->where('owner_id', $actor->id)])
            ->latest('id')->paginate(15, pageName: 'questions_page')->withQueryString();

        return Inertia::render('Exam::QuestionLibrary', [
            'banks' => $banks->through(fn (QuestionBank $record): array => [
                'id' => $record->id, 'title' => $record->title, 'description' => $record->description,
                'is_shared' => $record->is_shared, 'is_mine' => $record->owner_id === $actor->id,
                'questions_count' => $record->questions_count,
            ]),
            'questions' => $questions->through(fn (Question $question): array => [
                'id' => $question->id, 'type' => $question->type->value, 'body' => $question->body,
                'options' => $question->options, 'accepted_answers' => $question->accepted_answers,
                'model_answer' => $question->model_answer, 'score' => $question->score, 'is_active' => $question->is_active,
                'is_shared' => $question->is_shared, 'is_mine' => $question->owner_id === $actor->id,
                'bank_ids' => $question->banks->modelKeys(),
            ]),
            'ownBanks' => QuestionBank::query()->where('owner_id', $actor->id)->orderBy('title')->get(['id', 'title']),
            'selectedBank' => $bank?->only(['id', 'title']),
            'types' => collect(QuestionType::cases())->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->getLabel()]),
        ]);
    }

    public function storeBank(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $bank = new QuestionBank;
        $bank->forceFill(['owner_id' => $actor->id]);
        $this->saveBank($request, $bank);

        return to_route('exams.library')->with('status', 'بانک سؤال ساخته شد.');
    }

    public function updateBank(Request $request, QuestionBank $bank): RedirectResponse
    {
        abort_unless($bank->owner_id === $this->actor($request)->id, 404);
        $this->saveBank($request, $bank);

        return to_route('exams.library')->with('status', 'بانک سؤال ذخیره شد.');
    }

    public function destroyBank(Request $request, QuestionBank $bank): RedirectResponse
    {
        abort_unless($bank->owner_id === $this->actor($request)->id, 404);

        DB::transaction(function () use ($bank): void {
            $locked = QuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
            if (ExamSection::query()->whereBelongsTo($locked, 'questionBank')->exists()) {
                throw ValidationException::withMessages(['delete_bank' => 'این بانک به یک آزمون متصل است و فعلاً قابل حذف نیست.']);
            }

            $locked->delete();
        });

        return to_route('exams.library')->with('status', 'بانک حذف شد. سؤال‌های آن در فهرست سؤال‌ها باقی ماندند.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $question = new Question;
        $question->forceFill(['owner_id' => $actor->id]);
        $this->saveQuestion($request, $question, $actor);

        return to_route('exams.library')->with('status', 'سؤال ساخته شد.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($question->owner_id === $actor->id, 404);
        $this->saveQuestion($request, $question, $actor);

        return to_route('exams.library')->with('status', 'سؤال ذخیره شد.');
    }

    public function destroyQuestion(Request $request, Question $question): RedirectResponse
    {
        abort_unless($question->owner_id === $this->actor($request)->id, 404);
        $question->delete();

        return to_route('exams.library')->with('status', 'سؤال حذف شد.');
    }

    public function addSharedQuestion(Request $request, Question $question): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate(['bank_id' => ['required', 'integer']], attributes: ['bank_id' => 'بانک سؤال']);

        DB::transaction(function () use ($data, $actor, $question): void {
            $bank = QuestionBank::query()->where('owner_id', $actor->id)->lockForUpdate()->findOrFail($data['bank_id']);
            $shared = $this->library->questions($actor)->lockForUpdate()->findOrFail($question->id);
            $bank->questions()->syncWithoutDetaching([$shared->id]);
        });

        return to_route('exams.library')->with('status', 'سؤال به بانک شما اضافه شد.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($this->library->canAccess($user, Tenant::current()), 403);

        return $user;
    }

    private function saveBank(Request $request, QuestionBank $bank): void
    {
        $bank->forceFill($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_shared' => ['required', 'boolean'],
        ], attributes: ['title' => 'عنوان', 'description' => 'توضیحات', 'is_shared' => 'اشتراک‌گذاری']))->save();
    }

    private function saveQuestion(Request $request, Question $question, User $actor): void
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string', 'max:20000', function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                if ($request->input('type') === QuestionType::FillInTheBlank->value && ! Question::hasExactlyOneBlank((string) $value)) {
                    $fail('در متن سؤال دقیقاً یک جای خالی با سه زیرخط (___) مشخص کنید.');
                }
            }],
            'score' => ['required', 'numeric', 'min:0.25', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'is_shared' => ['required', 'boolean'],
            'bank_ids' => ['present', 'array', 'list', 'max:50'],
            'bank_ids.*' => ['required', 'integer', 'distinct'],
            'options' => ['exclude_unless:type,choice', 'required', 'array', 'list', 'min:2', 'max:10'],
            'options.*' => ['required', 'array:text,is_correct'],
            'options.*.text' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['required', 'boolean'],
            'accepted_answers' => ['exclude_unless:type,fill_in_the_blank', 'required', 'array', 'list', 'min:1', 'max:10'],
            'accepted_answers.*' => ['required', 'string', 'max:255'],
            'model_answer' => ['exclude_unless:type,descriptive', 'nullable', 'string', 'max:20000'],
        ], attributes: [
            'type' => 'نوع سؤال', 'body' => 'متن سؤال', 'score' => 'بارم', 'bank_ids' => 'بانک‌ها',
            'options' => 'گزینه‌ها', 'options.*.text' => 'متن گزینه', 'accepted_answers.*' => 'پاسخ پذیرفتنی', 'model_answer' => 'پاسخ نمونه',
        ]);

        if (isset($data['options']) && count(array_filter($data['options'], fn (array $option): bool => (bool) $option['is_correct'])) !== 1) {
            throw ValidationException::withMessages(['options' => 'دقیقاً یک گزینه باید پاسخ درست باشد.']);
        }

        DB::transaction(function () use ($question, $data, $actor): void {
            $bankIds = QuestionBank::query()->where('owner_id', $actor->id)->whereKey($data['bank_ids'])
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (count($bankIds) !== count($data['bank_ids'])) {
                throw ValidationException::withMessages(['bank_ids' => 'سؤال را فقط می‌توانید به بانک‌های خودتان اضافه کنید.']);
            }

            unset($data['bank_ids']);
            $question->forceFill($data)->save();
            $oldBankIds = $question->banks()->where('owner_id', $actor->id)->pluck('exam_question_banks.id')->all();
            $question->banks()->detach(array_diff($oldBankIds, $bankIds));
            $question->banks()->syncWithoutDetaching($bankIds);
        });
    }
}
