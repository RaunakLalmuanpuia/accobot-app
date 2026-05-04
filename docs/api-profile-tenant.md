# Profile & Tenant Management API

Base URL: `/api`
Auth: **Laravel Sanctum — Bearer token**

## Required Headers

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

> Get your Bearer token from `POST /api/mobile/login`.
> Get the tenant UUID from `GET /api/mobile/me` → `tenants[].id`.

---

## Table of Contents

- [User Profile](#user-profile)
  - [GET /api/mobile/profile](#get-apimobileprofile)
  - [PATCH /api/mobile/profile](#patch-apimobileprofile)
  - [POST /api/mobile/profile/change-password](#post-apimobileprofilechange-password)
  - [DELETE /api/mobile/profile](#delete-apimobileprofile)
- [Tenant Profile](#tenant-profile)
  - [GET /api/mobile/tenants/{tenant}/profile](#get-apimobiletenantstenanrprofile)
  - [PATCH /api/mobile/tenants/{tenant}/profile](#patch-apimobiletenantstenantprofile)
- [Tenant Bank Accounts](#tenant-bank-accounts)
  - [GET /api/mobile/tenants/{tenant}/bank-accounts](#get-apimobiletenantstenantbank-accounts)
  - [POST /api/mobile/tenants/{tenant}/bank-accounts](#post-apimobiletenantstenantbank-accounts)
  - [PUT /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}](#put-apimobiletenantstenantbank-accountsbankaccount)
  - [POST .../bank-accounts/{bankAccount}/set-primary](#post-bank-accountsbankaccountset-primary)
  - [DELETE /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}](#delete-apimobiletenantstenantbank-accountsbankaccount)

---

## User Profile

### `GET /api/mobile/profile`

Returns the authenticated user's profile.

**Response 200**
```json
{
  "user": {
    "id": 1,
    "name": "CA One",
    "email": "ca1@example.com",
    "phone": "+91 98765 43210",
    "pan": "ABCDE1234F",
    "type": "human",
    "status": "active",
    "email_verified_at": "2026-04-10T08:00:00.000000Z",
    "created_at": "2026-04-10T08:00:00.000000Z"
  }
}
```

---

### `PATCH /api/mobile/profile`

Update the authenticated user's name, email, phone, or PAN. If email changes, `email_verified_at` is cleared.

**Request body:**
```json
{
  "name": "CA One",
  "email": "ca1@example.com",
  "phone": "+91 98765 43210",
  "pan": "ABCDE1234F"
}
```

| Field | Required | Validation |
|---|:---:|---|
| `name` | Yes | string, max 255 |
| `email` | Yes | valid email, unique across users |
| `phone` | No | string, max 20, nullable |
| `pan` | No | exactly 10 chars, format `ABCDE1234F`, nullable |

**Response 200**
```json
{
  "message": "Profile updated.",
  "user": {
    "id": 1,
    "name": "CA One",
    "email": "ca1@example.com",
    "phone": "+91 98765 43210",
    "pan": "ABCDE1234F",
    "type": "human",
    "status": "active",
    "email_verified_at": null
  }
}
```

| Status | Reason |
|---|---|
| `422` | Validation failed (e.g. email already taken) |

---

### `POST /api/mobile/profile/change-password`

**Request body:**
```json
{
  "current_password": "old-secret",
  "password": "new-secret-123",
  "password_confirmation": "new-secret-123"
}
```

| Field | Required | Notes |
|---|:---:|---|
| `current_password` | Yes | Must match existing password |
| `password` | Yes | Min 8 chars, Laravel default strength rules |
| `password_confirmation` | Yes | Must match `password` |

**Response 200**
```json
{
  "message": "Password changed."
}
```

| Status | Reason |
|---|---|
| `422` | Wrong current password or new password too weak |

---

### `DELETE /api/mobile/profile`

Permanently deletes the account. All tokens are revoked first.

**Request body:**
```json
{
  "password": "current-secret"
}
```

**Response 200**
```json
{
  "message": "Account deleted."
}
```

| Status | Reason |
|---|---|
| `422` | Wrong password |

---

## Tenant Profile

Requires the user to be a member of the tenant.

| Permission needed | Endpoints |
|---|---|
| `tenant.view_settings` | GET |
| `tenant.update_settings` | PATCH |

---

### `GET /api/mobile/tenants/{tenant}/profile`

**Response 200**
```json
{
  "tenant": {
    "id": "uuid",
    "name": "Acme Ltd",
    "type": "business",
    "status": "active",
    "phone": "+91 98765 43210",
    "email": "hello@acme.com",
    "website": "https://acme.com",
    "gstin": "22AAAPL1234F1Z5",
    "pan": "AAAPL1234F",
    "logo_url": "https://cdn.acme.com/logo.png",
    "address_line1": "123 Main Street",
    "address_line2": "Floor 4",
    "city": "Mumbai",
    "state": "Maharashtra",
    "pincode": "400001",
    "created_at": "2026-05-04T10:00:00.000000Z"
  }
}
```

---

### `PATCH /api/mobile/tenants/{tenant}/profile`

All fields except `name` are optional — send only the fields you want to update.

**Request body:**
```json
{
  "name": "Acme Ltd",
  "phone": "+91 98765 43210",
  "email": "hello@acme.com",
  "website": "https://acme.com",
  "gstin": "22AAAPL1234F1Z5",
  "pan": "AAAPL1234F",
  "logo_url": "https://cdn.acme.com/logo.png",
  "address_line1": "123 Main Street",
  "address_line2": "Floor 4",
  "city": "Mumbai",
  "state": "Maharashtra",
  "pincode": "400001"
}
```

| Field | Required | Validation |
|---|:---:|---|
| `name` | Yes | string, max 255 |
| `phone` | No | string, max 20, nullable |
| `email` | No | valid email, max 255, nullable |
| `website` | No | valid URL, max 500, nullable |
| `gstin` | No | exactly 15 chars, e.g. `22AAAPL1234F1Z5`, nullable |
| `pan` | No | exactly 10 chars, e.g. `AAAPL1234F`, nullable |
| `logo_url` | No | valid URL, max 500, nullable |
| `address_line1` | No | string, max 255, nullable |
| `address_line2` | No | string, max 255, nullable |
| `city` | No | string, max 100, nullable |
| `state` | No | string, max 100, nullable |
| `pincode` | No | string, max 10, nullable |

**Response 200**
```json
{
  "message": "Tenant profile updated.",
  "tenant": {
    "id": "uuid",
    "name": "Acme Ltd",
    "type": "business",
    "status": "active",
    "phone": "+91 98765 43210",
    "email": "hello@acme.com",
    "website": "https://acme.com",
    "gstin": "22AAAPL1234F1Z5",
    "pan": "AAAPL1234F",
    "logo_url": "https://cdn.acme.com/logo.png",
    "address_line1": "123 Main Street",
    "address_line2": "Floor 4",
    "city": "Mumbai",
    "state": "Maharashtra",
    "pincode": "400001",
    "created_at": "2026-05-04T10:00:00.000000Z"
  }
}
```

---

## Tenant Bank Accounts

Requires the user to be a member of the tenant.

| Permission needed | Endpoints |
|---|---|
| `tenant.view_settings` | GET (list) |
| `tenant.update_settings` | POST, PUT, set-primary, DELETE |

---

### `GET /api/mobile/tenants/{tenant}/bank-accounts`

Returns all bank accounts for the tenant, primary account first.

**Response 200**
```json
{
  "bank_accounts": [
    {
      "id": 1,
      "bank_name": "State Bank of India",
      "account_holder_name": "Acme Ltd",
      "account_number": "12345678901",
      "ifsc_code": "SBIN0001234",
      "account_type": "current",
      "branch": "MG Road",
      "is_primary": true
    },
    {
      "id": 2,
      "bank_name": "HDFC Bank",
      "account_holder_name": "Acme Ltd",
      "account_number": "98765432101",
      "ifsc_code": "HDFC0001234",
      "account_type": "savings",
      "branch": null,
      "is_primary": false
    }
  ]
}
```

---

### `POST /api/mobile/tenants/{tenant}/bank-accounts`

The first account added is automatically set as primary.

**Request body:**
```json
{
  "bank_name": "State Bank of India",
  "account_holder_name": "Acme Ltd",
  "account_number": "12345678901",
  "ifsc_code": "SBIN0001234",
  "account_type": "current",
  "branch": "MG Road"
}
```

| Field | Required | Validation |
|---|:---:|---|
| `bank_name` | Yes | string, max 255 |
| `account_holder_name` | Yes | string, max 255 |
| `account_number` | Yes | string, max 50 |
| `ifsc_code` | Yes | exactly 11 chars, format `XXXX0XXXXXX` |
| `account_type` | Yes | one of `savings` / `current` / `overdraft` |
| `branch` | No | string, max 255, nullable |

**Response 201**
```json
{
  "message": "Bank account added.",
  "bank_account": {
    "id": 1,
    "bank_name": "State Bank of India",
    "account_holder_name": "Acme Ltd",
    "account_number": "12345678901",
    "ifsc_code": "SBIN0001234",
    "account_type": "current",
    "branch": "MG Road",
    "is_primary": true
  }
}
```

---

### `PUT /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}`

All fields are optional — send only what you want to change.

**Request body (partial update example):**
```json
{
  "branch": "Nariman Point",
  "account_type": "overdraft"
}
```

**Full body example:**
```json
{
  "bank_name": "State Bank of India",
  "account_holder_name": "Acme Ltd",
  "account_number": "12345678901",
  "ifsc_code": "SBIN0001234",
  "account_type": "current",
  "branch": "Nariman Point"
}
```

**Response 200**
```json
{
  "message": "Bank account updated.",
  "bank_account": {
    "id": 1,
    "bank_name": "State Bank of India",
    "account_holder_name": "Acme Ltd",
    "account_number": "12345678901",
    "ifsc_code": "SBIN0001234",
    "account_type": "current",
    "branch": "Nariman Point",
    "is_primary": true
  }
}
```

---

### `POST .../bank-accounts/{bankAccount}/set-primary`

Full path: `POST /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}/set-primary`

No request body. Marks the given account as primary and clears `is_primary` on all others.

**Response 200**
```json
{
  "message": "Primary account updated."
}
```

---

### `DELETE /api/mobile/tenants/{tenant}/bank-accounts/{bankAccount}`

No request body. If the deleted account was primary, the next account (by insertion order) is automatically promoted to primary.

**Response 200**
```json
{
  "message": "Bank account removed."
}
```

---

## Error Response Format

**Validation error (422)**
```json
{
  "message": "The ifsc code must be 11 characters.",
  "errors": {
    "ifsc_code": ["The ifsc code must be 11 characters."]
  }
}
```

**Unauthenticated (401)**
```json
{ "message": "Unauthenticated." }
```

**Forbidden (403)**
```json
{ "message": "This action is unauthorized." }
```
