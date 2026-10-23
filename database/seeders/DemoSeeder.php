<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Demo data for local development: three branches next to the head office,
 * the usual school roles and a few users per role. Every user logs in with
 * password "password" and an email like teacher1.north@sarasystem.test.
 * Safe to run again (looks records up by slug / name / email).
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Global roles: label, permissions, and whether holders are staff
     * (panel_user, admin-panel access).
     *
     * @var array<string, array{label: string, permissions: list<string>, staff: bool}>
     */
    private const array ROLES = [
        'hq_member' => ['label' => 'کارمند ستاد', 'permissions' => ['ViewAny:User', 'View:User'], 'staff' => true],
        'principal' => ['label' => 'مدیر شعبه', 'permissions' => ['ViewAny:User', 'View:User', 'Create:User', 'Update:User'], 'staff' => true],
        'teacher' => ['label' => 'معلم', 'permissions' => [], 'staff' => true],
        'supporter' => ['label' => 'پشتیبان', 'permissions' => [], 'staff' => true],
        'supervisor' => ['label' => 'ناظر', 'permissions' => [], 'staff' => true],
        'parent' => ['label' => 'والد', 'permissions' => [], 'staff' => false],
        'student' => ['label' => 'دانش‌آموز', 'permissions' => [], 'staff' => false],
        'user' => ['label' => 'کاربر', 'permissions' => [], 'staff' => false],
    ];

    /**
     * @var array<string, string> slug => name
     */
    private const array BRANCHES = [
        'north' => 'شعبه شمال',
        'center' => 'شعبه مرکز',
        'west' => 'شعبه غرب',
    ];

    /**
     * Users per branch: role => names, one name per user.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const array BRANCH_USERS = [
        'north' => [
            'principal' => ['مریم احمدی'],
            'teacher' => ['علی رضایی', 'زهرا کریمی'],
            'supporter' => ['حسین موسوی'],
            'supervisor' => ['فاطمه حسینی'],
            'student' => ['امیر محمدی', 'سارا جعفری', 'رضا نوری'],
            'parent' => ['محمد محمدی', 'لیلا جعفری'],
            'user' => ['نرگس صادقی'],
        ],
        'center' => [
            'principal' => ['احمد طاهری'],
            'teacher' => ['نسرین قاسمی', 'مهدی کاظمی'],
            'supporter' => ['الهام رحیمی'],
            'supervisor' => ['جواد اکبری'],
            'student' => ['کیان طاهری', 'هستی کاظمی', 'آرش سلیمانی'],
            'parent' => ['شهلا طاهری', 'بهرام سلیمانی'],
            'user' => ['پویا عباسی'],
        ],
        'west' => [
            'principal' => ['سمیه باقری'],
            'teacher' => ['رامین شریفی', 'مینا یوسفی'],
            'supporter' => ['کامران زارعی'],
            'supervisor' => ['آزاده فرهادی'],
            'student' => ['نیما باقری', 'ترانه یوسفی', 'یاسمن زارعی'],
            'parent' => ['فرهاد باقری', 'مهسا زارعی'],
            'user' => ['شیما نادری'],
        ],
    ];

    private int $sequence = 0;

    public function run(): void
    {
        $this->seedRoles();

        $headOffice = Tenant::firstOrCreate(['slug' => 'head-office'], ['name' => 'ستاد']);

        foreach (['پریسا کرمانی', 'بابک افشار'] as $index => $name) {
            $this->seedUser($name, 'hq_member', $index + 1, 'hq', [$headOffice]);
        }

        $branches = collect(self::BRANCHES)->map(fn (string $name, string $slug): Tenant => Tenant::firstOrCreate(['slug' => $slug], ['name' => $name]));

        foreach (self::BRANCH_USERS as $slug => $usersByRole) {
            foreach ($usersByRole as $role => $names) {
                foreach ($names as $index => $name) {
                    $this->seedUser($name, $role, $index + 1, $slug, [$branches[$slug]]);
                }
            }
        }

        // A teacher who also teaches in another branch (roles per tenant).
        $this->assign(User::where('email', 'teacher1.north@sarasystem.test')->firstOrFail(), 'teacher', $branches['center']);
    }

    private function seedRoles(): void
    {
        Role::findOrCreate(Utils::getPanelUserRoleName(), 'web');

        foreach (self::ROLES as $name => $definition) {
            $role = Role::findOrCreate($name, 'web');
            $role->fill(['label' => $definition['label']])->save();
            $role->syncPermissions(array_map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'), $definition['permissions']));
        }
    }

    /**
     * @param  list<Tenant>  $tenants
     */
    private function seedUser(string $name, string $role, int $number, string $place, array $tenants): void
    {
        $this->sequence++;

        $user = User::firstOrCreate(
            ['email' => "{$role}{$number}.{$place}@sarasystem.test"],
            [
                'name' => $name,
                'password' => 'password',
                'mobile' => sprintf('0912%07d', 1000000 + $this->sequence),
                'national_code' => $this->nationalCode($this->sequence),
            ],
        );

        foreach ($tenants as $tenant) {
            $this->assign($user, $role, $tenant);
        }
    }

    private function assign(User $user, string $role, Tenant $tenant): void
    {
        $roles = self::ROLES[$role]['staff'] ? [$role, Utils::getPanelUserRoleName()] : [$role];

        $tenant->execute(fn () => $user->unsetRelation('roles')->assignRole($roles));
        $user->unsetRelation('roles');
    }

    /**
     * A valid national code (check digit included) for the demo user number.
     */
    private function nationalCode(int $number): string
    {
        $digits = sprintf('001%06d', $number);

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }

        $remainder = $sum % 11;

        return $digits.($remainder < 2 ? $remainder : 11 - $remainder);
    }
}
