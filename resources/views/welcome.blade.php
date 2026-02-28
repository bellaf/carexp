<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Car Expense Tracker') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top,#f4efe6,transparent_42%),linear-gradient(180deg,#f7f4ef_0%,#ece7df_100%)] text-zinc-900 antialiased dark:bg-[radial-gradient(circle_at_top,#30251d,transparent_35%),linear-gradient(180deg,#171411_0%,#0f0d0b_100%)] dark:text-zinc-100">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-6 py-12 lg:px-10">
            <div class="grid w-full gap-8 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="space-y-6">
                    <div class="inline-flex items-center gap-3 rounded-full border border-black/10 bg-white/70 px-4 py-2 text-sm shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                        <span class="flex size-9 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900">
                            <x-app-logo-icon class="size-5 fill-current" />
                        </span>
                        <span class="font-medium">{{ __('Car Expense Tracker') }}</span>
                    </div>

                    <div class="max-w-3xl space-y-4">
                        <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                            {{ __('Track the real cost of running your car.') }}
                        </h1>
                        <p class="max-w-2xl text-base leading-7 text-zinc-600 dark:text-zinc-300 sm:text-lg">
                            {{ __('Fuel, maintenance, reimbursements, recurring costs, and reports in one ledger-driven app built for day-to-day use.') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">
                                {{ __('Open Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300">
                                {{ __('Log In') }}
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white/80 px-5 py-3 text-sm font-medium text-zinc-900 transition hover:bg-white dark:border-zinc-700 dark:bg-white/5 dark:text-zinc-100 dark:hover:bg-white/10">
                                    {{ __('Create Account') }}
                                </a>
                            @endif
                        @endauth
                    </div>

                    <div class="grid gap-3 pt-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-black/10 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                            <div class="text-sm font-medium">{{ __('Capture Quickly') }}</div>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Quick actions and mobile-first forms keep frequent entries fast.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-black/10 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                            <div class="text-sm font-medium">{{ __('Ledger Driven') }}</div>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('All financial reporting flows through a single consistent ledger.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-black/10 bg-white/70 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                            <div class="text-sm font-medium">{{ __('Useful Reports') }}</div>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Review summary, category, and fuel reports without extra setup.') }}</p>
                        </div>
                    </div>
                </section>

                <aside class="rounded-[2rem] border border-black/10 bg-white/80 p-6 shadow-xl shadow-black/5 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:shadow-black/30">
                    <div class="space-y-5">
                        <div>
                            <div class="text-sm font-medium uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">{{ __('What it covers') }}</div>
                            <div class="mt-2 text-2xl font-semibold">{{ __('One place for running costs and reimbursements') }}</div>
                        </div>

                        <div class="space-y-3">
                            @foreach ([
                                __('Fuel logs with efficiency tracking'),
                                __('Maintenance history and due reminders'),
                                __('Manual expenses and reimbursements'),
                                __('Recurring schedule forecasting'),
                                __('Summary, category, and fuel reports'),
                            ] as $feature)
                                <div class="flex items-start gap-3 rounded-2xl border border-black/5 bg-black/[0.02] px-4 py-3 dark:border-white/8 dark:bg-white/[0.03]">
                                    <span class="mt-0.5 flex size-5 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">+</span>
                                    <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </body>
</html>
