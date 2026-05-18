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
                    This policy explains what information is used when you choose to save a chart
                    with an emailed magic link.
                </p>

                <h2>Information We Store</h2>
                <p>
                    If you use the app without signing in, your chart is only held in the current
                    browser session. If you request a magic link, we store your email address and
                    the chore chart data needed to restore your chart.
                </p>

                <h2>Magic Sign-In Links</h2>
                <p>
                    magic sign-in links are sent to the email address you provide. Each link is
                    time-limited and can be used to sign in, create your account if needed, and save
                    the chart attached to that request.
                </p>

                <h2>How We Use Information</h2>
                <p>
                    Your email address is used for sign-in and account lookup. Your chore chart data
                    is used to display, edit, print, and save your chart. The app does not sell this
                    information or use it for advertising.
                </p>

                <h2>Data Retention</h2>
                <p>
                    Saved chart data remains associated with your email address until it is deleted
                    from the application database. Expired or used magic-link records may remain in
                    the database for operational troubleshooting until cleaned up by maintenance.
                </p>

                <h2>Local Development Email</h2>
                <p>
                    In local development, magic-link emails are written to Laravel's application log
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
