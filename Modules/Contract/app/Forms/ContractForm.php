<?php

namespace Modules\Contract\Forms;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\ResolvedForm;
use App\Models\Tenant;
use Modules\Contract\Models\ContractType;

/**
 * The terms staff fill in for a contract (ADR 0020), set up per contract
 * type in the primary tenant (ADR 0017): the built-in dates and amount
 * can be required, optional or not asked, and each type adds its own
 * fields (wage, working hours, …), which are also merge tags of its text.
 */
class ContractForm extends CustomizableForm
{
    public const KEY = 'contract.contract';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'شرایط قرارداد';
    }

    public function sections(): array
    {
        return ['terms' => 'شرایط قرارداد'];
    }

    public function builtInFields(): array
    {
        return [
            new BuiltInField('starts_on', 'تاریخ شروع'),
            new BuiltInField('ends_on', 'تاریخ پایان', FieldMode::Optional),
            new BuiltInField('amount', 'مبلغ (ریال)', FieldMode::Optional),
        ];
    }

    /**
     * The form of a type; types are the primary tenant's.
     */
    public static function for(ContractType $type): ResolvedForm
    {
        return (new self)->resolve(Tenant::primary() ?? $type->tenant, $type);
    }
}
