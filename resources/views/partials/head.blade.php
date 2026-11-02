<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="OpsFlow AI: operational request workflows with AI-assisted triage, built on Laravel, MySQL and Redis.">
<title>{{ $title ? "{$title} · OpsFlow AI" : 'OpsFlow AI' }}</title>
{{-- Applied before first paint so the theme never flashes. --}}
<script>
    (() => {
        let theme = null;
        try { theme = localStorage.getItem('opsflow.theme'); } catch (e) {}
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
