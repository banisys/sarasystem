@use(Modules\Course\Filament\Resources\Courses\CourseResource)
@use(Modules\Course\Models\CourseLesson)

@php
    $fileUrl = fn ($file) => CourseResource::getUrl('lesson-file', [
        'course' => $lesson->course_id,
        'lesson' => $lesson,
        'file' => $file,
    ]);
@endphp

<div class="grid gap-4">
    <h3 class="text-sm font-semibold">پیش‌نمایش فایل‌های ذخیره‌شده</h3>

    @if ($video)
        <video controls playsinline preload="metadata" aria-label="پیش‌نمایش ویدئوی درس"
            class="max-h-80 w-full max-w-2xl rounded-lg bg-black" src="{{ $fileUrl($video) }}"></video>
    @endif

    @if ($audio)
        <audio controls preload="metadata" aria-label="پیش‌نمایش فایل صوتی درس"
            class="w-full max-w-2xl" src="{{ $fileUrl($audio) }}"></audio>
    @endif

    @if ($attachments->isNotEmpty())
        <ul class="grid gap-3">
            @foreach ($attachments as $attachment)
                @php
                    $url = $fileUrl($attachment);
                    $isImage = in_array($attachment->mime_type, CourseLesson::ATTACHMENT_IMAGE_TYPES, true);
                @endphp
                <li class="grid justify-items-start gap-2">
                    @if ($isImage)
                        <img src="{{ $url }}" alt="{{ $attachment->file_name }}" loading="lazy"
                            class="max-h-48 max-w-full rounded-lg object-contain">
                    @endif

                    <x-filament::link :href="$url" target="_blank" rel="noopener">
                        {{ $isImage || $attachment->mime_type === 'application/pdf' ? 'نمایش' : 'دانلود' }}
                        <bdi>{{ $attachment->file_name }}</bdi>
                    </x-filament::link>
                </li>
            @endforeach
        </ul>
    @endif
</div>
