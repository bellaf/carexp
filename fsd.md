# Functional Specification Document (FSD)

## Project
CarExp - Personal Car Expense + Reimbursement Ledger

## Document Status
- Version: Current implementation baseline
- Date: 01-03-2026
- Purpose: Describe what the app does now, based on the shipped codebase.

## Product Summary
CarExp tracks vehicle operating activity (fuel, maintenance, manual expenses, reimbursements, obligations) and records all money movement in a unified `ledger_entries` table.

Primary outcomes:
- Understand total spend vs reimbursements
- Track net car cost over time
- Forecast year-end impact from recurring schedules
- Surface upcoming service and recurring due items
- Review summary, category, and fuel reports from logged data
- Keep supporting documents attached to key records
- Review a per-car unified service and ownership history

## Core Principles
1. Ledger-centric finance model
- Financial reporting is based on `ledger_entries`.
- Source modules (fuel, maintenance, expenses, obligations on completion, recurring schedules, and manual reimbursements) write financial activity into `ledger_entries`.

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
  - `ui_theme` (`classic`, `warm-paper`, `soft-automotive`, `editorial-neutral`)
  - appearance mode remains Light / Dark / System

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
- Attachments:
  - JPG, PNG, PDF documents can be uploaded to maintenance records
  - Attachments are shown in the record modal and flagged in list/history views

### 5. Expenses (manual)
- Records: category, amount, date, odometer, vendor, tags, notes
- Ledger sync:
  - Creates/updates linked expense ledger entry (`source_type=expense`)
  - Account selected by category->account mapping
- Categories are managed in-app (create/edit category names)
- Attachments:
  - JPG, PNG, PDF documents can be uploaded to expense records
  - Attachments are shown in the record modal and flagged in list/history views

### 6. Reimbursements
- Records: reimbursed date, amount, account/type, notes
- Storage model:
  - Reimbursements are ledger-native income entries, not a separate source table
  - Manual reimbursement entry creates/updates a `ledger_entries` income row (`source_type=reimbursement`)
  - Recurring income entries also appear in the Reimbursements view because that page is now ledger-backed
- Delete behavior:
  - Deleting a reimbursement removes the ledger entry
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
- Management controls:
  - `Skip Next Occurrence` advances the schedule by one cadence interval without posting
  - `Upcoming Preview` shows the next few projected dates based on cadence and optional end date

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
- Mobile behavior:
  - Quick actions are rendered as larger touch-friendly buttons
  - Financial summary, due notices, and ledger views have mobile card layouts in addition to desktop tables

### 9. Quick actions
- Purpose:
  - User-defined one-click templates for frequent transactions (for example tolls, parking, fuel)
- Definition fields:
  - name, target (`expense` or `fuel_log`), optional car, optional amount, optional fuel volume, default full tank flag, vendor, notes, tags, active flag, sort order
- Dashboard behavior:
  - Up to 4 active quick actions are shown
  - If no quick actions are defined, quick action buttons are hidden
  - Clicking a quick action opens a confirmation modal summary before posting
  - If definition amount is `0` or empty, modal requires entry of amount before confirm/post
  - If quick action target is fuel, modal can collect odometer, fuel volume, and full tank status
- Posting behavior:
  - Expense target: creates an `expenses` row and matching `ledger_entries` expense row
  - Fuel target: creates a `fuel_logs` row and matching `ledger_entries` expense row

### 10. Reports
- Dedicated reports page at `/reports`
- Current report modes:
  - Summary
  - Category Breakdown
  - Fuel Analysis
- Filters:
  - report type
  - period
  - selected year when `Full Year` is chosen
  - optional car filter
- Data sources:
  - Summary and Category reports read from `ledger_entries`
  - Fuel report reads from `fuel_logs` plus linked ledger spend
- Current outputs:
  - Summary cards
  - monthly trend tables/cards
  - category totals
  - fuel spend, volume, average price, and average efficiency

### 11. Obligations
- Dedicated obligations page for insurance, tax/registration, and MOT/inspection
- Records: car, type, provider, reference, start date, due date, renewal cost, notes, active flag
- Date semantics:
  - `Start Date` = when the covered/valid period begins
  - `Due Date` = when renewal, expiry, or completion comes around
- Ledger behavior:
  - Saving an active obligation stores the reminder record only
  - No ledger expense is created when the obligation is first added
  - Renewing/completing an obligation creates the linked expense ledger entry (`source_type=vehicle_obligation`) dated to the obligation due date
  - If an active obligation has a legacy linked ledger row, saving it clears that row
- Renewal workflow:
  - `Renew for Next Year` marks the current obligation complete and creates the next annual record
- Attachments:
  - JPG, PNG, PDF documents can be uploaded to obligation records
  - Attachments are shown in the record modal and flagged in list/history views

### 12. History
- Dedicated history page at `/history`
- Purpose:
  - show a unified per-car timeline across operational and financial events
- Current event sources:
  - fuel logs
  - manual expenses
  - maintenance records
  - obligations
  - reimbursement/income ledger entries
- Filters:
  - car
  - event type
- Interaction:
  - row/card click opens summary modal
  - modal shows event details, attachment links where applicable, and link out to the source page for editing

## UI/UX Conventions (Current)
- Date display standard in app tables/views: `dd-mm-yyyy`
- List views use simplified sheet-style tables
- Light theme variants are available in Settings > Appearance:
  - Classic
  - Warm Paper
  - Soft Automotive
  - Editorial Neutral
- Filter behavior:
  - Auto-apply via Livewire/model change
  - Constrained select widths (not full-card stretch on desktop)
- Row click behavior:
  - Expenses, Fuel, Reimbursements, Recurring: click row to open modal edit
  - Quick Actions list: click row to open modal edit
  - Dashboard service/recurring due rows: click row to open modal edit/delete
  - History rows/cards: click row to open summary modal
- Attachment indicators:
  - Expenses, Maintenance, Obligations, and History display `Docs attached` when records have stored documents
- Modal conventions:
  - Close control in top-right
  - Save/edit actions left-aligned in footer area
  - Delete action separated on right with confirmation where destructive
- Mobile conventions:
  - Frequently used entry screens prioritize the primary add action at top
  - Dense tables fall back to touch-friendly card layouts on small screens
  - Secondary filters are reduced or tucked behind simple disclosure sections on mobile where appropriate

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
6. Mobile-first presentation is required:
- primary actions remain immediately visible
- secondary filters should not dominate the first screen
- narrow screens should prefer stacked cards over wide horizontal tables when practical

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
- `vehicle_obligations` (linked by unique nullable `ledger_entry_id`)
- `recurring_transactions`
- `attachments` (polymorphic, user-owned, private file serving)
- `cars` (includes `current_odometer`, `is_default`, `is_archived`)
- `accounts`
- `expense_categories`
- `quick_actions`
- `reimbursement_allocations` (schema exists for allocation workflows)
- `users.ui_theme` stores selected light-theme palette preference

## Key Business Rules
1. Ledger is the financial source of truth.
2. Source records keep a 1:1 link to their ledger entry when monetized.
3. Deleting a monetized source record removes its linked ledger entry.
4. Deleting an attachment removes both the DB record and the stored file.
5. Fuel logs always require a cost amount and post expense ledger rows.
6. Maintenance only posts ledger when cost > 0.
7. Maintenance due checks support either condition:
- Due date within 14-day window
- Odometer within 500 units of next due odometer (or past it)
8. Recurring skip control advances `next_entry_date` by cadence without generating a ledger row.
9. Obligations are reminder records first; they only become ledger expenses when renewed/completed.
10. Dashboard net cost semantics:
- Net cost = expenses - reimbursements
- Negative net implies surplus/reimbursement exceeds spend

## Accounts and Categories
- Accounts are used for ledger classification (expense/income groups).
- Expense categories remain for manual expense UX and map into ledger accounts.
- Fuel and maintenance are effectively categorized by `source_type` and account key in ledger reporting.
- Reports use ledger account grouping for cross-module category totals.

## Routing Map (Authenticated)
- `/dashboard`
- `/reports`
- `/history`
- `/cars`
- `/obligations`
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
  - Reimbursements ledger-backed CRUD and recurring-income visibility
  - Obligations CRUD and renewal workflow
  - Attachment access and upload flows
  - History page filtering and merged event output
  - Recurring skip and preview controls
  - Recurring CRUD and generation command
  - Reports page summary/category/fuel outputs
  - Appearance/theme preference updates
- PHPUnit test environment now uses SQLite:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=database/testing.sqlite`
- Legacy `carexp_test` MySQL test DB is no longer part of the project setup.

## Known Boundaries / Next Candidate Enhancements
1. Reimbursement allocation workflow exists at schema level but is not yet exposed as a full UI flow.
2. Account management UI is minimal; most defaults come from seeders/auto-ensure logic.
3. Dashboard forecast currently projects from recurring schedules only; no scenario modeling beyond that.
4. Reports are currently on-screen only; export/print/PDF is not yet implemented.
5. Reporting remains intentionally simple; no charting or advanced analytics yet.

## Schema Notes
- The legacy `reimbursements` table has been removed.
- Reimbursement financial activity is now represented directly in `ledger_entries`.

## Operational Notes
- Recurring generation in production should be executed via scheduler/cron (`app:generate-recurring-transactions`).
- During development, the recurring page includes a manual trigger button for due entry generation.
- A simple repo-level deploy script exists: `./deploy.sh`
- Current deploy script flow:
  - `git pull --ff-only`
  - `composer install --no-dev --optimize-autoloader`
  - `npm ci && npm run build` when npm is available
  - `php artisan migrate --force`
  - cache clear + production cache rebuild
