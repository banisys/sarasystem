<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TimePicker;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * The admin panel's clock field (ADR 0067): it replaces every TimePicker
 * through the container (AdminPanelProvider), so forms keep writing
 * `TimePicker::make()`.
 *
 * The time is typed straight into the field («930», «17.30», Persian digits)
 * or picked from a panel of hours and five-minute steps; arrow keys move it.
 * Always 24-hour, whatever the operating system. A picker with seconds keeps
 * Filament's own panel.
 */
class ClockPicker extends TimePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seconds(false);
        $this->prefixIcon(Heroicon::OutlinedClock, isInline: true);
    }

    /**
     * Without seconds the state is a plain «HH:MM», as a native time input
     * would give it, so the limits and the state cast use that format.
     */
    public function isNative(): bool
    {
        return $this->hasSeconds() ? parent::isNative() : true;
    }

    public function toEmbeddedHtml(): string
    {
        if ($this->hasSeconds()) {
            return parent::toEmbeddedHtml();
        }

        $id = $this->getId();
        $statePath = $this->getStatePath();
        $isDisabled = $this->isDisabled();
        $isReadOnly = $this->isReadOnly();
        $isEditable = ! ($isDisabled || $isReadOnly);
        $minutesStep = $this->getMinutesStep();

        $input = $this->getExtraInputAttributeBag()
            ->merge([
                ...$this->getAccessibilityAttributes(),
                'aria-controls' => $id.'-panel',
                'aria-haspopup' => 'dialog',
                'autocomplete' => 'off',
                'autofocus' => $this->isAutofocused(),
                'dir' => 'ltr',
                'disabled' => $isDisabled,
                'id' => $id,
                'inputmode' => 'numeric',
                'maxlength' => 5,
                'placeholder' => $this->getPlaceholder() ?? '--:--',
                'readonly' => $isReadOnly,
                'role' => 'combobox',
                'title' => $isEditable ? 'مثلاً ۹۳۰ یا ۱۷:۳۰ · کلیدهای ↑ ↓ '.($minutesStep > 1 ? $minutesStep : 5).' دقیقه، با Shift یک ساعت' : null,
                'type' => 'text',
                'x-bind:aria-expanded' => 'isOpen.toString()',
                'x-model' => 'text',
                'x-ref' => 'input',
            ], escape: false)
            ->class([
                'fi-input',
                'fi-clock-picker-input',
            ]);

        $config = Js::from([
            'minutesStep' => $minutesStep > 1 ? $minutesStep : 5,
            'isEditable' => $isEditable,
        ]);

        $state = $this->applyStateBindingModifiers("\$entangle('{$statePath}')");
        $key = e($this->getLivewireKey()).'.'.substr(md5(serialize([$isDisabled, $isReadOnly, $minutesStep])), 0, 16);

        ob_start(); ?>
            <div
                x-data="saraClockPicker({ state: $wire.<?= $state ?>, ...<?= e($config) ?> })"
                x-on:focusout="if (! $el.contains($event.relatedTarget)) commit(true)"
                x-on:keydown.escape="if (isOpen) { $event.preventDefault(); $event.stopPropagation(); close() }"
                wire:ignore
                wire:key="<?= $key ?>"
                class="fi-clock-picker"
            >
                <input
                    <?php if ($isEditable) { ?>
                        x-on:focus="open()"
                        x-on:click="open()"
                        x-on:input="typed($event)"
                        x-on:keydown.enter.prevent="commit(true)"
                        x-on:keydown.arrow-up.prevent="step(1, $event.shiftKey)"
                        x-on:keydown.arrow-down.prevent="step(-1, $event.shiftKey)"
                        x-on:keydown.page-up.prevent="step(1, true)"
                        x-on:keydown.page-down.prevent="step(-1, true)"
                    <?php } ?>
                    <?= $input->toHtml() ?>
                />

                <?php if ($isEditable) { ?>
                    <div
                        x-ref="panel"
                        x-cloak
                        x-show="isOpen"
                        x-bind:class="{ 'fi-clock-picker-panel-up': opensUp }"
                        x-on:mousedown.prevent
                        id="<?= e($id) ?>-panel"
                        role="dialog"
                        aria-label="<?= e(__('انتخاب ساعت')) ?>"
                        class="fi-clock-picker-panel"
                    >
                        <div class="fi-clock-picker-section">
                            <div class="fi-clock-picker-section-label">ساعت</div>
                            <div class="fi-clock-picker-hours" dir="ltr">
                                <template x-for="h in hours" x-bind:key="h">
                                    <button
                                        type="button"
                                        tabindex="-1"
                                        x-text="pad(h)"
                                        x-on:click="pickHour(h)"
                                        x-bind:aria-pressed="(hour === h).toString()"
                                        x-bind:class="{ 'fi-selected': hour === h, 'fi-clock-picker-now': nowHour === h }"
                                        class="fi-clock-picker-cell"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <div class="fi-clock-picker-section">
                            <div class="fi-clock-picker-section-label">دقیقه</div>
                            <div class="fi-clock-picker-minutes" dir="ltr">
                                <template x-for="m in minutes" x-bind:key="m">
                                    <button
                                        type="button"
                                        tabindex="-1"
                                        x-text="pad(m)"
                                        x-on:click="pickMinute(m)"
                                        x-bind:aria-pressed="(minute === m).toString()"
                                        x-bind:class="{ 'fi-selected': minute === m }"
                                        class="fi-clock-picker-cell"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <div class="fi-clock-picker-footer">
                            <button type="button" tabindex="-1" x-on:click="pickNow()" class="fi-clock-picker-link">اکنون</button>
                            <button type="button" tabindex="-1" x-on:click="clear()" x-show="text" class="fi-clock-picker-link fi-clock-picker-clear">پاک کردن</button>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php $html = ob_get_clean();

        return $this->wrapEmbeddedHtml(
            $this->wrapInputHtml(
                $html,
                attributes: $this->getExtraAttributeBag()
                    ->merge(['x-on:focus-input.stop' => "\$el.querySelector('input')?.focus()"], escape: false)
                    ->class(['fi-fo-date-time-picker']),
            ),
            inlineLabelVerticalAlignment: VerticalAlignment::Center,
        );
    }
}
