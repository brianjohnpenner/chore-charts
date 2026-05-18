# Chore Charts

A small Laravel and Livewire app for building printable weekly chore charts.

Users can build a chart anonymously. To save it, they enter an email address and open a signed magic link. The link signs them in, creates the account if needed, and stores the chart against that email address.

## Local Setup

```bash
composer install
npm install
php artisan migrate
npm run dev
php artisan serve
```

Local magic-link emails use Laravel's `log` mailer, so links appear in `storage/logs/laravel.log`.
