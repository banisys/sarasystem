// Jalali date and time in Tehran time, by the browser's Intl (no date library yet).
const dateTimeFormat = new Intl.DateTimeFormat('fa-IR', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Tehran',
});

export function formatDateTime(iso) {
    return iso ? dateTimeFormat.format(new Date(iso)) : '';
}

export function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const pad = (value) => String(value).padStart(2, '0');

    return `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`.replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
}
