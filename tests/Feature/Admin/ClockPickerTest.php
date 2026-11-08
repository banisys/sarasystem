<?php

namespace Tests\Feature\Admin;

use App\Filament\Forms\ClockPicker;
use Filament\Forms\Components\TimePicker;
use Tests\TestCase;

class ClockPickerTest extends TestCase
{
    public function test_every_time_picker_is_the_clock_field_without_seconds(): void
    {
        $picker = TimePicker::make('start_time');

        $this->assertInstanceOf(ClockPicker::class, $picker);
        $this->assertFalse($picker->hasSeconds());
        $this->assertSame('H:i', $picker->getInternalFormat());
    }

    public function test_the_field_shows_a_saved_time_as_hours_and_minutes(): void
    {
        $cast = collect(TimePicker::make('start_time')->getDefaultStateCasts())->last();

        $this->assertSame('09:30', $cast->set('09:30:00'));
        $this->assertSame('17:05', $cast->get('17:05'));
    }

    public function test_a_picker_with_seconds_keeps_filaments_own_panel(): void
    {
        $picker = TimePicker::make('start_time')->seconds();

        $this->assertFalse($picker->isNative());
    }
}
