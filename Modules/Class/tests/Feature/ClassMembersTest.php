<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\MembersRelationManager;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * People in a class with class roles, and the class rules (ADR 0022).
 */
class ClassMembersTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private ClassRole $learner;

    private ClassRole $teacher;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();

        $studentRole = $this->roleWithPermissions('student', []);
        $teacherRole = $this->roleWithPermissions('teacher', []);

        $this->learner = ClassRole::factory()->for($this->primary)->attendee()->create(['allowed_role_ids' => [$studentRole->id]]);
        $this->teacher = ClassRole::factory()->for($this->primary)->teacher()->create(['allowed_role_ids' => [$teacherRole->id]]);
        $this->classroom = Classroom::factory()->for($this->west)->create(['capacity' => 2]);

        $this->west->makeCurrent();
    }

    public function test_people_are_added_with_a_class_role_they_are_allowed(): void
    {
        $student = $this->person('student');
        $teacher = $this->person('teacher');

        app(ClassMembers::class)->add($this->classroom, $this->learner, [$student->id]);
        app(ClassMembers::class)->add($this->classroom, $this->teacher, [$teacher->id]);

        $this->assertSame(1, $this->classroom->attendeeCount());
        $this->assertSame($this->west->id, ClassMember::query()->where('user_id', $student->id)->sole()->tenant_id);

        $this->expectExceptionMessage('نقش لازم');
        app(ClassMembers::class)->add($this->classroom, $this->teacher, [$student->id]);
    }

    public function test_a_person_of_another_tenant_is_not_added(): void
    {
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->primary, 'student');

        $this->expectException(ClassException::class);
        app(ClassMembers::class)->add($this->classroom, $this->learner, [$outsider->id]);
    }

    public function test_attendees_fit_the_capacity_and_leaving_frees_a_seat(): void
    {
        [$first, $second, $third] = [$this->person('student'), $this->person('student'), $this->person('student')];
        $members = app(ClassMembers::class);

        $members->add($this->classroom, $this->learner, [$first->id, $second->id], today()->subWeek());

        try {
            $members->add($this->classroom, $this->learner, [$third->id]);
            $this->fail('The class was over its capacity.');
        } catch (ClassException $exception) {
            $this->assertStringContainsString('ظرفیت', $exception->getMessage());
        }

        $leaving = ClassMember::query()->where('user_id', $first->id)->sole();
        $members->update($leaving, $this->learner, $leaving->joined_on, today()->subDay());
        $members->add($this->classroom, $this->learner, [$third->id]);

        $this->assertSame(2, $this->classroom->attendeeCount());

        // Coming back reuses the row, if a seat is free.
        $this->expectExceptionMessage('ظرفیت');
        $members->add($this->classroom, $this->learner, [$first->id]);
    }

    public function test_sub_class_attendees_come_from_the_parent_class(): void
    {
        $lab = Classroom::factory()->for($this->west)->create(['parent_id' => $this->classroom->id]);
        [$inClass, $notInClass] = [$this->person('student'), $this->person('student')];
        app(ClassMembers::class)->add($this->classroom, $this->learner, [$inClass->id]);

        app(ClassMembers::class)->add($lab, $this->learner, [$inClass->id]);
        $this->assertSame(1, $lab->attendeeCount());

        $this->expectExceptionMessage('اعضای زیرکلاس از کلاس اصلی می‌آیند');
        app(ClassMembers::class)->add($lab, $this->learner, [$notInClass->id]);
    }

    public function test_the_members_tab_adds_people_for_whoever_may_edit_the_class(): void
    {
        $student = $this->person('student');
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        foreach ([[$editor, 'class_editor'], [$viewer, 'class_viewer']] as [$user, $role]) {
            $this->makeStaff($user, $this->west);
            $this->grantRole($user, $this->west, $role);
        }

        $this->actingInPanel($viewer, $this->west);
        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class])
            ->assertActionHidden(TestAction::make('addMembers')->table());

        $this->actingInPanel($editor, $this->west);
        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class])
            ->callAction(TestAction::make('addMembers')->table(), data: ['class_role_id' => $this->learner->id, 'user_ids' => [$student->id], 'joined_on' => '2026-10-01'])
            ->assertHasNoActionErrors()
            ->assertCanSeeTableRecords(ClassMember::query()->get());

        $member = ClassMember::query()->sole();
        $this->assertSame($student->id, $member->user_id);
        $this->assertSame('2026-10-01', $member->joined_on->toDateString());
    }

    public function test_the_members_tab_shows_this_classs_members_only(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $other = Classroom::factory()->for($this->west)->create();
        $mine = ClassMember::factory()->create(['classroom_id' => $this->classroom->id, 'class_role_id' => $this->learner->id]);
        $theirs = ClassMember::factory()->create(['classroom_id' => $other->id, 'class_role_id' => $this->learner->id]);

        $this->actingInPanel($admin, $this->west);
        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get(ClassroomResource::getUrl('view', ['record' => $this->classroom], tenant: $this->west))->assertOk();
    }

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->west, $role);

        return $user;
    }
}
