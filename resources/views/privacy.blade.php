<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — Chore Charts</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="cc-app">
        <article class="cc-editor-panel" style="max-width: 720px; margin: 2rem auto;">
            <header>
                <h1 class="cc-title">Privacy Policy</h1>
                <p class="cc-subtitle">Last updated: {{ now()->format('F j, Y') }}</p>
            </header>

            <section>
                <h2>What we collect</h2>
                <ul>
                    <li><strong>Email address</strong> — only if you choose to save a chart. We use it to send you a sign-in link.</li>
                    <li><strong>Chart data</strong> — the chore chart you create (children's names, sections, chores). Stored so you can come back to it later.</li>
                    <li><strong>A session cookie</strong> — required to keep an unsaved chart attached to your browser between page loads.</li>
                </ul>
            </section>

            <section>
                <h2>What we don't do</h2>
                <ul>
                    <li>No passwords. Sign-in is by emailed magic link only.</li>
                    <li>No analytics, advertising, or third-party trackers.</li>
                    <li>No selling or sharing your email or chart data with anyone.</li>
                </ul>
            </section>

            <section>
                <h2>How long we keep it</h2>
                <p>Saved charts and the associated email stay until you ask us to delete them. Unsaved charts sit in the database tied to a browser session and can be removed at any time without warning.</p>
            </section>

            <section>
                <h2>Deleting your data</h2>
                <p>Email <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a> from the address you signed in with and we'll delete your account and all of your charts.</p>
            </section>

            <section>
                <h2>Changes to this policy</h2>
                <p>If we change this policy, we'll update the date at the top of this page.</p>
            </section>

            <p><a href="{{ route('home') }}">← Back to the editor</a></p>
        </article>
    </main>
</body>
</html>
