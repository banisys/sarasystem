@php
    $number = fn (int|float $value): string => \Illuminate\Support\Number::format($value, maxPrecision: 2, locale: 'fa');
    $localizeDigits = fn (string $value): string => strtr($value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
    $scores = array_column($paper['questions'], 'score');
    $total = in_array(null, $scores, true) ? '—' : $number(array_sum($scores));
@endphp
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $paper['title'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        html { color: #191919; background: #eaeaea; }
        body { margin: 0; font: 10.5pt/1.7 Tahoma, Arial, sans-serif; overflow-wrap: anywhere; }
        .paper { box-sizing: border-box; max-inline-size: 210mm; min-block-size: 270mm; margin: 0 auto; padding: 10mm; background: #fff; }
        header { margin-block-end: 4mm; break-inside: avoid; }
        table { inline-size: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border: 0.75pt solid #333; padding: 1.3mm 2.1mm; vertical-align: top; }
        th { font-weight: 700; }
        h1 { margin: 0; font-size: 13pt; line-height: 1.6; }
        .identity td { vertical-align: middle; }
        .identity .title-cell { padding-block: 2.4mm; }
        .fill-line { display: inline-block; min-inline-size: 12ch; min-block-size: 1em; border-block-end: 0.75pt dotted #777; vertical-align: baseline; }
        .question-body, .description { white-space: pre-wrap; }
        .description { margin: 3mm 0 0; padding-inline: 2mm; }
        .number-col { inline-size: 13mm; }
        .score-col { inline-size: 16mm; }
        .questions thead th, .number, .score { text-align: center; }
        .questions thead th:first-child { padding-inline: 1mm; white-space: nowrap; }
        .questions tbody tr { break-inside: avoid; }
        .questions tfoot { display: table-row-group; }
        .questions tfoot tr { break-inside: avoid; }
        .options { margin-block: 1mm 0; padding-inline-start: 6mm; list-style-type: persian; }
        .answer-lines { min-block-size: 21mm; margin-block-start: 2mm; }
        .answer-lines div { block-size: 7mm; border-block-end: 0.65pt dotted #777; }
        @media (max-width: 650px) { .paper { padding: 4mm; } }
        @media print {
            html { background: #fff; }
            .paper { max-inline-size: none; min-block-size: 0; padding: 0; }
            .questions thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <main class="paper">
        <header>
            <table class="identity" aria-label="مشخصات آزمون">
                <colgroup><col style="width: 40%"><col style="width: 32%"><col style="width: 28%"></colgroup>
                <tbody>
                    <tr>
                        <td class="title-cell" colspan="2"><h1>{{ $paper['title'] }}</h1></td>
                        <td><strong>تاریخ:</strong> {{ $localizeDigits($paper['date'] ?? '—') }}</td>
                    </tr>
                    <tr>
                        <td><strong>نام و نام خانوادگی:</strong> <span class="fill-line" aria-hidden="true"></span></td>
                        <td><strong>{{ config('sarasystem.tenant_label') }}:</strong> {{ $paper['branch'] ?? '—' }}</td>
                        <td><strong>شروع:</strong> <bdi dir="ltr">{{ $localizeDigits($paper['time'] ?? '—') }}</bdi></td>
                    </tr>
                    <tr>
                        <td><strong>کلاس:</strong> {{ $paper['class'] ?? '—' }}</td>
                        <td><strong>شماره داوطلب:</strong> <span class="fill-line" aria-hidden="true"></span></td>
                        <td><strong>مدت:</strong> {{ $paper['duration'] === null ? '—' : $number($paper['duration']).' دقیقه' }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($paper['description'] !== '')
                <p class="description">{{ $paper['description'] }}</p>
            @endif
        </header>

        <table class="questions" aria-label="سؤال‌های آزمون">
            <colgroup><col class="number-col"><col><col class="score-col"></colgroup>
            <thead>
                <tr><th scope="col">ردیف</th><th scope="col">شرح سؤال</th><th scope="col">نمره</th></tr>
            </thead>
            <tbody>
                @foreach ($paper['questions'] as $question)
                    <tr>
                        <th scope="row" class="number">{{ $number($loop->iteration) }}</th>
                        <td>
                            <div class="question-body">{{ $question['body'] }}</div>
                            @if ($question['options'] !== [])
                                <ol class="options">
                                    @foreach ($question['options'] as $option)
                                        <li>{{ $option }}</li>
                                    @endforeach
                                </ol>
                            @elseif ($question['written'])
                                <div class="answer-lines" aria-hidden="true"><div></div><div></div><div></div></div>
                            @endif
                        </td>
                        <td class="score">{{ $question['score'] === null ? '—' : $number($question['score']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th scope="row" colspan="2">جمع نمرات برگه</th><td class="score"><strong>{{ $total }}</strong></td></tr>
            </tfoot>
        </table>
    </main>
</body>
</html>
