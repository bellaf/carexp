<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body data-ui-theme="{{ auth()->user()?->ui_theme ?? 'classic' }}" class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                <flux:navbar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                    {{ __('Reports') }}
                </flux:navbar.item>
                @if (auth()->user()?->canManageUsers())
                    <flux:navbar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                        {{ __('Users') }}
                    </flux:navbar.item>
                @endif
                <flux:navbar.item icon="truck" :href="route('cars.index')" :current="request()->routeIs('cars.*')" wire:navigate>
                    {{ __('Cars') }}
                </flux:navbar.item>
                <flux:navbar.item icon="clock" :href="route('history.index')" :current="request()->routeIs('history.*')" wire:navigate>
                    {{ __('History') }}
                </flux:navbar.item>
                <flux:navbar.item icon="banknotes" :href="route('expenses.index')" :current="request()->routeIs('expenses.*')" wire:navigate>
                    {{ __('Expenses') }}
                </flux:navbar.item>
                <flux:navbar.item icon="calendar-days" :href="route('recurring.index')" :current="request()->routeIs('recurring.*')" wire:navigate>
                    {{ __('Recurring') }}
                </flux:navbar.item>
                <flux:navbar.item icon="bolt" :href="route('quick-actions.index')" :current="request()->routeIs('quick-actions.*')" wire:navigate>
                    {{ __('Quick Actions') }}
                </flux:navbar.item>
                <flux:navbar.item icon="map" :href="route('mileage.index')" :current="request()->routeIs('mileage.*')" wire:navigate>
                    {{ __('Mileage') }}
                </flux:navbar.item>
                <flux:navbar.item icon="beaker" :href="route('fuel.index')" :current="request()->routeIs('fuel.*')" wire:navigate>
                    {{ __('Fuel Logs') }}
                </flux:navbar.item>
                <flux:navbar.item icon="wrench-screwdriver" :href="route('maintenance.index')" :current="request()->routeIs('maintenance.*')" wire:navigate>
                    {{ __('Maintenance') }}
                </flux:navbar.item>
                <flux:navbar.item icon="document-text" :href="route('obligations.index')" :current="request()->routeIs('obligations.*')" wire:navigate>
                    {{ __('Obligations') }}
                </flux:navbar.item>
                <flux:navbar.item icon="wallet" :href="route('reimbursements.index')" :current="request()->routeIs('reimbursements.*')" wire:navigate>
                    {{ __('Reimbursements') }}
                </flux:navbar.item>
                <flux:navbar.item icon="folder" :href="route('accounts.index')" :current="request()->routeIs('accounts.*')" wire:navigate>
                    {{ __('Accounts') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group>
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="truck" :href="route('cars.index')" :current="request()->routeIs('cars.*')" wire:navigate>
                        {{ __('Cars') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('history.index')" :current="request()->routeIs('history.*')" wire:navigate>
                        {{ __('History') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('expenses.index')" :current="request()->routeIs('expenses.*')" wire:navigate>
                        {{ __('Expenses') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('recurring.index')" :current="request()->routeIs('recurring.*')" wire:navigate>
                        {{ __('Recurring') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bolt" :href="route('quick-actions.index')" :current="request()->routeIs('quick-actions.*')" wire:navigate>
                        {{ __('Quick Actions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="map" :href="route('mileage.index')" :current="request()->routeIs('mileage.*')" wire:navigate>
                        {{ __('Mileage') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="beaker" :href="route('fuel.index')" :current="request()->routeIs('fuel.*')" wire:navigate>
                        {{ __('Fuel Logs') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="wrench-screwdriver" :href="route('maintenance.index')" :current="request()->routeIs('maintenance.*')" wire:navigate>
                        {{ __('Maintenance') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('obligations.index')" :current="request()->routeIs('obligations.*')" wire:navigate>
                        {{ __('Obligations') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="wallet" :href="route('reimbursements.index')" :current="request()->routeIs('reimbursements.*')" wire:navigate>
                        {{ __('Reimbursements') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="folder" :href="route('accounts.index')" :current="request()->routeIs('accounts.*')" wire:navigate>
                        {{ __('Accounts') }}
                    </flux:sidebar.item>
                    @if (auth()->user()?->canManageUsers())
                        <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
