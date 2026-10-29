<?php

namespace Modules\Contract\Http\Controllers;

use App\Auth\OneTimePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Models\Contract;
use Modules\Contract\Services\ContractException;
use Modules\Contract\Services\ContractRenderer;
use Modules\Contract\Services\Contracts;

/**
 * The person's side of contracts (ADR 0020): their issued, signed and
 * cancelled contracts in the current tenant, reading one, its PDF, and
 * signing it with an SMS code sent to their mobile.
 */
class MyContractController extends Controller
{
    public function index(Request $request): Response
    {
        $contracts = Contract::query()->visibleToPerson($request->user())
            ->with(['contractType', 'parent'])
            ->latest('issued_at')
            ->get()
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'title' => $contract->contractType->title,
                'number' => $contract->number,
                'kind_line' => self::kindLine($contract),
                'status' => $contract->status->value,
                'status_label' => $contract->status->getLabel(),
                'starts_on' => ContractRenderer::date($contract->starts_on),
                'ends_on' => ContractRenderer::date($contract->ends_on),
            ]);

        return Inertia::render('Contract::Index', ['contracts' => $contracts]);
    }

    public function show(Request $request, Contract $contract, ContractRenderer $renderer): Response
    {
        $this->ensureOwn($request, $contract);
        $user = $request->user();

        return Inertia::render('Contract::Show', [
            'contract' => [
                'id' => $contract->id,
                'title' => $contract->contractType->title,
                'number' => $contract->number,
                'status' => $contract->status->value,
                'status_label' => $contract->status->getLabel(),
                'cancellation_reason' => $contract->cancellation_reason,
                'html' => $renderer->document($contract),
                'can_sign' => $contract->canBeSignedElectronically() && filled($user->mobile),
                'signs_on_paper' => $contract->status === ContractStatus::Issued && ! $contract->contractType->signature_method->allowsElectronic(),
            ],
            'mobile' => $user->mobile,
            'codeLength' => OneTimePassword::LENGTH,
            'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
        ]);
    }

    public function pdf(Request $request, Contract $contract, ContractRenderer $renderer): HttpResponse
    {
        $this->ensureOwn($request, $contract);

        return response($renderer->pdf($contract), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$renderer->fileName($contract).'"',
        ]);
    }

    public function sendCode(Request $request, Contract $contract, OneTimePassword $otp): RedirectResponse
    {
        $this->ensureOwn($request, $contract);
        $user = $request->user();

        if (! $contract->canBeSignedElectronically() || blank($user->mobile)) {
            throw ValidationException::withMessages(['code' => 'این قرارداد را نمی‌توانید الکترونیکی امضا کنید.']);
        }

        if (($wait = $otp->secondsUntilResend($user, self::purpose($contract))) > 0) {
            throw ValidationException::withMessages(['code' => "{$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $otp->send($user, self::purpose($contract));

        return back()->with('status', "کد امضا به {$user->mobile} پیامک شد.");
    }

    public function sign(Request $request, Contract $contract, OneTimePassword $otp, Contracts $contracts): RedirectResponse
    {
        $this->ensureOwn($request, $contract);
        $request->validate([
            'code' => ['required', 'string'],
            'accept' => ['accepted'],
        ], attributes: ['accept' => 'پذیرش متن قرارداد']);

        $user = $request->user();
        $throttleKey = "sign-contract:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($throttleKey, OneTimePassword::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'تعداد تلاش‌ها زیاد بود؛ کمی بعد دوباره امتحان کنید.']);
        }

        if (! $otp->verify($user, $request->string('code')->trim()->toString(), self::purpose($contract))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['code' => 'کد وارد شده درست نیست یا منقضی شده است.']);
        }

        RateLimiter::clear($throttleKey);

        try {
            $contracts->signElectronically($contract, $user, $request->ip());
        } catch (ContractException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return back()->with('status', 'قرارداد امضا شد.');
    }

    /**
     * Only the person's own, issued (or later) contracts; the tenant scope
     * keeps them to the current tenant.
     */
    private function ensureOwn(Request $request, Contract $contract): void
    {
        abort_unless(Contract::query()->visibleToPerson($request->user())->whereKey($contract->getKey())->exists(), 404);
    }

    /**
     * e.g. «الحاقیه‌ی قرارداد ک-12» (ADR 0021); null for a contract of its own.
     */
    private static function kindLine(Contract $contract): ?string
    {
        return match ($contract->kind) {
            ContractKind::Amendment => 'الحاقیه‌ی قرارداد '.$contract->parent?->number,
            ContractKind::Renewal => 'تمدید قرارداد '.$contract->parent?->number,
            default => null,
        };
    }

    private static function purpose(Contract $contract): string
    {
        return OneTimePassword::PURPOSE_SIGN.':contract-'.$contract->getKey();
    }
}
