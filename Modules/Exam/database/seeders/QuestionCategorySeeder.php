<?php

namespace Modules\Exam\Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Exam\Models\QuestionCategory;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;

class QuestionCategorySeeder extends Seeder
{
    /** Run explicitly inside the tenant that needs sample categories. */
    public function run(): void
    {
        $tenant = Tenant::current() ?? throw NoCurrentTenant::make();

        DB::transaction(function () use ($tenant): void {
            $tenant->newQuery()->whereKey($tenant->id)->lock('for no key update')->firstOrFail();
            $root = QuestionCategory::query()->firstOrCreate(['title' => 'نمونه | مهارت‌های پایه', 'parent_id' => null]);
            $digital = $root->children()->firstOrCreate(['title' => 'سواد دیجیتال']);
            $digital->children()->firstOrCreate(['title' => 'ارزیابی منابع']);
            $root->children()->firstOrCreate(['title' => 'منطق و محاسبه']);
        });
    }
}
