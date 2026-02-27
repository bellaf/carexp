# Functional Specification Document (FSD)

## Project
CarExp - Personal Car Expense + Reimbursement Ledger

## Document Status
- Version: Current implementation baseline
- Date: 26-02-2026
- Purpose: Describe what the app does now, based on the shipped codebase.

## Product Summary
CarExp tracks vehicle operating activity (fuel, maintenance, manual expenses, reimbursements) and records all money movement in a unified `ledger_entries` table.

Primary outcomes:
- Understand total spend vs reimbursements
- Track net car cost over time
- Forecast year-end impact from recurring schedules
- Surface upcoming service and recurring due items

## Core Principles
1. Ledger-centric finance model
- Financial reporting is based on `ledger_entries`.
- Source modules (fuel, maintenance, expenses, reimbursements, recurring schedules) create/update linked ledger rows.

2. Operational source records retained
- Source tables still store operational context (odometer, service type, tags, etc.).
- Money columns were removed from fuel and maintenance source tables and are now ledger-driven.

3. User ownership and isolation
- All business records are user-scoped.
- UI routes are protected by `auth` + `verified` middleware.

## Technology Stack
- Laravel 12, PHP 8.5
- Livewire 4 (Volt single-file components)
- Flux UI components
- Alpine.js for dashboard modal interactions
- Primary DB (dev): MySQL (`carexp`)
- Test DB: SQLite file (`database/testing.sqlite`)
- Testing: Pest feature tests

## Implemented Modules

### 1. Authentication and settings
- Fortify/session auth flows are enabled.
- User preferences supported:
  - `preferred_currency`
  - `measurement_system` (imperial/metric)
  - `volume_unit` (gallons/liters)

### 2. Cars
- Add/edit/archive/restore cars
- Set default car (`is_default`)
- Dashboard shows current car in top section
- Fuel type labels use: Petrol, Diesel, Hybrid, Electric

### 3. Fuel log
- Records: date, odometer, volume, unit, full tank, price/unit, total cost
- Auto calculations:
  - `price_per_unit` if omitted
  - efficiency for full-tank sequences
- Ledger sync:
  - Creates/updates linked expense ledger entry (`source_type=fuel_log`)
  - Deletes linked ledger entry when fuel row is deleted
- Car odometer sync:
  - Car `current_odometer` is recalculated from latest fuel log per car

### 4. Maintenance
- Records: service type/provider/date, optional odometer, notes, next due date/odometer
- Ledger sync behavior:
  - If cost > 0, linked expense ledger entry is created/updated (`source_type=maintenance_record`)
  - If cost is empty/0, linked ledger entry is removed
- Reminder logic in module:
  - Overdue or due soon by date and/or odometer thresholds

### 5. Expenses (manual)
- Records: category, amount, date, odometer, vendor, tags, notes
- Ledger sync:
  - Creates/updates linked expense ledger entry (`source_type=expense`)
  - Account selected by category->account mapping
- Categories are managed in-app (create/edit category names)

### 6. Reimbursements
- Records: reimbursed date, amount, account/type, notes
- Ledger sync:
  - Creates/updates linked income ledger entry (`source_type=reimbursement`)
  - Deletes linked ledger entry when reimbursement is deleted
- Default income accounts are auto-ensured

### 7. Recurring schedules (dedicated page)
- Schedule fields: type (expense/income), account, amount, cadence, next date, optional end date, notes/reference, active flag
- Command-based generation:
  - `app:generate-recurring-transactions`
  - Creates ledger entries (`source_type=recurring`) for due occurrences
  - Advances `next_entry_date`
- Automation:
  - Scheduled daily in `routes/console.php`
- Dev convenience:
  - Recurring page includes a temporary button to run due generation immediately

### 8. Dashboard
- Running headline totals:
  - Net cost all-time
  - Net cost this month
  - Projected year-end net cost
- Financial summary table:
  - Expenses, reimbursements, net across all-time, actual YTD, projected remaining, projected year-end
- Forecast model:
  - Combines actual YTD ledger totals with future recurring schedules to year end
- Upcoming indicators (next 14 days):
  - Service Due: based on maintenance `next_due_date` and/or odometer proximity
  - Recurring Due: active schedules with `next_entry_date` in window
- Transaction list:
  - Unified ledger-backed table
  - Filters: transaction type + period
  - Auto-apply on change (no apply/clear buttons)

### 9. Quick actions
- Purpose:
  - User-defined one-click expense templates for frequent transactions (for example tolls, parking)
- Definition fields:
  - name, category, optional car, optional amount, vendor, notes, tags, active flag, sort order
- Dashboard behavior:
  - Up to 4 active quick actions are shown
  - If no quick actions are defined, quick action buttons are hidden
  - Clicking a quick action opens a confirmation modal summary before posting
  - If definition amount is `0` or empty, modal requires entry of amount before confirm/post
- Posting behavior:
  - Creates an `expenses` row and matching `ledger_entries` expense row

## UI/UX Conventions (Current)
- Date display standard in app tables/views: `dd-mm-yyyy`
- List views use simplified sheet-style tables
- Filter behavior:
  - Auto-apply via Livewire/model change
  - Constrained select widths (not full-card stretch on desktop)
- Row click behavior:
  - Expenses, Fuel, Reimbursements, Recurring: click row to open modal edit
  - Quick Actions list: click row to open modal edit
  - Dashboard service/recurring due rows: click row to open modal edit/delete
- Modal conventions:
  - Close control in top-right
  - Save/edit actions left-aligned in footer area
  - Delete action separated on right with confirmation where destructive

## Default UI Standard (Future Features)
This standard is now the default expectation for all new list-style features unless explicitly overridden:
1. Table/list rows are click-selectable to open modal detail/edit.
2. No per-row action button column (`Edit`, `Delete`) in list tables.
3. Filters auto-apply on change (no apply/clear buttons).
4. Modals use consistent structure:
- close action in top-right
- primary save action bottom-left
- destructive delete action bottom-right with confirmation
5. Table styling matches existing sheet-style tables:
- subtle border
- hover state
- compact row spacing
- consistent date/currency formatting

## Data Model (Current Functional)

### Primary finance model
- `ledger_entries`
  - user_id, car_id (nullable), account_id
  - recurring_transaction_id (nullable)
  - entry_date, entry_type (`expense`|`income`), amount
  - source_type (`fuel_log`, `maintenance_record`, `expense`, `reimbursement`, `recurring`)
  - source_id, reference, notes

### Operational/source models
- `fuel_logs` (linked by unique nullable `ledger_entry_id`)
- `maintenance_records` (linked by unique nullable `ledger_entry_id`)
- `expenses` (linked by unique nullable `ledger_entry_id`)
- `reimbursements` (linked by unique nullable `ledger_entry_id`)
- `recurring_transactions`
- `cars` (includes `current_odometer`, `is_default`, `is_archived`)
- `accounts`
- `expense_categories`
- `quick_actions`
- `reimbursement_allocations` (schema exists for allocation workflows)

## Key Business Rules
1. Ledger is the financial source of truth.
2. Source records keep a 1:1 link to their ledger entry when monetized.
3. Deleting a monetized source record removes its linked ledger entry.
4. Fuel logs always require a cost amount and post expense ledger rows.
5. Maintenance only posts ledger when cost > 0.
6. Maintenance due checks support either condition:
- Due date within 14-day window
- Odometer within 500 units of next due odometer (or past it)
7. Dashboard net cost semantics:
- Net cost = expenses - reimbursements
- Negative net implies surplus/reimbursement exceeds spend

## Accounts and Categories
- Accounts are used for ledger classification (expense/income groups).
- Expense categories remain for manual expense UX and map into ledger accounts.
- Fuel and maintenance are effectively categorized by `source_type` and account key in ledger reporting.

## Routing Map (Authenticated)
- `/dashboard`
- `/cars`
- `/fuel`
- `/maintenance`
- `/expenses`
- `/reimbursements`
- `/recurring`
- `/quick-actions`

## Testing and Environment
- Feature coverage exists for:
  - Dashboard behavior and reminders
  - Fuel CRUD and odometer sync
  - Expenses CRUD and ledger sync
  - Reimbursements CRUD and ledger sync
  - Recurring CRUD and generation command
- PHPUnit test environment now uses SQLite:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=database/testing.sqlite`
- Legacy `carexp_test` MySQL test DB is no longer part of the project setup.

## Known Boundaries / Next Candidate Enhancements
1. Reimbursement allocation workflow exists at schema level but is not yet exposed as a full UI flow.
2. Account management UI is minimal; most defaults come from seeders/auto-ensure logic.
3. Dashboard forecast currently projects from recurring schedules only; no scenario modeling beyond that.
4. CSV/export/reporting endpoints are not yet implemented as a dedicated module.

## Operational Notes
- Recurring generation in production should be executed via scheduler/cron (`app:generate-recurring-transactions`).
- During development, the recurring page includes a manual trigger button for due entry generation.
