<?php

namespace Modules\Course\Services;

use App\Support\SessionLessonLibrary;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Modules\Course\Models\CourseLesson;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonContent implements SessionLessonLibrary
{
    public function idsByCode(array $codes): array
    {
        return $codes === [] ? [] : $this->publishedLessons()->whereIn('code', $codes)->pluck('id', 'code')->all();
    }

    public function sharedContent(array $ids, Closure $fileUrl): array
    {
        if ($ids === []) {
            return [];
        }

        $lessons = $this->publishedLessons()->whereKey($ids)->with('media')->get()->keyBy('id');
        $result = [];
        foreach ($ids as $id) {
            $lesson = $lessons->get($id);
            if ($lesson !== null) {
                $content = $this->describe($lesson, fn (int $file): string => $fileUrl($id, $file));
                $content['lesson']['code'] = $lesson->code;
                $result[] = $content;
            }
        }

        return $result;
    }

    public function sharedFile(int $lesson, int $file): BinaryFileResponse
    {
        return $this->file($this->publishedLessons()->findOrFail($lesson), $file);
    }

    /** @param Closure(int): string $fileUrl */
    public function describe(CourseLesson $lesson, Closure $fileUrl): array
    {
        $video = $lesson->getFirstMedia('video');
        $audio = $lesson->getFirstMedia('audio');

        return [
            'lesson' => [
                ...$lesson->only('id', 'title', 'duration_minutes'),
                'body' => $lesson->body_html === null ? $lesson->body : null,
                'bodyHtml' => str($lesson->body_html ?? '')->sanitizeHtml()->toString(),
            ],
            'video' => $video ? $fileUrl($video->getKey()) : null,
            'audio' => $audio ? $fileUrl($audio->getKey()) : null,
            'attachments' => $lesson->getMedia('attachments')->map(fn (Media $file): array => [
                'name' => $file->file_name,
                'url' => $fileUrl($file->getKey()),
            ])->values()->all(),
        ];
    }

    public function file(CourseLesson $lesson, int $file, bool $previewAttachments = false): BinaryFileResponse
    {
        $media = $lesson->media()->whereIn('collection_name', ['video', 'audio', 'attachments'])->findOrFail($file);
        $path = $media->getPath();
        abort_unless(File::isFile($path), 404);

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        $inlineTypes = match ($media->collection_name) {
            'video' => CourseLesson::VIDEO_TYPES,
            'audio' => CourseLesson::AUDIO_TYPES,
            'attachments' => $previewAttachments ? [...CourseLesson::ATTACHMENT_IMAGE_TYPES, 'application/pdf'] : [],
        };

        $response = in_array($media->mime_type, $inlineTypes, true)
            ? response()->file($path, [...$headers, 'Content-Type' => $media->mime_type])
            : response()->download($path, null, [...$headers, 'Content-Type' => 'application/octet-stream']);

        return $response->setPrivate();
    }

    /** @return Builder<CourseLesson> */
    private function publishedLessons(): Builder
    {
        return CourseLesson::query()->whereHas('course', fn (Builder $course) => $course->published());
    }
}
