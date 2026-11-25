<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'courses' => 'courses_tenant_id_course_category_id_foreign',
            'course_categories' => 'course_categories_tenant_id_parent_id_foreign',
            'course_sections' => 'course_sections_tenant_id_course_id_foreign',
            'course_lessons' => 'course_lessons_section_fk',
            'course_enrollments' => 'course_enrollments_tenant_id_course_id_foreign',
            'course_completions' => 'course_completions_tenant_id_course_lesson_id_foreign',
            'course_instructors' => 'course_instructors_tenant_id_course_id_foreign',
        ] as $table => $foreign) {
            Schema::table($table, fn (Blueprint $table) => $table->dropForeign($foreign));
        }

        Schema::table('course_category_course', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'course_id']);
            $table->dropForeign('course_additional_category_fk');
        });

        $this->makeValuesUnique('course_categories', 'title', ' ');
        $this->makeValuesUnique('course_categories', 'alias', '-');
        $this->makeValuesUnique('courses', 'code', '-', 64);
        $this->consolidateSettingsAndTeacherProfiles();

        foreach ([
            'courses', 'course_categories', 'course_sections', 'course_lessons', 'course_enrollments',
            'course_completions', 'course_instructors', 'course_category_course', 'course_teachers',
            'course_enrollment_settings',
        ] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('tenant_id'));
        }

        Schema::table('course_categories', function (Blueprint $table): void {
            $table->unique('title');
            $table->unique('alias');
            $table->foreign('parent_id')->references('id')->on('course_categories');
            $table->index('parent_id');
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->unique('code');
            $table->foreign('course_category_id')->references('id')->on('course_categories');
            $table->index('course_category_id');
            $table->index(['is_published', 'id']);
        });
        Schema::table('course_sections', function (Blueprint $table): void {
            $table->unique(['course_id', 'id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->foreign(['course_id', 'course_section_id'], 'course_lessons_section_fk')
                ->references(['course_id', 'id'])->on('course_sections');
            $table->index(['course_id', 'course_section_id']);
            $table->index('course_section_id');
        });
        Schema::table('course_category_course', function (Blueprint $table): void {
            $table->primary(['course_id', 'course_category_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('course_category_id', 'course_additional_category_fk')->references('id')->on('course_categories');
            $table->index('course_category_id');
        });
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->unique(['course_id', 'user_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index('user_id');
        });
        Schema::table('course_completions', function (Blueprint $table): void {
            $table->unique(['course_lesson_id', 'user_id']);
            $table->foreign('course_lesson_id')->references('id')->on('course_lessons')->cascadeOnDelete();
        });
        Schema::table('course_instructors', function (Blueprint $table): void {
            $table->unique(['course_id', 'user_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index('user_id');
        });
        Schema::table('course_teachers', fn (Blueprint $table) => $table->unique('user_id'));
        DB::statement('ALTER TABLE course_enrollment_settings ADD CONSTRAINT course_enrollment_settings_singleton CHECK (id = 1)');
    }

    private function makeValuesUnique(string $table, string $column, string $separator, int $maxLength = 255): void
    {
        $duplicates = DB::table($table)->select($column)->whereNotNull($column)->groupBy($column)->havingRaw('count(*) > 1');

        DB::table($table)->whereIn($column, $duplicates)->select('id', $column)->chunkById(200, function ($rows) use ($table, $column, $separator, $maxLength): void {
            foreach ($rows as $row) {
                if (! DB::table($table)->where($column, $row->{$column})->where('id', '<', $row->id)->exists()) {
                    continue;
                }

                $number = $row->id;
                do {
                    $suffix = $separator.$number++;
                    $value = mb_substr($row->{$column}, 0, $maxLength - mb_strlen($suffix)).$suffix;
                } while (DB::table($table)->where($column, $value)->exists());

                DB::table($table)->where('id', $row->id)->update([$column => $value]);
            }
        });
    }

    private function consolidateSettingsAndTeacherProfiles(): void
    {
        $primary = DB::table('tenants')->where('is_primary', true)->value('id');
        $settings = DB::table('course_enrollment_settings')
            ->orderByRaw('tenant_id = ? DESC', [$primary])
            ->orderByRaw('updated_at DESC NULLS LAST')->orderByDesc('id')->value('id');

        if ($settings !== null) {
            DB::table('course_enrollment_settings')->where('id', '<>', $settings)->delete();
            DB::table('course_enrollment_settings')->where('id', $settings)->update(['id' => 1]);
        }

        DB::delete('
            DELETE FROM course_teachers WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY user_id ORDER BY (tenant_id = ?) DESC, updated_at DESC NULLS LAST, id DESC
                    ) AS rank FROM course_teachers
                ) AS profiles WHERE rank > 1
            )
        ', [$primary]);
    }

    public function down(): void
    {
        throw new RuntimeException('Course ownership and superseded branch settings cannot be restored without a pre-migration backup.');
    }
};
