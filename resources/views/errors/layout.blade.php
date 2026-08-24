@php
    $isPosAbort = isset($exception) && $exception instanceof \App\Exceptions\PosAbortException;
    $customTitle = $isPosAbort ? $exception->title : null;
    $customMessage = $isPosAbort ? trim($exception->getMessage()) : '';
    $statusCode = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : null;
    $safeToShow = $customMessage !== '' && ($statusCode === null || $statusCode < 500 || config('app.debug'));
    $headline = $customTitle ?? trim($__env->yieldContent('message'));
    $showCta = $isPosAbort ? $exception->showCta : false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $customTitle ?? trim($__env->yieldContent('title')) }} &mdash; POS System Kasir</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #ffffff;
            --color-bg-secondary: #f6f6f7;
            --color-ink: #111111;
            --color-ink-hover: #2b2b2b;
            --color-text-main: #17181c;
            --color-text-muted: #74777d;
            --color-border: #ececec;
            --color-accent: #9a5b2e;
            --color-accent-soft: #f5ede4;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100vh;
        }

        body {
            padding: 48px 20px;
            background-color: var(--color-bg-secondary);
            background-image: radial-gradient(var(--color-border) 1px, transparent 1px);
            background-size: 20px 20px;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: var(--color-text-main);
        }

        .receipt {
            position: relative;
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
            background: var(--color-bg);
            padding: 36px 32px 28px;
            border-radius: 2px;
            box-shadow: 0 12px 24px rgba(17, 17, 17, 0.09), 0 2px 6px rgba(17, 17, 17, 0.05);
            animation: rise 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
        }

        .receipt::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -13px;
            height: 14px;
            background:
                linear-gradient(135deg, var(--color-bg-secondary) 50%, transparent 50%) 0 0 / 16px 14px,
                linear-gradient(-135deg, var(--color-bg-secondary) 50%, transparent 50%) 0 0 / 16px 14px;
            background-color: var(--color-bg);
        }

        .receipt__masthead {
            text-align: center;
            font-family: 'Inter', monospace;
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }

        .receipt__perforation {
            border: none;
            border-top: 2px dashed var(--color-border);
            margin: 22px 0;
        }

        .receipt__status {
            text-align: center;
            font-family: 'Inter', monospace;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--color-accent);
            background: var(--color-accent-soft);
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .receipt__status-row {
            text-align: center;
            margin-bottom: 18px;
        }

        .receipt__code {
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 64px;
            line-height: 1;
            color: var(--color-ink);
            margin: 0 0 6px;
        }

        .receipt__title {
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 18px;
            margin: 0 0 10px;
            color: var(--color-text-main);
        }

        .receipt__message {
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
            color: var(--color-text-muted);
            margin: 0 0 26px;
        }

        .receipt__cta {
            display: block;
            width: 100%;
            text-align: center;
            padding: 12px 20px;
            background: var(--color-ink);
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .receipt__cta:hover { background: var(--color-ink-hover); }
        .receipt__cta:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

        .receipt__ref {
            margin-top: 18px;
            text-align: center;
            font-family: 'Inter', monospace;
            font-size: 10px;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .receipt { animation: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt__masthead">POS System Kasir</div>

        <div class="receipt__status-row">
            <span class="receipt__status">Status @yield('code')</span>
        </div>

        <p class="receipt__code">@yield('code')</p>
        <h1 class="receipt__title">{{ $headline }}</h1>
        <p class="receipt__message">{{ $safeToShow ? $customMessage : trim($__env->yieldContent('description')) }}</p>

        @if ($showCta)
            <a href="{{ url('/') }}" class="receipt__cta">@yield('cta', 'Kembali ke Beranda')</a>
        @endif

        <hr class="receipt__perforation">
        <p class="receipt__ref">NO. REF: {{ now()->format('dmY-His') }}</p>
    </div>
</body>
</html>
