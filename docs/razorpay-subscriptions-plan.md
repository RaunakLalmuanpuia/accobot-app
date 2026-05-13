# Razorpay Subscriptions — Implementation Plan

This document is the authoritative reference for the Razorpay billing integration. Use it to resume work at any point.

---

## Overview

Accobot will gate features behind paid subscriptions managed via **Razorpay Subscriptions** (auto-recurring monthly billing). A payment selection screen is shown immediately after registration, before the user lands on the dashboard. A `CheckSubscription` middleware enforces active billing on all tenant routes.

---

## Plans & Pricing

| Slug | Name | Price (Rs./mo) | Tenant type | Notes |
|---|---|---|---|---|
| `business_ca` | Business – CA Plan | 1,499 | `business` | Only shown when a CA is already linked |
| `business_solo` | Business – Solo Plan | 2,999 | `business` | Shown when no CA is linked |
| `personal` | Personal Plan | 999 | `business` | Stripped-down; no group chat, no Tally |
| `ca_firm` | CA Firm Plan | **TBD — configurable** | `ca_firm` | Price set via `PLAN_PRICE_CA_FIRM` env var |
| `ai_addon` | AI Assistance | 499 | `personal` only | Add-on for Personal plan; included by default in all Business and CA Firm plans |

### CA Firm Trial
- CA firms get a **14-day free trial** on registration.
- Trial is tracked via `subscriptions.trial_ends_at`.
- No payment details collected at registration — trial is unconditional.
- At trial end the CA hits the payment wall on next login.

### CA Discount Auto-Detection
- When a business tenant has `tally_managed_by_ca = true`, the plan selector automatically hides `business_solo` and shows only `business_ca` (Rs. 1,499).
- The discount is structural (separate Razorpay plan ID), not a coupon code.

---

## Feature Gating

| Feature | business_ca | business_solo | personal | ca_firm |
|---|---|---|---|---|
| Invoicing / accounting | ✓ | ✓ | ✓ | ✓ |
| Tally sync | ✓ | ✓ | ✗ | ✓ |
| Live Group Chat | ✓ | ✓ | ✗ | ✓ |
| AI Chat assistant | ✓ | ✓ | addon | ✓ |
| CA client management | — | — | — | ✓ |

### Enforcement
- **Backend:** A `SubscriptionService::can(Tenant, string $feature)` helper checks the active plan's feature set. Controllers and AI tools call this before executing gated actions.
- **Frontend:** `usePage().props.subscription.features` array passed from `HandleInertiaRequests`. Vue components use a `v-if="features.includes('group_chat')"` pattern.

---

## Database Schema

### `plans` table

```
id            bigint PK
slug          string unique          e.g. 'business_solo'
name          string                 Display name
price         integer                In paise (e.g. 299900 = Rs. 2999)
tenant_type   string                 'business' | 'ca_firm' | 'any'
razorpay_plan_id  string nullable    Set after plan is created in Razorpay dashboard
features      json                   Array of feature slugs this plan includes
is_addon      boolean default false  True for 'ai_addon'
is_active     boolean default true   Can disable without deleting
created_at / updated_at
```

### `subscriptions` table

```
id                          bigint PK
tenant_id                   uuid FK → tenants.id (unique)
plan_id                     bigint FK → plans.id
razorpay_subscription_id    string nullable unique
razorpay_customer_id        string nullable
status                      enum: trialing | active | halted | cancelled | expired
trial_ends_at               timestamp nullable
current_period_start        timestamp nullable
current_period_end          timestamp nullable
cancelled_at                timestamp nullable
created_at / updated_at
```

### `subscription_addons` table

```
id                          bigint PK
subscription_id             bigint FK → subscriptions.id
plan_id                     bigint FK → plans.id (is_addon = true)
razorpay_subscription_id    string nullable unique
status                      enum: active | halted | cancelled
current_period_end          timestamp nullable
created_at / updated_at
```

---

## Registration & Payment Flow

```
POST /register
  └─► Create User + Tenant + TallyConnection
      ├─ CA firm  → Create subscription (status: trialing, trial_ends_at: +14 days)
      │            → Redirect to /t/{tenant}/dashboard  (no payment page)
      └─ Business → Redirect to /t/{tenant}/billing/select-plan
                     User picks plan
                     → POST /t/{tenant}/billing/subscribe {plan_id}
                       → Create Razorpay Subscription via API
                       → Redirect to Razorpay hosted checkout URL
                         ├─ On success → Razorpay webhook activates subscription
                         │              → Redirect to /t/{tenant}/dashboard
                         └─ On failure → Back to /t/{tenant}/billing/select-plan with error
```

**CA trial expiry path:**
```
GET /t/{tenant}/dashboard  (after trial ends)
  └─ EnsureActiveSubscription middleware
       → subscription.status = 'trialing' AND trial_ends_at < now()
       → Redirect to /t/{tenant}/billing/select-plan
```

---

## Middleware: `EnsureActiveSubscription`

Applied to all routes inside the `tenant.auth` group (all `/t/{tenant}/...` routes).

**Passes if any of these are true:**
1. `subscription.status = 'active'`
2. `subscription.status = 'trialing'` AND `trial_ends_at > now()`
3. Route is billing-related (`billing.*` named routes) — bypass to avoid infinite redirect loop
4. Route is `logout` or `onboarding.dismiss`

**Blocks otherwise:** Redirects to `route('billing.select-plan', $tenant)`.

---

## Razorpay Webhooks

Endpoint: `POST /webhooks/razorpay` (no tenant scope — Razorpay POSTs here globally)

| Event | Action |
|---|---|
| `subscription.activated` | Set status → `active`, set `current_period_start/end` |
| `subscription.charged` | Extend `current_period_end` by one month |
| `subscription.halted` | Set status → `halted` (payment failed; next login redirects to billing) |
| `subscription.cancelled` | Set status → `cancelled` |
| `subscription.completed` | Set status → `expired` |

Webhook signature verified via `Razorpay-Signature` header using `RAZORPAY_WEBHOOK_SECRET`.

---

## Environment Variables

```env
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

# Plan IDs — created in Razorpay dashboard, pasted here
RAZORPAY_PLAN_ID_BUSINESS_CA=
RAZORPAY_PLAN_ID_BUSINESS_SOLO=
RAZORPAY_PLAN_ID_PERSONAL=
RAZORPAY_PLAN_ID_CA_FIRM=
RAZORPAY_PLAN_ID_AI_ADDON=

# Pricing (in paise) — overridable without a code deploy
PLAN_PRICE_BUSINESS_CA=149900
PLAN_PRICE_BUSINESS_SOLO=299900
PLAN_PRICE_PERSONAL=99900
PLAN_PRICE_CA_FIRM=0          # TBD — set this before CA trial ends
PLAN_PRICE_AI_ADDON=49900
```

---

## Files to Create / Modify

### Migrations
| File | Purpose |
|---|---|
| `..._create_plans_table.php` | Plans table |
| `..._create_subscriptions_table.php` | Subscriptions table |
| `..._create_subscription_addons_table.php` | Addons table |

### Models
| File | Purpose |
|---|---|
| `app/Models/Plan.php` | Plan model + feature helpers |
| `app/Models/Subscription.php` | Subscription model, `isActive()`, `onTrial()` |
| `app/Models/SubscriptionAddon.php` | Addon model |
| `app/Models/Tenant.php` | Add `subscription()` relationship + `hasFeature()` helper |

### Services
| File | Purpose |
|---|---|
| `app/Services/RazorpayService.php` | Wraps Razorpay PHP SDK; creates subscriptions, verifies signatures |
| `app/Services/SubscriptionService.php` | `can(Tenant, feature)`, `activate()`, `halt()`, seeder helper |

### Controllers
| File | Purpose |
|---|---|
| `app/Http/Controllers/BillingController.php` | `selectPlan()`, `subscribe()`, `success()`, `cancel()` |
| `app/Http/Controllers/RazorpayWebhookController.php` | Webhook receiver + signature verification |
| `app/Http/Controllers/Api/MobileBillingController.php` | Mobile parity: plan list + subscribe |

### Middleware
| File | Purpose |
|---|---|
| `app/Http/Middleware/EnsureActiveSubscription.php` | Gate all tenant routes |

### Vue Pages
| File | Purpose |
|---|---|
| `resources/js/Pages/Billing/SelectPlan.vue` | Plan picker shown after registration |
| `resources/js/Pages/Billing/Index.vue` | Billing management (current plan, cancel, upgrade) |

### Config / Seeder
| File | Purpose |
|---|---|
| `config/plans.php` | Feature lists per plan slug (single source of truth) |
| `database/seeders/PlanSeeder.php` | Seeds `plans` table from `config/plans.php` |

### Routes
```php
// web.php — outside tenant middleware so billing page is reachable before subscription
Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])
    ->name('webhooks.razorpay');

// Inside tenant group, billing bypass added to EnsureActiveSubscription
Route::get('/t/{tenant}/billing', [BillingController::class, 'index'])->name('billing.index');
Route::get('/t/{tenant}/billing/select-plan', [BillingController::class, 'selectPlan'])->name('billing.select-plan');
Route::post('/t/{tenant}/billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
```

---

## Implementation Order

1. **Migrations + Models + Seeder** — DB foundation, seed plans from config
2. **RazorpayService** — SDK wrapper, create subscription, verify webhook sig
3. **EnsureActiveSubscription middleware** — register it, test bypass logic
4. **BillingController + SelectPlan.vue** — plan picker page, Razorpay checkout redirect
5. **RazorpayWebhookController** — handle all 5 webhook events
6. **SubscriptionService::can()** — feature gate helper
7. **Gate group chat, Tally sync, AI chat** — controller + Vue guards
8. **BillingController::index + Billing/Index.vue** — manage plan, cancel, upgrade
9. **Mobile API parity** — MobileBillingController + routes + api-mobile.md update
10. **Audit events** — `subscription.trial_started`, `subscription.started`, `subscription.renewed`, `subscription.halted`, `subscription.cancelled`, `subscription.expired` ✓

---

## Open Questions

- What price does the CA firm plan charge after the 14-day trial? Set `PLAN_PRICE_CA_FIRM` and `RAZORPAY_PLAN_ID_CA_FIRM` when decided.
- Should the AI addon be cancellable independently of the main subscription?
- Should upgrading from `personal` → `business_solo` mid-cycle be prorated?
