<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('class_sessions')->whereIn('kind', ['make_up', 'extra'])->update([
            'kind' => 'non_instructional',
            'subject_id' => null,
        ]);

        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_kind_and_subject_check
            CHECK (kind IN ('regular', 'non_instructional', 'exam')
                AND (kind <> 'non_instructional' OR subject_id IS NULL))");
    }

    /**
     * The original kind and subject cannot be recovered.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE class_sessions DROP CONSTRAINT class_sessions_kind_and_subject_check');

        DB::table('class_sessions')->where('kind', 'non_instructional')->update(['kind' => 'extra']);
    }
};
