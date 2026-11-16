<?php

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Exam\Models\ExamGroup;

class ExamGroupSeeder extends Seeder
{
    /** Run explicitly inside the tenant that needs sample groups. */
    public function run(): void
    {
        ExamGroup::query()->firstOrCreate(['title' => 'آزمون‌های پایان دوره']);
        ExamGroup::query()->firstOrCreate(['title' => 'آزمون‌های میان‌دوره']);
    }
}
