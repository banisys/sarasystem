<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Supplies lesson content; the session owner authorizes every read and download. */
interface SessionLessonLibrary
{
    /**
     * @param  list<string>  $codes
     * @return array<string, int> Published lesson IDs keyed by code.
     */
    public function idsByCode(array $codes): array;

    /**
     * @param  list<int>  $ids
     * @param  Closure(int, int): string  $fileUrl
     * @return list<array{lesson: array{id: int, title: string, code: string, duration_minutes: ?int, body: ?string, bodyHtml: string}, video: ?string, audio: ?string, attachments: list<array{name: string, url: string}>}>
     */
    public function sharedContent(array $ids, Closure $fileUrl): array;

    public function sharedFile(int $lesson, int $file): BinaryFileResponse;
}
