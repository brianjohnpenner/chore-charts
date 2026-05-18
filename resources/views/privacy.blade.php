<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Privacy Policy | Chore Charts</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="p-5">
            <nav class="mx-auto mb-4 max-w-3xl">
                <a class="font-extrabold text-emerald-800 no-underline hover:underline" href="{{ route('home') }}">Back to Chore Charts</a>
            </nav>

            <article class="mx-auto max-w-3xl rounded-lg border border-slate-200 bg-white p-[clamp(1.25rem,4vw,2.5rem)] shadow-sm">
                <h1 class="m-0 mb-1.5 text-[clamp(2rem,5vw,3rem)] font-black leading-none">Privacy Policy</h1>
                <p class="mb-5 text-sm font-bold text-slate-500">Last updated May 17, 2026</p>

                <p class="m-0 leading-relaxed text-slate-700">
                    Chore Charts is a small app for creating printable household chore charts.
                    This policy explains what information is stored when you save a chart.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">Information We Store</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    While you are building a chart, the in-progress draft is held in your
                    browser session. When you choose to save, the chore chart data is written
                    to our database and you are given a private link to come back to it. If
                    you provide an email address, it is stored alongside the chart so you can
                    email yourself the link.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">Shareable Chart Links</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    Each saved chart has a signed URL. Anyone with that link can view and
                    edit the chart, so treat it like a password and only share it with people
                    you trust.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">How We Use Information</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    Your chore chart data is used to display, edit, print, and save your chart.
                    If you provided an email address, it is used only to email you a copy of
                    your chart link. The app does not sell this information or use it for
                    advertising.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">Data Retention</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    Saved chart data remains in the database until it is deleted from the
                    application database.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">Local Development Email</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    In local development, emails are written to Laravel's application log
                    instead of being sent through a real mail provider.
                </p>

                <h2 class="mb-1.5 mt-6 text-base font-black">Contact</h2>
                <p class="m-0 leading-relaxed text-slate-700">
                    For questions about privacy or saved chart data, contact the person or
                    organization operating this Chore Charts installation.
                </p>
            </article>
        </main>
    </body>
</html>
