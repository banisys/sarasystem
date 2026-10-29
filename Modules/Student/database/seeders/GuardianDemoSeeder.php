<?php

namespace Modules\Student\Database\Seeders;

use App\Guardians\GuardianRelationship;
use App\Models\Guardianship;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

/**
 * Learners and their parents for local development (ADR 0027), on top of
 * the core DemoSeeder: every demo `student` of a branch gets a learner row
 * there, and each demo `parent` is linked to the student with the same last
 * name (e.g. «محمد محمدی» → «امیر محمدی»). Principals get the learners
 * permissions. Safe to run again.
 */
class GuardianDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    /** Demo parents who are mothers (the rest are fathers). */
    private const array MOTHERS = ['لیلا جعفری', 'شهلا طاهری', 'مهسا زارعی'];

    public function run(): void
    {
        $this->grantDemoPermissions([
            'principal' => ['ViewAny:Student', 'View:Student', 'Update:Student'],
        ]);

        Tenant::query()->where('is_active', true)->get()->each(function (Tenant $tenant): void {
            $students = $this->holders('student', $tenant);
            $parents = $this->holders('parent', $tenant);

            $tenant->execute(fn () => $students->each(fn (User $student) => Student::query()->firstOrCreate(
                ['user_id' => $student->getKey()],
                ['status' => StudentStatus::Active, 'enrolled_at' => '2026-09-23'],
            )));

            foreach ($parents as $parent) {
                $child = $students->first(fn (User $student): bool => self::lastName($student) === self::lastName($parent));

                if ($child !== null) {
                    Guardianship::query()->firstOrCreate(
                        ['guardian_id' => $parent->getKey(), 'ward_id' => $child->getKey()],
                        ['relationship' => in_array($parent->name, self::MOTHERS, true) ? GuardianRelationship::Mother : GuardianRelationship::Father, 'receives_sms' => true],
                    );
                }
            }
        });
    }

    /**
     * Holders of the global role in the tenant.
     *
     * @return Collection<int, User>
     */
    private function holders(string $role, Tenant $tenant)
    {
        $roleId = Role::query()->whereNull('tenant_id')->where('name', $role)->value('id');

        return User::query()->whereIn('id', DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenant->getKey())
            ->select(config('permission.column_names.model_morph_key')))
            ->orderBy('id')
            ->get();
    }

    private static function lastName(User $user): string
    {
        return (string) str($user->name)->afterLast(' ');
    }
}
