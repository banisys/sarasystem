<?php

namespace Modules\Document\Services;

use App\CustomFields\BuiltInField;
use App\CustomFields\FieldMode;
use App\Documents\DocumentHolders;
use App\Models\Tenant;
use Modules\Document\Models\DocumentType;

/**
 * Document requests belong to each form, not to the reusable type catalog.
 */
class DocumentFormFields
{
    public static function key(int $typeId): string
    {
        return 'document_'.$typeId;
    }

    /** @return list<BuiltInField> */
    public static function fields(): array
    {
        return DocumentType::definedTypes()->ordered()->get()
            ->map(fn (DocumentType $type): BuiltInField => new BuiltInField(
                self::key($type->getKey()),
                $type->title.($type->is_active ? '' : ' (غیرفعال)'),
                FieldMode::Hidden,
                group: 'مدارک',
                groupDescription: 'مدارک درخواستی در این فرم را مشخص کنید؛ تعریف نوع مدرک به‌تنهایی آن را به فرم اضافه نمی‌کند.',
            ))->all();
    }

    /** @return array<int, FieldMode> */
    public static function modes(string $holderKey, ?int $ownerId): array
    {
        $holder = DocumentHolders::find($holderKey);
        $primary = Tenant::primary();

        if ($holder === null || $primary === null) {
            return [];
        }

        $owner = $ownerId === null ? null : $holder->requirementOwnerModel()::withoutTenantScope()
            ->where('tenant_id', $primary->getKey())->find($ownerId);

        if ($ownerId !== null && $owner === null) {
            return [];
        }

        $modes = $holder->form()->resolve($primary, $owner)->modes;
        $result = [];

        foreach ($modes as $key => $mode) {
            if (str_starts_with($key, 'document_')) {
                $result[(int) substr($key, strlen('document_'))] = $mode;
            }
        }

        return $result;
    }
}
