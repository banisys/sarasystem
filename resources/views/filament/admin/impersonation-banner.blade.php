{{--
    Shown on every panel page while a super admin is logged in as another
    user (App\Auth\Impersonation); the button logs them back in as themselves.
--}}
@php($impersonator = \App\Auth\Impersonation::impersonator())
@if ($impersonator)
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-amber-500 px-4 py-2 text-sm text-white">
        <span>
            شما ({{ $impersonator->name }}) با حساب <strong>{{ auth()->user()->name }}</strong> وارد شده‌اید.
        </span>
        <form method="POST" action="{{ route('impersonation.leave') }}">
            @csrf
            <button type="submit" class="rounded-md bg-white/20 px-3 py-1 font-medium hover:bg-white/30">
                بازگشت به حساب خودم
            </button>
        </form>
    </div>
@endif
