<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<script>
    (() => {
        const serverAppearance = @js(auth()->user()?->appearance_mode);
        let appearance = serverAppearance;

        if (! appearance) {
            try {
                appearance = window.localStorage.getItem('flux.appearance') || 'system';
            } catch (error) {
                appearance = 'system';
            }
        } else {
            try {
                window.localStorage.setItem('flux.appearance', appearance);
            } catch (error) {
            }
        }

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        document.documentElement.classList.toggle(
            'dark',
            appearance === 'dark' || (appearance === 'system' && prefersDark),
        );
        document.documentElement.dataset.appearance = appearance;
    })();
</script>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
