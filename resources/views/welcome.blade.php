<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Sistema de Empregabilidade SENAC DF') }}</title>
        <script>
            (function () {
                try {
                    var temaSalvo = localStorage.getItem('ses_theme');
                    var tema = temaSalvo === 'light' || temaSalvo === 'dark'
                        ? temaSalvo
                        : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

                    document.documentElement.classList.toggle('dark', tema === 'dark');
                    document.documentElement.setAttribute('data-bs-theme', tema);
                    document.documentElement.style.colorScheme = tema;
                } catch (e) {}
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
