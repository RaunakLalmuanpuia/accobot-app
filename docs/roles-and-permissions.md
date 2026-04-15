# Roles & Permissions

All passwords for seeded users: **`password`**

---

## Tenants

| Tenant | Type | Personal | Created By |
|---|---|---|---|
| Tili | Business | No | Fela |
| Awab | Business | No | Fela |
| Eightsis | Business | No | Zoa |
| Alpha Advisors | CA Firm | Yes | CA1 |
| Beta Consulting | CA Firm | Yes | CA2 |

---

## Seeded Users

| User | Email | Tenant | Role | Member Type |
|---|---|---|---|---|
| Platform Admin | admin@example.com | — (platform-wide) | admin | — |
| Fela | fela@example.com | Tili | owner | internal |
| Fela | fela@example.com | Awab | owner | internal |
| Fela | fela@example.com | Eightsis | owner | internal |
| Zoa | zoa@example.com | Eightsis | owner | internal |
| Zira | zira@example.com | Tili | TenantAdmin | internal |
| Zira | zira@example.com | Awab | TenantAdmin | internal |
| Dini | dini@example.com | Tili | Manager | internal |
| Dini | dini@example.com | Awab | Manager | internal |
| Dini | dini@example.com | Eightsis | Manager | internal |
| Raunak | raunak@example.com | Eightsis | Staff | internal |
| Dennis | dennis@example.com | Tili | Staff | internal |
| Madini | madini@example.com | Awab | Staff | internal |
| CA1 | ca1@example.com | Alpha Advisors | OwnerPartner | internal |
| CA1 | ca1@example.com | Tili | ExternalAccountant | external (via Alpha Advisors) |
| CA1 | ca1@example.com | Awab | ExternalAccountant | external (via Alpha Advisors) |
| CA2 | ca2@example.com | Beta Consulting | OwnerPartner | internal |
| CA2 | ca2@example.com | Eightsis | ExternalAccountant | external (via Beta Consulting) |

---

## Permission Catalog

| Permission | Description |
|---|---|
| `tenant.view_settings` | View tenant settings |
| `tenant.update_settings` | Update tenant settings |
| `members.view` | View team members |
| `members.invite` | Send member invitations |
| `members.remove` | Remove members |
| `members.suspend` | Suspend members |
| `members.assign_role` | Assign roles to members |
| `clients.view_requests` | View CA link requests (business owner only) |
| `clients.approve_link` | Approve CA linking requests |
| `clients.terminate_link` | Terminate CA links |
| `clients.view` | View clients |
| `clients.create` | Create clients |
| `clients.edit` | Edit clients |
| `clients.delete` | Delete clients |
| `vendors.view` | View vendors |
| `vendors.create` | Create vendors |
| `vendors.edit` | Edit vendors |
| `vendors.delete` | Delete vendors |
| `products.view` | View products / inventory |
| `products.create` | Create products |
| `products.edit` | Edit products |
| `products.delete` | Delete products |
| `narration_heads.view` | View narration heads & sub-heads |
| `narration_heads.create` | Create narration heads |
| `narration_heads.edit` | Edit narration heads |
| `narration_heads.delete` | Delete narration heads |
| `invoices.view` | View invoices |
| `invoices.create` | Create invoices |
| `invoices.edit` | Edit invoices |
| `invoices.delete` | Delete invoices |
| `reports.view` | View reports |
| `reports.export` | Export reports |
| `integrations.view` | View integrations |
| `integrations.manage` | Manage integrations |
| `audit.view` | View audit log |
| `chat.view` | Use the AI accounting assistant |
| `transactions.view` | View bank transactions |
| `transactions.review` | Approve / reject pending transactions |
| `transactions.edit` | Correct narration, link invoices |
| `transactions.import` | Upload statements, paste SMS / email |

---

## Roles & Their Permissions

### Platform Role

#### `admin`
Full platform access. Assigned to Platform Admin only. Gets all permissions.

---

### Business Tenant Roles

#### `owner`
Full access to everything in the tenant. Intended for the business founder/director.

| Group | Permissions |
|---|---|
| Settings | view_settings, update_settings |
| Members | view, invite, remove, suspend, assign_role |
| CA Linking | view_requests, approve_link, terminate_link |
| Clients | view, create, edit, delete |
| Vendors | view, create, edit, delete |
| Products | view, create, edit, delete |
| Narration Heads | view, create, edit, delete |
| Invoices | view, create, edit, delete |
| Reports | view, export |
| Integrations | view, manage |
| Audit | view |
| Chat | view |
| Transactions | view, review, edit, import |

---

#### `TenantAdmin`
Operations admin. Full access except CA approval rights (`clients.approve_link`, `clients.terminate_link`).

| Group | Permissions |
|---|---|
| Settings | view_settings, update_settings |
| Members | view, invite, remove, suspend, assign_role |
| CA Linking | view_requests |
| Clients | view, create, edit, delete |
| Vendors | view, create, edit, delete |
| Products | view, create, edit, delete |
| Narration Heads | view, create, edit, delete |
| Invoices | view, create, edit, delete |
| Reports | view, export |
| Integrations | view, manage |
| Audit | view |
| Chat | view |
| Transactions | view, review, edit, import |

---

#### `Manager`
Day-to-day operations. No settings, no deletes, no integrations management.

| Group | Permissions |
|---|---|
| Members | view |
| Clients | view, create, edit |
| Vendors | view, create, edit |
| Products | view, create, edit |
| Narration Heads | view, create, edit |
| Invoices | view, create, edit |
| Reports | view, export |
| Integrations | view |
| Chat | view |
| Transactions | view, review, edit, import |

---

#### `Staff`
Entry-level. Can create basic records and view/review transactions. No deletes, no exports, no settings.

| Group | Permissions |
|---|---|
| Clients | view, create |
| Vendors | view, create |
| Products | view, create |
| Narration Heads | view, create |
| Invoices | view, create, edit |
| Reports | view |
| Transactions | view, review |

---

#### `Viewer`
Read-only across all modules. No create, edit, delete, or transaction actions.

| Group | Permissions |
|---|---|
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Invoices | view |
| Reports | view |
| Transactions | view |

---

#### `ExternalAccountant`
CA staff working inside a client business tenant. Safe read-only baseline with export access.

| Group | Permissions |
|---|---|
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Invoices | view |
| Reports | view, export |
| Transactions | view |

---

### CA Firm Tenant Roles

#### `OwnerPartner`
Senior CA / founding partner. Full firm management but no invoicing (CA firms don't use client invoicing internally).

| Group | Permissions |
|---|---|
| Settings | view_settings, update_settings |
| Members | view, invite, remove, suspend, assign_role |
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Reports | view, export |
| Integrations | view, manage |
| Audit | view |

---

#### `CAManager`
Mid-level CA manager. View access across modules, can export reports.

| Group | Permissions |
|---|---|
| Members | view |
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Reports | view, export |

---

#### `Auditor`
Read-only + export. Same as ExternalAccountant but also sees invoices and can export.

| Group | Permissions |
|---|---|
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Invoices | view |
| Reports | view, export |

---

#### `CAStaff`
Junior CA staff. View-only, no export.

| Group | Permissions |
|---|---|
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Reports | view |

---

#### `IntegrationUser`
Machine / API integration account. Can view core records and create invoices programmatically.

| Group | Permissions |
|---|---|
| Clients | view |
| Vendors | view |
| Products | view |
| Narration Heads | view |
| Invoices | view, create |
| Reports | view |

---

## Quick Comparison Matrix

| Permission | admin | owner | TenantAdmin | Manager | Staff | Viewer | ExtAccountant | OwnerPartner | CAManager | Auditor | CAStaff | IntegrationUser |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| tenant.view_settings | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| tenant.update_settings | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| members.view | ✓ | ✓ | ✓ | ✓ | | | | ✓ | ✓ | | | |
| members.invite | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| members.remove | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| members.suspend | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| members.assign_role | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| clients.view_requests | ✓ | ✓ | ✓ | | | | | | | | | |
| clients.approve_link | ✓ | ✓ | | | | | | | | | | |
| clients.terminate_link | ✓ | ✓ | | | | | | | | | | |
| clients.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| clients.create | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| clients.edit | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| clients.delete | ✓ | ✓ | ✓ | | | | | | | | | |
| vendors.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| vendors.create | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| vendors.edit | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| vendors.delete | ✓ | ✓ | ✓ | | | | | | | | | |
| products.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| products.create | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| products.edit | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| products.delete | ✓ | ✓ | ✓ | | | | | | | | | |
| narration_heads.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| narration_heads.create | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| narration_heads.edit | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| narration_heads.delete | ✓ | ✓ | ✓ | | | | | | | | | |
| invoices.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | ✓ | | ✓ |
| invoices.create | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | ✓ |
| invoices.edit | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| invoices.delete | ✓ | ✓ | ✓ | | | | | | | | | |
| reports.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| reports.export | ✓ | ✓ | ✓ | ✓ | | | ✓ | ✓ | ✓ | ✓ | | |
| integrations.view | ✓ | ✓ | ✓ | ✓ | | | | ✓ | | | | |
| integrations.manage | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| audit.view | ✓ | ✓ | ✓ | | | | | ✓ | | | | |
| chat.view | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| transactions.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | |
| transactions.review | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | | | |
| transactions.edit | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
| transactions.import | ✓ | ✓ | ✓ | ✓ | | | | | | | | |
