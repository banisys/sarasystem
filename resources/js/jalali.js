// Jalali (Persian) calendar helpers built on the browser's Intl persian
// calendar, so no date library is needed (ADR 0015). Gregorian dates are
// Date objects at UTC midnight; Jalali dates are {year, month, day}.
const jalaliParts = new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    timeZone: 'UTC',
});
const DAY = 86_400_000;

export const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// Saturday first, as on Iranian calendars.
export const weekdayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

export function toJalali(date) {
    const parts = Object.fromEntries(jalaliParts.formatToParts(date).map((part) => [part.type, part.value]));

    return { year: Number(parts.year), month: Number(parts.month), day: Number(parts.day) };
}

function compare(first, second) {
    return first.year - second.year || first.month - second.month || first.day - second.day;
}

// The Gregorian date of a Jalali date, or null when the date doesn't exist (e.g. 30 Esfand of a common year).
export function toGregorian(year, month, day) {
    const target = { year, month, day };
    // 1 Farvardin falls on 20–22 March, so this guess is at most a couple of days off.
    let time = Date.UTC(year + 621, 2, 21) + ((month - 1) * 31 - Math.max(0, month - 7) + day - 1) * DAY;

    for (let step = 0; step < 5; step++) {
        const difference = compare(toJalali(new Date(time)), target);
        if (difference === 0) {
            return new Date(time);
        }
        time += difference < 0 ? DAY : -DAY;
    }

    return null;
}

export function monthLength(year, month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;

    return toGregorian(year, 12, 30) ? 30 : 29;
}

// 0 = Saturday … 6 = Friday.
export function firstWeekday(year, month) {
    return (toGregorian(year, month, 1).getUTCDay() + 1) % 7;
}

export function today() {
    const now = new Date();

    return toJalali(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())));
}

export function toLatinDigits(value) {
    return String(value).replace(/[۰-۹]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)).replace(/[٠-٩]/g, (digit) => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit));
}

export function toPersianDigits(value) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
}

// "1370/01/05" (Persian digits and "-" too, as App\Rules\JalaliDate accepts) → {year, month, day}, or null.
export function parse(value) {
    const matches = toLatinDigits(value ?? '').trim().replace(/-/g, '/').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    if (!matches) {
        return null;
    }

    const [year, month, day] = matches.slice(1).map(Number);

    return month >= 1 && month <= 12 && day >= 1 && day <= monthLength(year, month) ? { year, month, day } : null;
}

export function format({ year, month, day }) {
    const pad = (number) => String(number).padStart(2, '0');

    return `${year}/${pad(month)}/${pad(day)}`;
}
