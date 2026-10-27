<?php

namespace Tests\Feature\CustomFields;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\FieldType;
use App\Models\FormCustomization;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\TenantNote;
use Tests\TestCase;

/**
 * Customizable forms in the core (ADR 0017), with a test form whose owner
 * records are TenantNotes.
 */
class CustomizableFormTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private CustomizableForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        TenantNote::createTable();
        $this->tenant = Tenant::factory()->create();
        $this->form = new class extends CustomizableForm
        {
            public function key(): string
            {
                return 'test.form';
            }

            public function label(): string
            {
                return 'فرم آزمایشی';
            }

            public function sections(): array
            {
                return ['main' => 'اصلی'];
            }

            public function builtInFields(): array
            {
                return [
                    new BuiltInField('phone', 'تلفن', FieldMode::Optional),
                    new BuiltInField('email', 'ایمیل', modes: [FieldMode::Required, FieldMode::Optional]),
                ];
            }
        };
    }

    public function test_without_a_setup_the_defaults_apply(): void
    {
        $form = $this->form->resolve($this->tenant);

        $this->assertSame(FieldMode::Optional, $form->mode('phone'));
        $this->assertSame(FieldMode::Required, $form->mode('email'));
        $this->assertSame([], $form->fields);
    }

    public function test_an_owners_setup_wins_over_the_whole_forms_and_adds_its_fields(): void
    {
        $note = TenantNote::query()->create(['tenant_id' => $this->tenant->id, 'title' => 'یادداشت']);
        $this->customize(null, ['phone' => 'required', 'email' => 'optional'], [['key' => 'a', 'label' => 'الف', 'type' => 'text']]);
        $this->customize($note, ['phone' => 'hidden'], [['key' => 'b', 'label' => 'ب', 'type' => 'number']]);

        $general = $this->form->resolve($this->tenant);
        $own = $this->form->resolve($this->tenant, $note);

        $this->assertSame(FieldMode::Required, $general->mode('phone'));
        $this->assertSame(['a'], array_map(fn (CustomField $field): string => $field->key, $general->fields));
        $this->assertSame(FieldMode::Hidden, $own->mode('phone'));
        $this->assertSame(FieldMode::Optional, $own->mode('email'));
        $this->assertSame(['a', 'b'], array_map(fn (CustomField $field): string => $field->key, $own->fields));
    }

    public function test_modes_a_field_does_not_allow_are_ignored(): void
    {
        $this->customize(null, ['email' => 'hidden']);

        $this->assertSame(FieldMode::Required, $this->form->resolve($this->tenant)->mode('email'));
    }

    public function test_another_tenants_setup_is_not_used(): void
    {
        $other = Tenant::factory()->create();
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $other->id, 'form' => 'test.form', 'field_modes' => ['phone' => 'hidden'], 'fields' => [['key' => 'x', 'label' => 'x', 'type' => 'text']],
        ]);

        $form = $this->form->resolve($this->tenant);

        $this->assertSame(FieldMode::Optional, $form->mode('phone'));
        $this->assertSame([], $form->fields);

        $this->tenant->makeCurrent();
        $this->assertSame(0, FormCustomization::query()->count());
    }

    public function test_answers_are_normalized_and_shown(): void
    {
        $date = new CustomField('d', 'تاریخ', FieldType::Date);
        $number = new CustomField('n', 'عدد', FieldType::Number);
        $checkbox = new CustomField('c', 'تیک', FieldType::Checkbox);
        $multi = new CustomField('m', 'چند', FieldType::MultiSelect, options: ['الف', 'ب']);

        $this->assertSame('1370/01/05', $date->prepare('۱۳۷۰/۰۱/۰۵'));
        $this->assertSame('1991-03-25', $date->normalize('1370/01/05'));
        $this->assertSame('1370/01/05', $date->display('1991-03-25'));
        $this->assertSame(12.5, $number->normalize('12.5'));
        $this->assertNull($number->normalize(''));
        $this->assertFalse($checkbox->normalize(null));
        $this->assertSame('خیر', $checkbox->display(false));
        $this->assertSame('الف، ب', $multi->display(['الف', 'ب']));
        $this->assertSame([], CustomField::fromArray(['type' => 'text', 'options' => ['x']])->options);
        $this->assertSame(['x'], CustomField::fromArray(['type' => 'select', 'options' => [' x ', 'x', '']])->options);
    }

    /**
     * @param  array<string, string>  $modes
     * @param  list<array<string, mixed>>  $fields
     */
    private function customize(?TenantNote $owner, array $modes = [], array $fields = []): void
    {
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id,
            'form' => 'test.form',
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->id,
            'field_modes' => $modes,
            'fields' => $fields,
        ]);
    }
}
