<?php

namespace App\Filament\Support;

use App\Support\Identifiers;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A text box in the filters dropdown that keeps the rows whose column
 * contains the typed text (case-insensitive; Persian digits are read as
 * Latin, so ۰۹۱۲ finds 0912). Panel tables have no search
 * box (AdminPanelProvider), so text lookups are filters like this one.
 *
 * Searches the column named like the filter; ->attributes() searches
 * several columns instead (a row matches if any of them contains the text).
 */
class TextFilter extends BaseFilter
{
    /**
     * @var array<string>|null
     */
    protected ?array $attributes = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query(function (Builder $query, array $data): void {
            $value = trim((string) ($data['value'] ?? ''));

            if ($value === '') {
                return;
            }

            $pattern = '%'.addcslashes(Identifiers::toLatinDigits($value), '%_\\').'%';

            $query->where(function (Builder $query) use ($pattern): void {
                foreach ($this->getAttributes() as $attribute) {
                    $query->orWhereLike($query->qualifyColumn($attribute), $pattern);
                }
            });
        });

        $this->indicateUsing(function (array $state): array {
            $value = trim((string) ($state['value'] ?? ''));

            return $value === '' ? [] : [Indicator::make("{$this->getLabel()}: {$value}")];
        });
    }

    /**
     * @param  array<string>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getAttributes(): array
    {
        return $this->attributes ?? [$this->getName()];
    }

    public function getFormField(): TextInput
    {
        return TextInput::make('value')
            ->label($this->getLabel());
    }
}
