<?php

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Exam\Models\QuestionTag;

class QuestionTagSeeder extends Seeder
{
    /** Run explicitly inside the tenant that needs sample tags. */
    public function run(): void
    {
        QuestionTag::query()->firstOrCreate(['title' => 'مرور']);
        QuestionTag::query()->firstOrCreate(['title' => 'تمرین تکمیلی']);
    }
}
