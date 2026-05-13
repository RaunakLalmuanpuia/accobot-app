# Billing & Subscriptions

Accobot uses **Razorpay Subscriptions** for auto-recurring monthly billing. This document covers the full integration: architecture, setup, payment flows, webhook handling, feature gating, and the test plan.

---

## Table of Contents

1. [Plans & Pricing](#plans--pricing)
2. [Feature Gating](#feature-gating)
3. [Database Schema](#database-schema)
4. [Architecture & Key Files](#architecture--key-files)
5. [Setup Process](#setup-process)
6. [Registration & Payment Flow](#registration--payment-flow)
7. [Middleware](#middleware)
8. [Webhooks](#webhooks)
9. [Payment Records](#payment-records)
10. [Frontend Integration](#frontend-integration)
11. [Mobile API](#mobile-api)
12. [Test Plan](#test-plan)

---

## Plans & Pricing

| Slug | Name | Price (Rs./mo) | Tenant type | Notes |
|---|---|---|---|---|
| `business_ca` | Business – CA Plan | 1,499 | `business` | Only shown when `tally_managed_by_ca = true` |
| `business_solo` | Business – Solo Plan | 2,999 | `business` | Default business plan |
| `personal` | Personal Plan | 999 | `business` | Stripped-down; no Tally, no group chat, no AI |
| `ca_firm` | CA Firm Plan | TBD | `ca_firm` | Price set via `PLAN_PRICE_CA_FIRM` env var |
| `ai_addon` | AI Assistance | 499 | `personal` only | Add-on; included free in all other plans |

Prices are stored in **paise** in the database (e.g. Rs. 2,999 = `299900`). All prices are overridable via env vars without a code deploy — see [Environment Variables](#environment-variables).

### CA Discount Logic

When a business tenant has `tally_managed_by_ca = true`, the plan selector automatically hides `business_solo` and shows only `business_ca` (Rs. 1,499). This is a separate Razorpay plan, not a coupon code. The field is set on the `tenants` table.

### CA Firm Free Trial

CA firms get a **14-day free trial** on registration. No payment details are collected. The trial length is configurable via `CA_TRIAL_DAYS` (default: 14). After the trial expires, the CA hits the payment wall on their next login.

---

## Feature Gating

| Feature slug | `business_ca` | `business_solo` | `personal` | `ca_firm` | `ai_addon` |
|---|---|---|---|---|---|
| `invoicing` | ✓ | ✓ | ✓ | ✓ | — |
| `tally_sync` | ✓ | ✓ | ✗ | ✓ | — |
| `group_chat` | ✓ | ✓ | ✗ | ✓ | — |
| `ai_assistant` | ✓ | ✓ | ✗ (addon) | ✓ | ✓ |
| `ca_clients` | — | — | — | ✓ | — |

The source of truth for plan features is `config/plans.php`. The `PlanSeeder` seeds the `plans` table from this config.

### Enforcement

**Backend (controllers & AI tools)**

```php
// Abort 403 if the tenant's plan doesn't include the feature
app(SubscriptionService::class)->authorize($tenant, 'tally_sync');

// Boolean check
if (app(SubscriptionService::class)->can($tenant, 'group_chat')) { ... }
```

**Backend (route middleware)**

```php
Route::middleware('subscription.feature:tally_sync')->group(function () {
    // all Tally routes
});
```

**Frontend (Vue)**

```js
import { hasFeature } from '@/utils/permissions'

// In <script setup>
const canUseChat = hasFeature('ai_assistant')

// In template
<NavLink v-if="hasFeature('group_chat')" :href="route('chat-rooms.index', tenant)">
    Groups
</NavLink>
```

The `subscription.features` array is passed from `HandleInertiaRequests` via Inertia shared props on every page load.

---

## Database Schema

### `plans`

```
id                 bigint PK
slug               string unique      'business_solo'
name               string             'Business – Solo Plan'
price              integer            In paise (299900 = Rs. 2999)
tenant_type        string             'business' | 'ca_firm' | 'any'
razorpay_plan_id   string nullable    Set after creating plan in Razorpay dashboard
features           json               Array of feature slugs
is_addon           boolean            true for 'ai_addon'
is_active          boolean            false = hidden from plan picker
created_at / updated_at
```

### `subscriptions`

```
id                          bigint PK
tenant_id                   uuid FK → tenants.id (unique per tenant)
plan_id                     bigint FK → plans.id
razorpay_subscription_id    string nullable unique
razorpay_customer_id        string nullable
razorpay_short_url          string nullable    Razorpay hosted management page URL
status                      enum: pending | trialing | active | halted | cancelled | expired
trial_ends_at               timestamp nullable
current_period_start        timestamp nullable
current_period_end          timestamp nullable
cancelled_at                timestamp nullable
created_at / updated_at
```

Status lifecycle:

```
[registration]
  ├─ CA firm  → trialing ──(trial expires)──► [payment wall]
  └─ Business → pending ──(webhook: activated)──► active
                         ──(webhook: charged)───► active (renewed)
                         ──(webhook: halted)────► halted
                         ──(webhook: cancelled)─► cancelled
                         ──(webhook: completed)─► expired
```

### `subscription_addons`

```
id                          bigint PK
subscription_id             bigint FK → subscriptions.id (cascade delete)
plan_id                     bigint FK → plans.id (is_addon = true)
razorpay_subscription_id    string nullable unique
status                      enum: pending | active | halted | cancelled
current_period_end          timestamp nullable
created_at / updated_at
```

### `razorpay_payments`

Immutable payment ledger — one row per payment received.

```
id                          bigint PK
tenant_id                   uuid FK → tenants.id (cascade delete)
subscription_id             bigint FK nullable → subscriptions.id (null on delete)
razorpay_payment_id         string unique      Razorpay's pay_xxx ID
razorpay_subscription_id    string indexed     Razorpay's sub_xxx ID
event_type                  string             'subscription.activated' | 'subscription.charged'
amount                      integer            In paise
currency                    string             'INR'
status                      string             'captured' | 'failed' | etc.
method                      string nullable    'emandate' | 'card' | 'upi' | etc.
email                       string nullable
contact                     string nullable    Phone number
razorpay_created_at         timestamp nullable Razorpay's own timestamp
payload                     json               Full raw webhook payload
created_at / updated_at
```

Rows are upserted by `razorpay_payment_id` so duplicate webhook deliveries are idempotent.

---

## Architecture & Key Files

```
app/
├── Models/
│   ├── Plan.php                          hasFeature(), priceInRupees()
│   ├── Subscription.php                  isPending(), isActive(), onTrial(), isAccessible(), hasFeature()
│   ├── SubscriptionAddon.php
│   ├── RazorpayPayment.php               amountInRupees()
│   └── Tenant.php                        subscription() relationship, hasFeature()
│
├── Services/
│   ├── RazorpayService.php               createSubscription(), cancelSubscription(),
│   │                                     verifyWebhookSignature(), verifyPaymentSignature(),
│   │                                     fetchSubscription()
│   └── SubscriptionService.php           can(), authorize(), features(), summary()
│
├── Http/
│   ├── Controllers/
│   │   ├── BillingController.php         index, selectPlan, subscribe, success, cancel, subscribeAddon
│   │   ├── RazorpayWebhookController.php handle (+ recordPayment)
│   │   ├── AuthController.php            register (CA trial creation)
│   │   └── Api/MobileBillingController.php
│   └── Middleware/
│       ├── EnsureActiveSubscription.php  Payment wall for all tenant routes
│       └── CheckSubscriptionFeature.php  Per-feature gate (subscription.feature:xxx)
│
config/
└── plans.php                             Single source of truth for plan definitions

database/
├── migrations/
│   ├── 2026_05_13_000001_create_plans_table.php
│   ├── 2026_05_13_000002_create_subscriptions_table.php
│   ├── 2026_05_13_000003_create_subscription_addons_table.php
│   ├── 2026_05_13_000004_add_pending_to_subscriptions_status.php
│   └── 2026_05_13_000005_create_razorpay_payments_table.php
└── seeders/
    └── PlanSeeder.php                    Seeds plans table from config/plans.php

resources/js/
├── Pages/Billing/
│   ├── SelectPlan.vue                    Plan picker shown after registration
│   └── Index.vue                         Billing management (current plan, cancel, upgrade)
└── utils/permissions.js                  hasFeature() helper for Vue components
```

---

## Setup Process

### Step 1 — Install the Razorpay PHP SDK

```bash
composer require razorpay/razorpay
```

### Step 2 — Create Plans in the Razorpay Dashboard

Log in to [dashboard.razorpay.com](https://dashboard.razorpay.com) → **Subscriptions → Plans → + Create Plan**.

Create one plan for each slug. Use these exact settings:

| Plan slug | Period | Interval | Amount |
|---|---|---|---|
| `business_ca` | monthly | 1 | 1499.00 |
| `business_solo` | monthly | 1 | 2999.00 |
| `personal` | monthly | 1 | 999.00 |
| `ca_firm` | monthly | 1 | TBD |
| `ai_addon` | monthly | 1 | 499.00 |

Copy each plan's **Plan ID** (format: `plan_xxxxxxxxxxxxx`).

> **Test vs Live:** The dashboard has a Test/Live toggle in the top-left. Create plans in Test mode first. You will need to recreate them in Live mode when going to production — plan IDs differ between modes.

### Step 3 — Configure a Webhook Endpoint

In the Razorpay dashboard → **Settings → Webhooks → + Add New Webhook**:

- **Webhook URL:** `https://your-domain.com/api/webhooks/razorpay`
- **Secret:** Generate a strong random string and copy it
- **Active events:** Select all five:
  - `subscription.activated`
  - `subscription.charged`
  - `subscription.halted`
  - `subscription.cancelled`
  - `subscription.completed`

> For local development, use [ngrok](https://ngrok.com) to expose your local server:
> ```bash
> ngrok http 8000
> # Copy the https:// URL and use it as the webhook URL
> ```

### Step 4 — Set Environment Variables

Copy the plan IDs and credentials into `.env`:

```env
RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxxxxxxxxxx
RAZORPAY_WEBHOOK_SECRET=your-webhook-secret-here

# Plan IDs from the Razorpay dashboard
RAZORPAY_PLAN_ID_BUSINESS_CA=plan_xxxxxxxxxxxxx
RAZORPAY_PLAN_ID_BUSINESS_SOLO=plan_xxxxxxxxxxxxx
RAZORPAY_PLAN_ID_PERSONAL=plan_xxxxxxxxxxxxx
RAZORPAY_PLAN_ID_CA_FIRM=plan_xxxxxxxxxxxxx
RAZORPAY_PLAN_ID_AI_ADDON=plan_xxxxxxxxxxxxx

# Prices in paise — change without a code deploy
PLAN_PRICE_BUSINESS_CA=149900
PLAN_PRICE_BUSINESS_SOLO=299900
PLAN_PRICE_PERSONAL=99900
PLAN_PRICE_CA_FIRM=0          # Set this before the CA trial period ends
PLAN_PRICE_AI_ADDON=49900

# Trial length in days
CA_TRIAL_DAYS=14
```

Also add to `config/services.php` (if not already present):

```php
'razorpay' => [
    'key_id'         => env('RAZORPAY_KEY_ID'),
    'key_secret'     => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
],
```

### Step 5 — Run Migrations

```bash
php artisan migrate
```

This creates: `plans`, `subscriptions`, `subscription_addons`, `razorpay_payments`.

### Step 6 — Seed Plans

```bash
php artisan db:seed --class=PlanSeeder
```

This reads `config/plans.php` and upserts all 5 plan rows into the `plans` table. Re-running it is safe — it uses `updateOrCreate` on the slug.

### Step 7 — Register Middleware

In `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 11+), register:

```php
'subscription'         => \App\Http\Middleware\EnsureActiveSubscription::class,
'subscription.feature' => \App\Http\Middleware\CheckSubscriptionFeature::class,
```

### Step 8 — Verify Setup

```bash
php artisan route:list | grep billing
php artisan route:list | grep webhook
```

You should see:

```
GET|HEAD  /t/{tenant}/billing                billing.index
POST      /t/{tenant}/billing/cancel         billing.cancel
POST      /t/{tenant}/billing/addon          billing.addon
GET|HEAD  /t/{tenant}/billing/select-plan    billing.select-plan
POST      /t/{tenant}/billing/subscribe      billing.subscribe
GET|HEAD  /t/{tenant}/billing/success        billing.success
POST      /api/webhooks/razorpay             webhooks.razorpay
```

---

## Registration & Payment Flow

### Business Tenant

```
POST /register
  └─► Create User + Tenant (type: business)
      └─► Create TallyConnection (auto-provision token)
          └─► Redirect → /t/{tenant}/billing/select-plan

GET /t/{tenant}/billing/select-plan
  └─► Show plan picker
      ├─ tally_managed_by_ca = true  → show only business_ca (Rs. 1,499)
      └─ otherwise                   → show business_solo (Rs. 2,999) + personal (Rs. 999)

POST /t/{tenant}/billing/subscribe  { plan_id: X }
  └─► Validate plan is available for this tenant
  └─► Validate razorpay_plan_id is set (abort 422 if not)
  └─► Call Razorpay API → create subscription
  └─► Save local subscription (status: pending)
  └─► Redirect → Razorpay hosted checkout (short_url)

[User completes mandate on Razorpay]

GET /t/{tenant}/billing/success?razorpay_payment_id=...&razorpay_subscription_id=...&razorpay_signature=...
  └─► Verify Razorpay callback signature
  └─► If valid + subscription is pending → mark active (optimistic)
  └─► Redirect → /t/{tenant}/dashboard

POST /api/webhooks/razorpay  (subscription.activated)
  └─► Verify X-Razorpay-Signature
  └─► Update subscription: status=active, period dates
  └─► Record payment row in razorpay_payments
  └─► Log audit event: subscription.started (source: webhook)
```

> The callback and webhook may race. The callback verifies the Razorpay signature and activates optimistically so the user lands on the dashboard immediately. If the webhook arrives first (less common), the callback is a no-op.

### CA Firm

```
POST /register  (role: ca)
  └─► Create User + Tenant (type: ca_firm)
  └─► Create TallyConnection
  └─► Create Subscription (status: trialing, trial_ends_at: now + 14 days)
  └─► Log audit event: subscription.trial_started
  └─► Redirect → /t/{tenant}/dashboard  (no payment page)

GET /t/{tenant}/dashboard  (after trial expires)
  └─► EnsureActiveSubscription middleware
      └─► status=trialing AND trial_ends_at < now()  →  redirect to billing.select-plan
```

### Plan Cancellation

```
POST /t/{tenant}/billing/cancel
  └─► Validate subscription is active
  └─► Call Razorpay cancel API (cancel_at_cycle_end=1 — stays active until period end)
  └─► Set cancelled_at = now()
  └─► Log audit event: subscription.cancelled (source: user)
  └─► Redirect back with success message
```

Razorpay fires `subscription.cancelled` webhook later, which sets `status = cancelled`.

---

## Middleware

### `EnsureActiveSubscription`

Applied to all routes in the `subscription` middleware group (all `/t/{tenant}/...` routes).

**Passes through if:**
1. User has the `admin` role
2. Route name starts with `billing.` (avoids infinite redirect loop)
3. Route is `onboarding.dismiss` or `logout`
4. Subscription status is `active`
5. Subscription status is `trialing` AND `trial_ends_at` is in the future

**Blocks otherwise:** Redirects to `billing.select-plan`.

### `CheckSubscriptionFeature`

Parameterized middleware applied per route group.

```php
Route::middleware('subscription.feature:tally_sync')->group(...)
```

- **Admins:** Always pass through
- **API / JSON requests:** Returns `403 { message, feature }` if feature not available
- **Web requests:** Redirects to `billing.select-plan` with an error flash if feature not available

---

## Webhooks

**Endpoint:** `POST /api/webhooks/razorpay` (no auth, no CSRF — stateless API route)

Every request is verified via `X-Razorpay-Signature` header using HMAC-SHA256 with `RAZORPAY_WEBHOOK_SECRET`. Invalid signatures return `400`; valid ones return `200` regardless of outcome (to prevent Razorpay retries for expected non-events).

| Event | Action |
|---|---|
| `subscription.activated` | status → `active`; set period dates; record payment; log `subscription.started` |
| `subscription.charged` | status → `active`; extend period dates; record payment; log `subscription.renewed` |
| `subscription.halted` | status → `halted` (payment failed); log `subscription.halted` |
| `subscription.cancelled` | status → `cancelled`; set `cancelled_at`; log `subscription.cancelled` |
| `subscription.completed` | status → `expired`; log `subscription.expired` |

### Addon Subscription Webhooks

The webhook handler first tries to match `razorpay_subscription_id` against the `subscriptions` table. If not found, it tries `subscription_addons`. Addon events map to:

| Event | Addon status |
|---|---|
| `subscription.activated` | `active` |
| `subscription.charged` | `active` |
| `subscription.halted` | `halted` |
| `subscription.cancelled` | `cancelled` |
| `subscription.completed` | `cancelled` |

---

## Payment Records

Every `subscription.activated` and `subscription.charged` webhook writes a row to `razorpay_payments`. The row stores:

- Individual columns for `amount`, `currency`, `status`, `method`, `email`, `contact` — queryable without touching JSON
- `razorpay_created_at` — Razorpay's own timestamp
- `event_type` — `subscription.activated` (first payment) or `subscription.charged` (renewal)
- `payload` — full raw webhook JSON for debugging and reconciliation

Rows are keyed on `razorpay_payment_id` (unique), so duplicate webhook deliveries are idempotent.

### Querying Payments

```php
// All payments for a tenant
RazorpayPayment::where('tenant_id', $tenant->id)->latest()->get();

// Revenue for a month
RazorpayPayment::where('status', 'captured')
    ->whereBetween('razorpay_created_at', [$start, $end])
    ->sum('amount') / 100;  // convert paise to rupees
```

---

## Frontend Integration

### Shared Subscription Props

Every Inertia page receives `subscription` in `usePage().props`:

```js
{
  status: 'active',           // pending | trialing | active | halted | cancelled | expired | null
  plan: 'business_solo',      // plan slug or null
  trial_ends_at: '2026-05-27', // date string or null
  features: ['invoicing', 'tally_sync', 'group_chat', 'ai_assistant']
}
```

### `hasFeature()` Helper

```js
import { hasFeature } from '@/utils/permissions'

// Boolean
hasFeature('group_chat')  // true | false
```

Internally reads `usePage().props.subscription.features`.

### Billing Pages

| Page | Route | Purpose |
|---|---|---|
| `Billing/SelectPlan.vue` | `/t/{tenant}/billing/select-plan` | Plan picker after registration or when subscription expires |
| `Billing/Index.vue` | `/t/{tenant}/billing` | Manage current plan, cancel, add AI addon (Personal only), switch plans |

#### Halted (payment failed) banner

When `subscription.status === 'halted'`, the billing page shows two action buttons:

- **Update Payment Method** — opens `subscription.razorpay_short_url` in a new tab (Razorpay's hosted subscription management page where the user can update their card/UPI). Only shown when `razorpay_short_url` is set.
- **Choose New Plan** — navigates to the plan picker to start a fresh subscription.

The `razorpay_short_url` is stored on the `subscriptions` table when the subscription is first created and is included in the `Billing/Index` Inertia props.

---

## Mobile API

All billing endpoints are under `/api/mobile/tenants/{tenant}/billing/`. See `docs/api-mobile.md` for full request/response shapes.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/billing` | Current subscription status + feature list |
| `GET` | `/billing/plans` | Available plans for this tenant |
| `POST` | `/billing/subscribe` | Create Razorpay subscription → returns `{ short_url }` |
| `POST` | `/billing/cancel` | Cancel at end of cycle |
| `POST` | `/billing/addon` | Subscribe to AI addon (Personal plan only) |

---

## Test Plan

### Environment

Use Razorpay **Test Mode** throughout. Test mode credentials (`rzp_test_...`) are already in `.env`. Test card numbers and UPI IDs are listed in the [Razorpay test documentation](https://razorpay.com/docs/payments/payments/test-card-details/).

### Useful Razorpay test values

| Method | Details |
|---|---|
| Card (success) | 4111 1111 1111 1111 / any future expiry / any CVV |
| Card (failure) | 4000 0000 0000 0002 |
| UPI (success) | success@razorpay |
| UPI (failure) | failure@razorpay |

---

### TC-01 — Business registration redirects to plan selection

1. Register a new account with role `owner`.
2. **Expected:** Redirected to `/t/{tenant}/billing/select-plan`, not the dashboard.
3. **Expected:** Page shows `Business – Solo Plan` (Rs. 2,999) and `Personal Plan` (Rs. 999).
4. **Expected:** `subscriptions` table has no row for this tenant yet.

---

### TC-02 — CA-managed business sees only the CA plan

1. Set `tally_managed_by_ca = true` on an existing business tenant (or register and update the DB).
2. Visit `/t/{tenant}/billing/select-plan`.
3. **Expected:** Only `Business – CA Plan` (Rs. 1,499) is shown.
4. **Expected:** `business_solo` and `personal` plans are not listed.

---

### TC-03 — CA firm registration creates a trial subscription

1. Register a new account with role `ca`.
2. **Expected:** Redirected directly to the dashboard, no payment page shown.
3. **Expected:** `subscriptions` table has a row with `status = trialing`, `trial_ends_at = now + 14 days`.
4. **Expected:** `audit_events` table has a row with `event_type = subscription.trial_started`.

---

### TC-04 — CA firm trial expiry blocks access

1. Use a CA firm account. Manually update `trial_ends_at` to a past date in the DB:
   ```sql
   UPDATE subscriptions SET trial_ends_at = NOW() - INTERVAL '1 hour' WHERE tenant_id = 'your-tenant-id';
   ```
2. Visit any tenant route (e.g. `/t/{tenant}/dashboard`).
3. **Expected:** Redirected to `/t/{tenant}/billing/select-plan`.
4. **Expected:** Plan picker shows only `CA Firm Plan`.

---

### TC-05 — Successful business subscription

1. On the plan selection page, choose `Business – Solo Plan` and click Subscribe.
2. **Expected:** `subscriptions` table row with `status = pending` is created.
3. **Expected:** Redirected to Razorpay hosted checkout.
4. Complete payment with test card `4111 1111 1111 1111`.
5. **Expected:** Redirected to `/t/{tenant}/billing/success?razorpay_payment_id=...&...`.
6. **Expected:** `subscriptions.status` becomes `active` (optimistic callback activation).
7. **Expected:** Redirected to the dashboard with a success flash.
8. **Expected:** Razorpay fires `subscription.activated` webhook shortly after. Re-check DB — `status` remains `active`, `current_period_start` and `current_period_end` are set.
9. **Expected:** `razorpay_payments` has a row with `event_type = subscription.activated`, `status = captured`, correct amount.
10. **Expected:** `audit_events` has `subscription.started` with `source = webhook`.

---

### TC-06 — Failed / abandoned payment

1. Begin a subscription flow, reach Razorpay checkout.
2. Close the tab or use the failure test card.
3. **Expected:** `subscriptions.status` remains `pending`.
4. **Expected:** `EnsureActiveSubscription` blocks access and redirects to `billing.select-plan`.
5. Retry with a valid card.
6. **Expected:** `updateOrCreate` upserts the existing pending row with the new Razorpay subscription ID.

---

### TC-07 — Webhook: subscription renewed (monthly charge)

Simulate via the Razorpay dashboard → Subscriptions → find the test subscription → **Charge Now** (or wait for the test cycle).

1. **Expected:** `subscriptions.status` stays `active`, `current_period_end` extended by one month.
2. **Expected:** New row in `razorpay_payments` with `event_type = subscription.charged`.
3. **Expected:** `audit_events` has `subscription.renewed`.

---

### TC-08 — Webhook: subscription halted

Simulate by triggering a payment failure via the Razorpay dashboard or using a test card that causes failure on renewal.

1. **Expected:** `subscriptions.status` becomes `halted`.
2. **Expected:** Next login attempt to any tenant route redirects to `billing.select-plan`.
3. **Expected:** `audit_events` has `subscription.halted`.

---

### TC-09 — Cancel subscription

1. Log in with an active subscription.
2. Go to `/t/{tenant}/billing` and click **Cancel Subscription**, confirm.
3. **Expected:** `subscriptions.cancelled_at` is set to now.
4. **Expected:** `subscriptions.status` is still `active` (Razorpay allows access until period end).
5. **Expected:** `audit_events` has `subscription.cancelled` with `source = user`.
6. Wait for (or simulate) the `subscription.cancelled` webhook.
7. **Expected:** `subscriptions.status` becomes `cancelled`.

---

### TC-10 — Feature gating: Tally blocked on Personal plan

1. Subscribe with the `personal` plan.
2. Attempt to visit any Tally route (e.g. `/t/{tenant}/tally`).
3. **Expected:** Redirected to `billing.select-plan` with an upgrade message.
4. Via API: `GET /api/mobile/tenants/{tenant}/tally/something`.
5. **Expected:** `403 { "message": "...", "feature": "tally_sync" }`.

---

### TC-11 — Feature gating: AI assistant blocked on Personal plan (no addon)

1. Use a Personal plan tenant without the AI addon.
2. Visit `/t/{tenant}/chat`.
3. **Expected:** Redirected to `billing.select-plan`.
4. **Expected:** `usePage().props.subscription.features` does not include `ai_assistant`.
5. **Expected:** Assistant nav link is hidden in the sidebar.

---

### TC-12 — AI addon subscription (Personal plan)

1. Use an active Personal plan tenant.
2. Go to `/t/{tenant}/billing`, click **Add AI Assistance** (Rs. 499/mo).
3. **Expected:** `subscription_addons` row created with `status = pending`.
4. **Expected:** Redirected to Razorpay checkout for the addon plan.
5. Complete payment.
6. Wait for `subscription.activated` webhook for the addon's Razorpay subscription ID.
7. **Expected:** `subscription_addons.status` becomes `active`.
8. **Expected:** `usePage().props.subscription.features` now includes `ai_assistant`.
9. **Expected:** Assistant nav link appears.

---

### TC-13 — Webhook signature rejection

Send a POST to `/api/webhooks/razorpay` with a tampered body or wrong signature.

```bash
curl -X POST https://your-domain.com/api/webhooks/razorpay \
  -H "Content-Type: application/json" \
  -H "X-Razorpay-Signature: invalidsignature" \
  -d '{"event":"subscription.activated","payload":{}}'
```

**Expected:** `400 Invalid signature`. No DB changes.

---

### TC-14 — Duplicate webhook delivery (idempotency)

Send the same valid `subscription.charged` webhook payload twice.

**Expected:** Only one row in `razorpay_payments` for that `razorpay_payment_id` (upsert). Subscription `current_period_end` is the same after both deliveries.

---

### TC-15 — Plan not configured (missing Razorpay plan ID)

1. Clear `RAZORPAY_PLAN_ID_BUSINESS_SOLO` from `.env` and run `php artisan config:clear`.
2. Submit the subscribe form for Business – Solo Plan.
3. **Expected:** `422 This plan is not yet configured for payment. Please contact support.`
4. **Expected:** No call made to Razorpay API.

---

### TC-16 — Admin bypass

1. Log in as a user with the `admin` role.
2. Visit a tenant route with an expired/missing subscription.
3. **Expected:** Access granted — `EnsureActiveSubscription` and `CheckSubscriptionFeature` both pass through for admins.

---

### TC-17 — Mobile API: subscribe and cancel

```bash
# Get plans
GET /api/mobile/tenants/{tenant}/billing/plans
Authorization: Bearer {token}

# Subscribe
POST /api/mobile/tenants/{tenant}/billing/subscribe
{ "plan_id": 2 }
# Expected: { "short_url": "https://rzp.io/..." }

# Cancel
POST /api/mobile/tenants/{tenant}/billing/cancel
# Expected: { "ok": true, "message": "..." }
```

---

### TC-18 — Subscription summary in Inertia props

1. Log in with an active subscription.
2. Open browser DevTools → Network → any Inertia page response.
3. Check the `props` field.
4. **Expected:**
   ```json
   {
     "subscription": {
       "status": "active",
       "plan": "business_solo",
       "trial_ends_at": null,
       "features": ["invoicing", "tally_sync", "group_chat", "ai_assistant"]
     }
   }
   ```
