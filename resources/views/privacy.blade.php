<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Privacy Policy | Chore Charts</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="policy-page">
            <nav class="policy-nav">
                <a href="{{ route('home') }}">Back to Chore Charts</a>
            </nav>

            <article class="policy-document">
                <h1>Privacy Policy</h1>
                <p class="policy-updated">Last updated May 17, 2026</p>

                <p>
                    Chore Charts is a small app for creating printable household chore charts.
                    This policy explains what information is stored when you save a chart.
                </p>

                <h2>Information We Store</h2>
                <p>
                    While you are building a chart, the in-progress draft is held in your
                    browser session. When you choose to save, the chore chart data is written
                    to our database and you are given a private link to come back to it. If
                    you provide an email address, it is stored alongside the chart so you can
                    email yourself the link.
                </p>

                <h2>Shareable Chart Links</h2>
                <p>
                    Each saved chart has a signed URL. Anyone with that link can view and
                    edit the chart, so treat it like a password and only share it with people
                    you trust.
                </p>

                <h2>How We Use Information</h2>
                <p>
                    Your chore chart data is used to display, edit, print, and save your chart.
                    If you provided an email address, it is used only to email you a copy of
                    your chart link. The app does not sell this information or use it for
                    advertising.
                </p>

                <h2>Data Retention</h2>
                <p>
                    Saved chart data remains in the database until it is deleted from the
                    application database.
                </p>

                <h2>Local Development Email</h2>
                <p>
                    In local development, emails are written to Laravel's application log
                    instead of being sent through a real mail provider.
                </p>

                <h2>Contact</h2>
                <p>
                    For questions about privacy or saved chart data, contact the person or
                    organization operating this Chore Charts installation.
                </p>
            </article>
        </main>
    </body>
</html>
