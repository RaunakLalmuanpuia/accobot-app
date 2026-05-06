# Onboarding

This document covers the post-registration onboarding flow for both **Business Owners** and **CA / Accountants**, including how a CA firm connects to client businesses.

---

## Overview

After registration, users land directly on their tenant dashboard. A dismissable onboarding checklist widget appears at the top of the dashboard until all items are complete (or the user dismisses it).

Onboarding is only shown to **internal, owner-level members** (`owner`, `OwnerPartner`, `TenantAdmin`) of a tenant. Staff, external members, and read-only users never see it.

---

## Registration

Both roles register at `/register`. The form collects:

| Field | Notes |
|---|---|
| `name` | User's display name |
| `email` | Must be unique |
| `password` | Standard strength requirements |
| `role` | `owner` (Business) or `ca` (CA firm) |
| `tenant_name` | Business name or CA firm name |

On submit:
- A **User** record is created.
- A **Tenant** is created (`type: business` for owners, `type: ca_firm` for CAs).
- The user is attached to the tenant as `owner` / `OwnerPartner`.
- A `TallyConnection` record is auto-provisioned for all registrations so the token is ready immediately.
- The user lands on their tenant dashboard.

---

## Onboarding Checklist

The checklist is computed live from the tenant's data — no separate state to sync.

### Business Owner checklist

| Step | Done when |
|---|---|
| Create your account | Always ✓ |
| Complete business profile | `gstin` or `pan` or (`city` + `state`) is set |
| Add a bank account | At least one bank account exists |
| Connect Tally | `inbound_token_last_used_at` is not null — only shown when `tally_managed_by_ca = false` |

Links to: Settings → Profile, Settings → Profile (bank account section), Settings → Tally.

### CA / Firm checklist

| Step | Done when |
|---|---|
| Create your account | Always ✓ |
| Complete firm profile | `phone` or `gstin` or `pan` is set |
| Connect Tally | `inbound_token_last_used_at` is not null (plugin has synced at least once) |
| Invite team members | More than 1 internal member on the tenant |
| Add your first client | At least one business is linked as a client |

Links to: Settings → Profile, Settings → Tally, Settings → Team, CA Clients page.

> **Note:** Business tenants that were created via CA invitation (`tally_managed_by_ca = true`) do **not** get a Tally onboarding step. Their Tally data is routed by the CA's plugin using the token provisioned at invitation accept time.

### Dismissal

Clicking **Dismiss** on the widget sends `POST /t/{tenant}/onboarding/dismiss`, which sets `onboarding_dismissed_at` on the tenant. The checklist will not reappear, even if items remain incomplete.

---

## CA → Business Client Flow

### Step 1 — CA invites a business

CA goes to **My Client Businesses** (`/t/{tenant}/ca/businesses`) and clicks **Add Client**.

Fields:
- `email` *(required)* — business owner's email
- `business_name` *(optional)* — pre-fills the business name if they register via the link

**Two outcomes:**

**A. User already exists and has a business tenant**
The CA is linked immediately as an `ExternalAccountant` on that business tenant. No email sent.

**B. User doesn't exist (or exists but has no business yet)**
An invitation of type `ca_client` is created. The business owner receives an email: *"[Firm name] wants to manage your business on Accobot."*

The invitation:
- Expires in **14 days** (longer than team invites, which are 7 days)
- Is revocable from the Pending Invitations section

### Step 2 — Business owner accepts

The invite link opens `/invite/{token}`.

**If the owner is a new user**, the acceptance page shows:
- Name field
- Business name field (pre-filled from `suggested_business_name` if the CA provided one)
- Password fields

On submit:
1. User account is created.
2. A business tenant is created (using the provided business name), with `tally_managed_by_ca = true`.
3. The CA is linked to that business as `ExternalAccountant`.
4. A `TallyConnection` is auto-provisioned for the business tenant (CA copies this token into their Tally plugin).
5. The owner lands on their new business dashboard.

**If the owner already has an account**, they see a single accept button. On accept:
1. Their existing primary business tenant is found.
2. The CA is linked to that tenant (`tally_managed_by_ca` is **not** changed — the business already exists independently).
3. A `TallyConnection` is created for the business if one doesn't exist yet.
4. They land on their business dashboard.

### What "linked" means in the database

When a CA is linked to a business, the following records are created:

**`tenant_user` row:**
```
user_id          = CA user's ID
tenant_id        = Business tenant's ID
member_type      = external
source_tenant_id = CA firm's tenant ID
role_name        = ExternalAccountant
status           = active
```

**`tenant_user_roles` row:**
```
user_id   = CA user's ID
tenant_id = Business tenant's ID
role_id   = ExternalAccountant role ID
```

This gives the CA user `ExternalAccountant` permissions on the business tenant, while `source_tenant_id` records which firm they came from (used for display and bulk unlink).

### Removing a client

CA clicks **Remove** next to the client in My Clients. This:
1. Deletes all `tenant_user` rows where `source_tenant_id = ca_firm_id` and `tenant_id = business_tenant_id`.
2. Deletes the corresponding `tenant_user_roles` rows.
3. Logs a `ca.client.unlinked` audit event on the business tenant.

---

## Tally Token Provisioning

Every tenant gets a `TallyConnection` record with a unique `inbound_token` provisioned at the right moment:

| Who | When provisioned | Managed by |
|---|---|---|
| CA firm | On registration (`AuthController::register`) | CA — Settings → Tally |
| Self-registered business | On registration (`AuthController::register`) | Business — Settings → Tally |
| Business created via CA invitation | When CA invitation is accepted (`CaClientLinkService::link`) | CA — cannot be managed by the business (`tally_managed_by_ca = true`) |
| Existing business linked directly by CA | When CA links them (`CaClientLinkService::link`, `firstOrCreate`) | Business retains control (pre-existing tenant, `tally_managed_by_ca` unchanged) |

The CA copies each client's `inbound_token` from the **CA Clients page** and pastes it into their Tally plugin for that company. Each token routes synced data to the correct tenant.

Tenants with `tally_managed_by_ca = true` receive a 403 on all Tally connection management routes (`show`, `save`, `regenerateToken`, `destroy`, `testConnection`). The "Settings" link is also hidden on their Tally Sync page.

---

## Client Model ↔ CA-Business Link Sync

The `Client` model (accounting contact records used for invoicing) is partially synced with the CA-business tenant link.

### Direction 1: CA links a business → existing Client record updated (never created)

When `CaClientLinkService::link()` runs, it calls `syncClientRecord()` which:

1. Looks for an existing `Client` in the CA firm with `linked_tenant_id = business_tenant_id`.
2. If not found, tries to match by email.
3. If a match is found, updates `name`, `email`, `phone`, `tax_id`, `address`, and sets `linked_tenant_id`.
4. If no match, does nothing — Client records are managed manually by the CA.

### Direction 2: Client record → Invite to Accobot

On the **Clients** page (`/t/{tenant}/clients`), CA firms see an extra **Accobot** column:

| State | Shown as |
|---|---|
| `linked_tenant_id` is set | "Connected" badge (violet) |
| Pending `ca_client` invitation exists | "Invited" badge (amber) |
| Client has an email but no link | "Invite" link (triggers invitation) |
| Client has no email | `—` |

Clicking **Invite** calls `POST /t/{tenant}/clients/{client}/invite`:
- If the client's email matches an existing Accobot user with a business tenant → links directly, no email.
- Otherwise → sends the `CaClientInvitationMail` and creates a `ca_client` invitation.

### Direction 3: Invitation accepted → back-fill existing Client record

When a business owner accepts a `ca_client` invitation (`InvitationController::acceptCaClientInvitation`), the system also runs:

```php
Client::where('tenant_id', $caFirmTenant->id)
    ->where('email', $user->email)
    ->whereNull('linked_tenant_id')
    ->update(['linked_tenant_id' => $businessTenant->id]);
```

So if the CA had manually added this client's email before they joined Accobot, that record gets connected automatically.

### Unlinking

When a CA removes a client (`CaClientLinkService::unlink()`), `linked_tenant_id` is set to `null` on the matching `Client` record. The `Client` record itself is **not deleted** — the CA may still have invoices referencing it.

### New columns

| Table | Column | Type | Notes |
|---|---|---|---|
| `clients` | `linked_tenant_id` | `uuid nullable FK → tenants.id` | `nullOnDelete` — cleared automatically if the business tenant is deleted |
| `tenants` | `tally_managed_by_ca` | `boolean default false` | Set to `true` when a new business tenant is created by accepting a CA invitation; blocks self-managed Tally connection |

---

## Invitation Model Changes

The `invitations` table was extended to support CA client invitations:

| Column | Type | Notes |
|---|---|---|
| `invitation_type` | `string` | `member` (default) or `ca_client` |
| `meta` | `json` | Extra data; for `ca_client`: `{"business_name": "..."}` |
| `role_id` | `bigint nullable` | Nullable for `ca_client` invitations (role is determined at accept time) |

---

## Audit Events

| Event | Tenant context | Fired when |
|---|---|---|
| `ca.client.linked` | Business tenant | CA linked to business (via `CaClientLinkService::link()`) |
| `ca.client.unlinked` | Business tenant | CA removed from business |
| `ca.client.invite.accepted` | Business tenant | Business owner accepted a CA client invitation |
| `ca.client.linked_direct` | CA firm tenant | CA added existing user who already had a business tenant (direct link, no email) |
| `ca.client.invited` | CA firm tenant | CA client invitation email sent via CA Clients page |
| `ca.client.invite_revoked` | CA firm tenant | Pending invitation revoked (revocation email sent to invitee) |
| `member.invite_revoked` | Tenant | Team member invite revoked (revocation email sent to invitee) |
| `ca.client.linked_from_client_record` | CA firm tenant | "Invite" clicked on Client record; linked directly (user already had a business) |
| `ca.client.invited_from_client_record` | CA firm tenant | "Invite" clicked on Client record; invitation email sent |

---

## Web Routes

| Method | URI | Name | Notes |
|---|---|---|---|
| `POST` | `/t/{tenant}/onboarding/dismiss` | `onboarding.dismiss` | Dismisses checklist |
| `GET` | `/t/{tenant}/ca/businesses` | `ca.businesses.index` | CA firm only |
| `POST` | `/t/{tenant}/ca/businesses` | `ca.businesses.store` | Invite / link business |
| `DELETE` | `/t/{tenant}/ca/businesses/{businessTenant}` | `ca.businesses.destroy` | Unlink business |
| `DELETE` | `/t/{tenant}/ca/businesses/invites/{invitation}` | `ca.businesses.invites.revoke` | Revoke pending invite |
| `POST` | `/t/{tenant}/clients/{client}/invite` | `clients.invite` | Invite existing Client record to Accobot |

---

## Mobile API Routes

| Method | URI | Notes |
|---|---|---|
| `GET` | `/api/mobile/tenants/{tenant}/onboarding` | Checklist + dismissed state |
| `POST` | `/api/mobile/tenants/{tenant}/onboarding/dismiss` | Dismiss checklist |
| `GET` | `/api/mobile/tenants/{tenant}/ca-businesses` | List linked businesses + pending invites |
| `POST` | `/api/mobile/tenants/{tenant}/ca-businesses` | Invite / link business |
| `DELETE` | `/api/mobile/tenants/{tenant}/ca-businesses/{businessTenant}` | Unlink business |
| `DELETE` | `/api/mobile/tenants/{tenant}/ca-businesses/invites/{invitation}` | Revoke pending invite |

See `docs/api-mobile.md` for full request/response shapes.

> **Mobile parity gap:** `POST /t/{tenant}/clients/{client}/invite` (invite from Client record) has no mobile API equivalent. CA firms using the mobile app can invite clients via `POST /api/mobile/tenants/{tenant}/ca-businesses` by email instead.

---

## Files Added / Modified

| File | Change |
|---|---|
| `database/migrations/2026_05_05_000001_add_invitation_type_to_invitations_table.php` | Adds `invitation_type`, `meta`, makes `role_id` nullable |
| `database/migrations/2026_05_05_000002_add_onboarding_dismissed_at_to_tenants_table.php` | Adds `onboarding_dismissed_at` to tenants |
| `database/migrations/2026_05_06_000001_add_tally_managed_by_ca_to_tenants_table.php` | Adds `tally_managed_by_ca` boolean to tenants |
| `app/Models/Invitation.php` | Added `invitation_type`, `meta` fillable + `isCaClientInvite()` |
| `app/Models/Tenant.php` | Added `onboarding_dismissed_at`, `tally_managed_by_ca`, `linkedBusinessClients()`, `linkedCaFirms()` |
| `app/Models/Client.php` | Added `linked_tenant_id` fillable, `linkedTenant()` relationship, `isLinkedToAccobot()` |
| `app/Services/CaClientLinkService.php` | Links / unlinks CA firm ↔ business tenant; `syncClientRecord()` (updates existing, never creates); `firstOrCreate` TallyConnection on link |
| `app/Http/Controllers/AuthController.php` | Auto-creates TallyConnection for all tenants on registration |
| `app/Http/Controllers/OnboardingController.php` | Checklist builder + dismiss endpoint; CA checklist includes "Connect Tally" step |
| `app/Http/Controllers/CaClientController.php` | CA client CRUD (web); passes Tally token per linked business |
| `app/Http/Controllers/ClientController.php` | Added `invite()` action; `index()` passes `linked_tenant_id` + `invite_pending` |
| `app/Http/Controllers/InvitationController.php` | Sets `tally_managed_by_ca = true` on new business tenants created via CA invitation |
| `app/Http/Controllers/TallyConnectionController.php` | Blocks all manage actions for `tally_managed_by_ca` tenants via private guard; removes `company_id` from validation; uses `updateOrCreate` in `save()` |
| `resources/js/Pages/Tally/Connection.vue` | Removes `company_id` input and table row entirely |
| `app/Http/Controllers/DashboardController.php` | Passes `onboarding` prop to Tenant dashboard |
| `app/Http/Controllers/Api/MobileOnboardingController.php` | Mobile onboarding API (auto-includes CA Tally step via shared `buildChecklist()`) |
| `app/Http/Controllers/Api/MobileCaClientController.php` | Mobile CA clients API |
| `app/Mail/CaClientInvitationMail.php` | CA client invitation email |
| `app/Mail/CaClientInviteRevokedMail.php` | CA client revocation notification email |
| `app/Mail/InvitationRevokedMail.php` | Team member invite revocation notification email |
| `resources/views/emails/ca_client_invitation.blade.php` | CA client invitation email template |
| `resources/views/emails/ca_client_invite_revoked.blade.php` | CA client revocation email template |
| `resources/views/emails/invitation_revoked.blade.php` | Team member revocation email template |
| `resources/js/Components/OnboardingChecklist.vue` | Checklist widget (progress bar + step list, dismissable) |
| `resources/js/Pages/CaClients/Index.vue` | Shows Tally token + copy button per connected client |
| `resources/js/Pages/Tally/Sync.vue` | "Settings" link hidden for `tally_managed_by_ca` tenants |
| `resources/js/Pages/Clients/Index.vue` | Accobot column: Connected / Invited / Invite button per row (CA firms only) |
| `resources/js/Pages/Dashboard/Tenant.vue` | Shows checklist widget + My Clients quick action for CA firms |
| `resources/js/Pages/Invitations/Accept.vue` | Handles `ca_client` invitation context (different heading, business name field) |
| `routes/web.php` | Onboarding dismiss, CA client CRUD, client invite routes |
| `routes/api.php` | Mobile onboarding + CA client routes |
