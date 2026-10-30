<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep legacy columns as an upgrade snapshot; new code only reads form modes.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->jsonb('holders')->default('[]')->change();
            $table->string('required_for')->default('nobody')->change();
        });

        $primaryId = DB::table('tenants')->where('is_primary', true)->value('id');

        if ($primaryId === null) {
            return;
        }

        $forms = [
            'employee' => ['employee.job_application', 'Modules\\Employee\\Models\\JobPosition', 'employee_job_positions'],
            'learner' => ['student.preregistration', 'Modules\\Student\\Models\\Program', 'student_programs'],
        ];

        foreach (DB::table('document_types')->where('tenant_id', $primaryId)->get() as $type) {
            foreach ($forms as $holder => [$form, $ownerType, $ownerTable]) {
                $applies = $type->applicant_uploads && in_array($holder, json_decode($type->holders, true), true);
                $mode = ! $applies || $type->required_for === 'selected'
                    ? 'hidden'
                    : ($type->required_for === 'everyone' ? 'required' : 'optional');
                $key = 'document_'.$type->id;
                $this->setMode($primaryId, $form, null, null, $key, $mode);

                if (! $applies || $type->required_for !== 'selected' || ! Schema::hasTable($ownerTable)) {
                    continue;
                }

                $ownerIds = DB::table('document_type_requirements')->where('document_type_id', $type->id)->where('holder', $holder)->pluck('owner_id');

                foreach (DB::table($ownerTable)->where('tenant_id', $primaryId)->whereIn('id', $ownerIds)->pluck('id') as $ownerId) {
                    $this->setMode($primaryId, $form, $ownerType, $ownerId, $key, 'required');
                }
            }
        }
    }

    private function setMode(int $tenantId, string $form, ?string $ownerType, ?int $ownerId, string $key, string $mode): void
    {
        $identity = ['tenant_id' => $tenantId, 'form' => $form, 'owner_type' => $ownerType, 'owner_id' => $ownerId];
        $row = DB::table('form_customizations')->where($identity)->first();
        $modes = $row === null ? [] : json_decode($row->field_modes, true);
        $modes[$key] ??= $mode;

        if ($row !== null) {
            DB::table('form_customizations')->where('id', $row->id)->update(['field_modes' => json_encode($modes)]);
        } else {
            DB::table('form_customizations')->insert([...$identity, 'field_modes' => json_encode($modes), 'fields' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * The old settings remain intact. New form choices cannot be represented
     * by the old type-wide flags, so rollbacks retain them for a forward fix.
     */
    public function down(): void {}
};
