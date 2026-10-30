<?php

namespace App\CustomFields;

/**
 * A field the form has in code (with its own rules and storage) that an
 * organization may make optional or leave out (ADR 0017). Fields the
 * system depends on are not listed as built-in fields at all.
 */
final readonly class BuiltInField
{
    /**
     * @param  list<FieldMode>  $modes  the modes an organization may choose
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldMode $default = FieldMode::Required,
        public array $modes = [FieldMode::Required, FieldMode::Optional, FieldMode::Hidden],
        public ?string $group = null,
        public ?string $groupDescription = null,
    ) {}

    public function allows(FieldMode $mode): bool
    {
        return in_array($mode, $this->modes, true);
    }
}
