<?php

namespace App\Filament\CustomFields;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\FieldType;
use App\Models\FormCustomization;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;

/**
 * Sets up a customizable form in the current tenant (ADR 0017): the mode
 * of each built-in field and the added fields. As a header action it sets
 * up the whole form; as a row action ($perRecord) the form for that record
 * only, where an empty mode means "as in the whole form" and its added
 * fields come after the whole form's ones.
 */
class CustomizeFormAction
{
    public static function make(string $name, CustomizableForm $form, bool $perRecord = false): Action
    {
        return Action::make($name)
            ->label($perRecord ? 'فیلدهای فرم' : "تنظیم فرم {$form->label()}")
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading(fn (?Model $record): string => $perRecord && $record !== null
                ? "فیلدهای فرم {$form->label()} برای «{$record->getAttribute('title')}»"
                : "تنظیم فرم {$form->label()}")
            ->modalWidth('5xl')
            ->fillForm(fn (?Model $record): array => self::load($form, $perRecord ? $record : null))
            ->schema(fn (): array => self::components($form, $perRecord))
            ->action(function (array $data, ?Model $record) use ($form, $perRecord): void {
                self::save($form, $perRecord ? $record : null, $data);

                Notification::make()->title('تنظیمات فرم ذخیره شد.')->success()->send();
            });
    }

    /**
     * @return list<Component>
     */
    public static function components(CustomizableForm $form, bool $perRecord): array
    {
        $sections = $form->sections();

        return [
            ...collect($form->allBuiltInFields())
                ->groupBy(fn (BuiltInField $field): string => $field->group ?? 'فیلدهای ثابت')
                ->map(fn ($fields, string $group): Section => Section::make($group)
                    ->description($perRecord
                        ? 'خالی یعنی همان تنظیم کلی فرم.'
                        : ($fields->first()->groupDescription
                            ?? 'فیلدهایی که سامانه به آن‌ها نیاز دارد (مثل نام و کد ملی) همیشه پرسیده می‌شوند و این‌جا نیستند.'))
                    ->columns(3)
                    ->schema($fields->map(fn (BuiltInField $field): Select => Select::make("field_modes.{$field->key}")
                        ->label($field->label)
                        ->options(collect($field->modes)->mapWithKeys(fn (FieldMode $mode): array => [$mode->value => $mode->getLabel()])->all())
                        ->placeholder($perRecord ? 'مثل تنظیم کلی فرم' : null)
                        ->selectablePlaceholder($perRecord)
                        ->required(! $perRecord))->all()))
                ->values()
                ->all(),
            Section::make($perRecord ? 'فیلدهای اضافه برای این مورد' : 'فیلدهای اضافه')
                ->description($perRecord
                    ? 'بعد از فیلدهای اضافه‌ی کلی فرم، در بخشی که انتخاب می‌کنید نمایش داده می‌شوند.'
                    : 'در انتهای بخشی که انتخاب می‌کنید نمایش داده می‌شوند. جواب‌های قبلی با تغییر یا حذف فیلد از بین نمی‌روند.')
                ->schema([
                    Repeater::make('fields')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->addActionLabel('افزودن فیلد')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->columns(3)
                        ->schema([
                            Hidden::make('key')->default(fn (): string => CustomField::newKey()),
                            TextInput::make('label')
                                ->label('عنوان')
                                ->required()
                                ->maxLength(150),
                            Select::make('type')
                                ->label('نوع')
                                ->options(FieldType::class)
                                ->default(FieldType::Text->value)
                                ->required()
                                ->live(),
                            Select::make('section')
                                ->label('بخش فرم')
                                ->options($sections)
                                ->default(array_key_last($sections))
                                ->required(),
                            TagsInput::make('options')
                                ->label('گزینه‌ها')
                                ->helperText('هر گزینه را بنویسید و Enter بزنید.')
                                ->visible(fn (Get $get): bool => self::type($get('type'))->hasOptions())
                                ->required(fn (Get $get): bool => self::type($get('type'))->hasOptions())
                                ->columnSpanFull(),
                            TextInput::make('help')
                                ->label('راهنما')
                                ->helperText('زیر فیلد نمایش داده می‌شود (اختیاری).')
                                ->maxLength(300)
                                ->columnSpan(2),
                            Toggle::make('required')
                                ->label('الزامی')
                                ->helperText('برای تیک: باید زده شود.')
                                ->inline(false),
                        ]),
                ]),
        ];
    }

    /**
     * @return array{field_modes: array<string, string|null>, fields: list<array<string, mixed>>}
     */
    public static function load(CustomizableForm $form, ?Model $owner): array
    {
        $row = self::row($form, $owner);
        $modes = [];

        foreach ($form->allBuiltInFields() as $field) {
            $modes[$field->key] = $row?->modeFor($field)?->value ?? ($owner === null ? $field->default->value : null);
        }

        return [
            'field_modes' => $modes,
            'fields' => array_map(fn (CustomField $field): array => $field->toArray(), $row?->customFields() ?? []),
        ];
    }

    /**
     * @param  array{field_modes?: array<string, string|null>, fields?: array<array-key, array<string, mixed>>}  $data
     */
    public static function save(CustomizableForm $form, ?Model $owner, array $data): FormCustomization
    {
        $modes = [];

        foreach ($form->allBuiltInFields() as $field) {
            $mode = FieldMode::tryFrom((string) ($data['field_modes'][$field->key] ?? ''));

            if ($mode !== null && $field->allows($mode)) {
                $modes[$field->key] = $mode->value;
            }
        }

        $keys = [];
        $fields = [];

        foreach (array_values($data['fields'] ?? []) as $item) {
            $field = CustomField::fromArray($item);

            // A copied item would share its key with the original.
            if (in_array($field->key, $keys, true)) {
                $field = CustomField::fromArray([...$field->toArray(), 'key' => null]);
            }

            $keys[] = $field->key;
            $fields[] = $field->toArray();
        }

        $tenant = Tenant::current() ?? throw NoCurrentTenant::make();

        return FormCustomization::query()->updateOrCreate([
            'tenant_id' => $owner?->getAttribute('tenant_id') ?? $tenant->getKey(),
            'form' => $form->key(),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ], [
            'field_modes' => $modes,
            'fields' => $fields,
        ]);
    }

    private static function row(CustomizableForm $form, ?Model $owner): ?FormCustomization
    {
        return FormCustomization::query()
            ->where('form', $form->key())
            ->where('owner_type', $owner?->getMorphClass())
            ->where('owner_id', $owner?->getKey())
            ->first();
    }

    private static function type(mixed $type): FieldType
    {
        return $type instanceof FieldType ? $type : (FieldType::tryFrom((string) $type) ?? FieldType::Text);
    }
}
