# Hassan Spot Trading Risk Manager

A Laravel 12 + Livewire crypto spot trading risk management dashboard.

## Features

- Authentication (register/login/logout)
- Protected per-user dashboard and trade records
- Risk calculator with position size, TP, expected profit
- Save/edit/delete trades with pagination and coin search
- Statistics engine (win rate, average RR, monthly performance, account growth)
- Risk protection alerts:
  - 3 consecutive losses warning
  - Weekly drawdown >5% alert
- Dark mode fintech-style responsive UI using TailwindCSS

## Stack

- Laravel 12
- Livewire 3
- TailwindCSS
- MySQL

## Database

Run migrations after configuring your `.env`:

```bash
php artisan migrate
```

## Notes

This repository includes source scaffolding and implementation for the requested product structure.
Install dependencies using Composer and NPM in a network-enabled environment before running.
