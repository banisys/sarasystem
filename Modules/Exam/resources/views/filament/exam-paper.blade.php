<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $paper['title'] }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        html { color: #171717; background: white; }
        body { font: 14px/1.8 Tahoma, Arial, sans-serif; margin: 24px; overflow-wrap: anywhere; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        header { border-bottom: 1px solid #555; padding-bottom: 10px; margin-bottom: 16px; break-inside: avoid; }
        .question-list, .options { padding-inline-start: 26px; }
        .question { margin-bottom: 14px; break-inside: avoid; }
        .question-body, .description { white-space: pre-wrap; }
        .written-answer { min-height: 48px; border-bottom: 1px dotted #777; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <header>
        <h1>{{ $paper['title'] }}</h1>
        <div>نام و نام خانوادگی: ............................................</div>
        @if ($paper['duration'] !== null)
            <div>مدت آزمون: {{ $paper['duration'] }} دقیقه</div>
        @endif
        @if ($paper['description'] !== '')
            <p class="description">{{ $paper['description'] }}</p>
        @endif
    </header>

    <ol class="question-list">
        @foreach ($paper['questions'] as $question)
            <li class="question">
                <div class="question-body">{{ $question['body'] }}</div>
                @if ($question['options'] !== [])
                    <ul class="options">
                        @foreach ($question['options'] as $option)
                            <li>{{ $option }}</li>
                        @endforeach
                    </ul>
                @elseif ($question['written'])
                    <div class="written-answer"></div>
                @endif
            </li>
        @endforeach
    </ol>
</body>
</html>
