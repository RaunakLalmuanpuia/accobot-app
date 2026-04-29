# Group Chat — Developer Reference

**Stack**: Laravel 13 · Vue 3 · Inertia.js v2 · Laravel Reverb (WebSocket) · PostgreSQL  
**Deployed**: `accobot.eightsis.com` (production) · **Status**: Fully live (Phase 12 FCM/APNs pending)

---

## Table of Contents

1. [Feature Overview](#1-feature-overview)
2. [Architecture](#2-architecture)
3. [Database Schema](#3-database-schema)
4. [Models](#4-models)
5. [Broadcasting (Real-Time)](#5-broadcasting-real-time)
6. [Web Routes & Controllers](#6-web-routes--controllers)
7. [Mobile API](#7-mobile-api)
8. [Frontend](#8-frontend)
9. [Notifications](#9-notifications)
10. [File Attachments](#10-file-attachments)
11. [Permissions](#11-permissions)
12. [Infrastructure & Ops](#12-infrastructure--ops)
13. [Known Gaps](#13-known-gaps)

---

## 1. Feature Overview

Group chat gives every tenant a WhatsApp-like internal messaging space. Key capabilities:

- **Group rooms** — tenant admins create named rooms and manage membership
- **General room** — auto-created per tenant (`is_system=true`); all users with `owner`, `TenantAdmin`, `ExternalAccountant`, or `CAManager` roles are auto-added on join
- **System notifications room** — auto-created per tenant; receives events from invoices, Tally sync, invitations
- **Real-time messaging** — via Laravel Reverb (WebSocket) + Laravel Echo on the frontend
- **Reactions** — emoji toggle reactions on any message
- **Replies** — quote-reply to any previous message
- **Read receipts** — per-user high-water mark tracking; double-tick UI
- **Typing indicators** — debounced, bypass the queue for low latency
- **File attachments** — two-step upload (file first, message second); up to 5 files × 20 MB each
- **Browser push notifications** — VAPID Web Push via `minishlink/web-push`
- **Mobile API** — 14-endpoint REST API + dedicated Pusher auth endpoint for native apps
- **Online presence** — live member list (who is in the room right now) from Pusher presence channels

---

## 2. Architecture

```
Browser / Mobile App
        │
        ├── HTTPS (Inertia/API)  ──▶  nginx  ──▶  PHP-FPM (Laravel)
        │                                              │
        └── WSS  ──▶  nginx /app proxy  ──▶  Reverb:8080 (supervisor: accobot-reverb)
                                                       │
                                         Laravel Queue (database driver)
                                         supervisor: accobot-worker (2 procs)
```

**Why Reverb + database queue**: Zero additional infrastructure — no Redis needed. Typing events use `ShouldBroadcastNow` to skip the queue entirely; message/reaction events are queued and delivered within ~50–200 ms.

**Web vs. AI chat**: The AI assistant (`ChatController`) is a separate, stateless HTTP feature. It shares only `AuthenticatedLayout` and the permission middleware with group chat.

---

## 3. Database Schema

All tables use UUID primary keys (`HasUuids`). All tenant-scoped tables include `tenant_id uuid not null` with cascade delete.

### `chat_rooms`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `name` | varchar(255) | |
| `description` | text nullable | |
| `type` | varchar(20) | `group` or `notifications` |
| `is_system` | boolean | `true` for auto-created Notifications room |
| `created_by_user_id` | bigint nullable FK users | SET NULL on user delete |
| `deleted_at` | timestamp nullable | Soft deletes |

Indexes: `(tenant_id)`, `(tenant_id, type)`

### `chat_room_members`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `chat_room_id` | uuid FK chat_rooms | |
| `user_id` | bigint FK users | |
| `role` | varchar(20) | `admin` or `member` |
| `joined_at` | timestamp | Default `now()` |
| `last_read_message_id` | uuid nullable | Application-level FK; no DB constraint (circular dep) |

Unique: `(chat_room_id, user_id)`

### `chat_messages`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `chat_room_id` | uuid FK chat_rooms | |
| `user_id` | bigint nullable FK users | null for system messages |
| `body` | text nullable | null for attachment-only messages; wiped on soft delete |
| `type` | varchar(20) | `text`, `system`, `attachment` |
| `metadata` | jsonb nullable | Extra data for system events |
| `reply_to_message_id` | uuid nullable | Self-FK, SET NULL |
| `edited_at` | timestamp nullable | |
| `deleted_at` | timestamp nullable | Soft deletes |

Indexes: `(tenant_id)`, `(chat_room_id, created_at DESC)` (primary query index), `(user_id)`

### `message_reactions`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `chat_message_id` | uuid FK chat_messages CASCADE | |
| `user_id` | bigint FK users | |
| `emoji` | varchar(10) | e.g. `👍` |

Unique: `(chat_message_id, user_id, emoji)` — prevents duplicates

### `message_reads`

High-water mark pattern: one row per user per room, upserted on each read event.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `chat_room_id` | uuid FK chat_rooms | |
| `user_id` | bigint FK users | |
| `last_read_message_id` | uuid | Application-level FK |
| `read_at` | timestamp | |

Unique: `(chat_room_id, user_id)`

### `chat_attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `tenant_id` | uuid FK tenants | |
| `chat_message_id` | uuid nullable FK chat_messages | null until message is sent (two-step upload) |
| `user_id` | bigint FK users | Uploader |
| `disk` | varchar(20) | `local` |
| `path` | varchar(1000) | `chat/{tenant_id}/{Y_m}/{uuid}.{ext}` |
| `original_filename` | varchar(255) | |
| `mime_type` | varchar(127) | |
| `size_bytes` | bigint | |

Orphan cleanup: `CleanOrphanChatAttachments` artisan command runs daily; deletes records where `chat_message_id IS NULL AND created_at < now() - 1 hour` and removes the file from storage.

### `push_subscriptions`

User-level (not tenant-scoped). One row per browser/device.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `user_id` | bigint FK users | |
| `endpoint` | text | Browser push endpoint |
| `public_key` | varchar(255) | |
| `auth_token` | varchar(255) | Hidden from serialization |
| `user_agent` | varchar(255) nullable | |

Unique: `(user_id, endpoint)`

### `notifications`

Standard Laravel notifications table (generated by `php artisan notifications:table`). Used by `SystemNotification` for the `database` channel.

---

## 4. Models

All models: `app/Models/`.

| Model | File | Traits | Scope |
|---|---|---|---|
| `ChatRoom` | `ChatRoom.php` | `HasUuids`, `BelongsToTenant`, `SoftDeletes` | Tenant |
| `ChatRoomMember` | `ChatRoomMember.php` | `HasUuids`, `BelongsToTenant` | Tenant |
| `ChatMessage` | `ChatMessage.php` | `HasUuids`, `BelongsToTenant`, `SoftDeletes` | Tenant |
| `MessageReaction` | `MessageReaction.php` | `HasUuids`, `BelongsToTenant` | Tenant |
| `MessageRead` | `MessageRead.php` | `HasUuids`, `BelongsToTenant` | Tenant |
| `ChatAttachment` | `ChatAttachment.php` | `HasUuids`, `BelongsToTenant` | Tenant |
| `PushSubscription` | `PushSubscription.php` | `HasUuids` | User (not tenant) |

### Key relationships

**`ChatRoom`**
- `members()` → `HasMany(ChatRoomMember)`
- `users()` → `BelongsToMany(User)` via `chat_room_members`, pivot: `role, joined_at`
- `messages()` → `HasMany(ChatMessage)` ordered by `created_at ASC`
- `latestMessage()` → `HasOne(ChatMessage)->latestOfMany('created_at')`
- Scopes: `scopeForUser(userId)`, `scopeGroup()`, `scopeSystem()`
- Static: `ChatRoom::notificationsChannelForTenant(tenantId)` — finds or creates the system Notifications room

**`ChatMessage`**
- `sender()` → `BelongsTo(User, 'user_id')`
- `reactions()` → `HasMany(MessageReaction)`
- `attachments()` → `HasMany(ChatAttachment)`
- `replyTo()` → `BelongsTo(ChatMessage, 'reply_to_message_id')`
- Appended accessor `reaction_summary`: groups reactions by emoji with count and user IDs

**`ChatAttachment`**
- `getSignedUrlAttribute()` → `Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30))`

**`User` additions**
- `chatRooms()` → `BelongsToMany(ChatRoom)` via `chat_room_members`
- `pushSubscriptions()` → `HasMany(PushSubscription)`

**`Tenant` additions**
- `chatRooms()` → `HasMany(ChatRoom)`

---

## 5. Broadcasting (Real-Time)

**Server**: Laravel Reverb (`laravel/reverb ^1.4`)  
**Client**: Laravel Echo + `pusher-js` (web), Pusher native SDK (mobile)

### Channels (`routes/channels.php`)

| Channel | Type | Purpose | Authorization |
|---|---|---|---|
| `presence-room.{tenantId}.{roomId}` | Presence | Per-room real-time events + online presence | User must be a tenant member AND a room member |
| `private-user.{userId}` | Private | Per-user system notifications | `auth()->id() === userId` |
| `private-tenant.{tenantId}.notifications` | Private | Tenant-wide system notifications | User must be a tenant member |

### Events (`app/Events/`)

| Class | Queue | Channel | Event name | Payload highlights |
|---|---|---|---|---|
| `BroadcastChatMessage` | Yes (`$afterCommit = true`) | Presence room | `.chat.message` | `id, chat_room_id, user_id, sender_name, body, type, metadata, attachments, reactions, created_at` |
| `BroadcastTyping` | **No** (`ShouldBroadcastNow`) | Presence room | `.chat.typing` | `user_id, user_name, typing (bool)` |
| `BroadcastReaction` | Yes | Presence room | `.chat.reaction` | `message_id, emoji, user_id, action ('added'/'removed')` |
| `BroadcastReadReceipt` | **No** (`ShouldBroadcastNow`) | Presence room | `.chat.read` | `user_id, last_read_message_id` |
| `BroadcastSystemNotification` | Yes | Tenant notifications + user private | `.system.notification` | Arbitrary payload |

### Frontend Echo setup (`resources/js/bootstrap.js`)

```js
window.Echo = new Echo({
    broadcaster: 'reverb',
    key:     import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:  import.meta.env.VITE_REVERB_HOST,
    wsPort:  import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
});
```

---

## 6. Web Routes & Controllers

Base prefix: `/t/{tenant}` · Middleware: `auth, verified, member`

### Room management — `ChatRoomController`

| Method | URL | Name | Permission |
|---|---|---|---|
| `GET` | `/groups` | `chat.groups.index` | `chat.room.view` |
| `POST` | `/groups` | `chat.groups.store` | `chat.room.create` |
| `GET` | `/groups/{room}` | `chat.groups.show` | `chat.room.view` |
| `PATCH` | `/groups/{room}` | `chat.groups.update` | `chat.room.manage` |
| `DELETE` | `/groups/{room}` | `chat.groups.destroy` | `chat.room.manage` |
| `POST` | `/groups/{room}/members` | `chat.groups.members.store` | `chat.room.manage` |
| `DELETE` | `/groups/{room}/members/{user}` | `chat.groups.members.destroy` | `chat.room.manage` |

**`show`** returns last 50 messages with `sender`, `attachments`, `reactions`, `replyTo.sender` eager-loaded. Attachment records are annotated with a signed `download_url` before being passed to Inertia.

**`store` / `destroy`**: Guards prevent renaming or deleting `is_system` rooms.

### Messages — `ChatMessageController`

| Method | URL | Name | Permission |
|---|---|---|---|
| `GET` | `/groups/{room}/messages` | `chat.messages.index` | `chat.message.send` |
| `POST` | `/groups/{room}/messages` | `chat.messages.store` | `chat.message.send` |
| `DELETE` | `/groups/{room}/messages/{message}` | `chat.messages.destroy` | `chat.message.send` |
| `POST` | `/groups/{room}/typing` | `chat.typing` | `chat.message.send` |
| `POST` | `/groups/{room}/read` | `chat.read` | `chat.message.send` |

**`index`**: Cursor pagination — `?before_id={uuid}` returns 50 messages older than that ID (JSON response, not Inertia).

**`store`**: Accepts `body (nullable)`, `reply_to_message_id (nullable uuid)`, `attachment_ids (array, max 5)`. At least one of `body` or `attachment_ids` required. Links orphan attachments to the new message. Fires `BroadcastChatMessage`.

**`destroy`**: Soft-deletes and wipes `body = null`. Only the sender or a user with `chat.message.delete` can delete. Fires `BroadcastChatMessage` with deleted state.

**`markRead`**: Upserts `MessageRead` and updates `ChatRoomMember.last_read_message_id`. Fires `BroadcastReadReceipt`.

### Reactions — `ChatReactionController`

| Method | URL | Name | Permission |
|---|---|---|---|
| `POST` | `/groups/{room}/messages/{message}/reactions` | `chat.reactions.toggle` | `chat.message.send` |

Toggle: if reaction `(message, user, emoji)` exists → delete it (`action = removed`); otherwise create it (`action = added`). Fires `BroadcastReaction`.

### Attachments — `ChatAttachmentController`

| Method | URL | Name | Permission |
|---|---|---|---|
| `POST` | `/groups/{room}/attachments` | `chat.attachments.store` | `chat.message.send` |
| `GET` | `/groups/{room}/attachments/{attachment}/download` | `chat.attachments.download` | `chat.message.send` |

**`store`**: Validates MIME via `mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv` (reads actual file magic bytes, not Content-Type header). Stores at `chat/{tenant_id}/{Y_m}/{uuid}.{ext}` on `local` disk. Returns attachment metadata + signed URL. `chat_message_id` is null until the message is sent.

**`download`**: Streams the file via `Storage::disk('local')->download(...)`. Membership in the room is required.

### Push subscriptions — `PushSubscriptionController`

Routes outside the tenant prefix (user-level):

| Method | URL | Name |
|---|---|---|
| `POST` | `/push/subscribe` | `push.subscribe` |
| `DELETE` | `/push/subscribe` | `push.unsubscribe` |

---

## 7. Mobile API

**Controller**: `app/Http/Controllers/Api/MobileGroupChatController.php`  
**Base prefix**: `/api/mobile/tenants/{tenant}/groups`  
**Auth**: `auth:sanctum` + `member` middleware  
**Full reference**: see `docs/api-mobile.md` (Group Chat section)

### Endpoints

| Method | Endpoint | Controller method |
|---|---|---|
| `GET` | `/groups` | `rooms` |
| `POST` | `/groups` | `createRoom` |
| `GET` | `/groups/{room}` | `showRoom` |
| `GET` | `/groups/{room}/messages` | `messages` |
| `POST` | `/groups/{room}/messages` | `sendMessage` |
| `DELETE` | `/groups/{room}/messages/{message}` | `deleteMessage` |
| `POST` | `/groups/{room}/messages/{message}/reactions` | `toggleReaction` |
| `POST` | `/groups/{room}/read` | `markRead` |
| `POST` | `/groups/{room}/typing` | `typing` |
| `POST` | `/groups/{room}/attachments` | `uploadAttachment` |
| `GET` | `/groups/{room}/attachments/{attachment}` | `downloadAttachment` |
| `POST` | `/groups/{room}/members` | `addMember` |
| `DELETE` | `/groups/{room}/members/{user}` | `removeMember` |

All responses are JSON (no Inertia). Logic mirrors the web controllers exactly.

### Mobile WebSocket auth

The default `/broadcasting/auth` route uses session middleware and rejects Bearer tokens. A dedicated endpoint exists:

```
POST /api/mobile/broadcasting/auth
Authorization: Bearer <sanctum-token>
```

The mobile Pusher SDK must set its `authEndpoint` to this URL. Middleware: `auth:sanctum`.

### Mobile channel subscriptions

```
presence-room.{tenantId}.{roomId}  — chat events + presence
private-user.{userId}              — system notifications
```

Events to handle: `.chat.message`, `.chat.typing`, `.chat.reaction`, `.chat.read`, `.system.notification`

---

## 8. Frontend

### Pages

| File | Route | Purpose |
|---|---|---|
| `resources/js/Pages/Chat/Groups/Index.vue` | `chat.groups.index` | Room list with unread counts, search filter, create modal |
| `resources/js/Pages/Chat/Groups/Show.vue` | `chat.groups.show` | Full chat view — messages, input, members sidebar |

**`Show.vue` lifecycle**:
- `onMounted`: Joins Echo presence channel, binds `.chat.message / .chat.typing / .chat.reaction / .chat.read`, scrolls to bottom, marks latest message read
- `onUnmounted`: Leaves Echo channel

**Older message loading**: "Load earlier" button at the top of the message list fetches via axios `GET /groups/{room}/messages?before_id={uuid}` and prepends results (does not trigger Inertia navigation).

**Responsive layout**: Mobile drawer for room list (left panel) and members sidebar (right panel). Wide screens show both panels inline.

### Components (`resources/js/Components/Chat/`)

| Component | Purpose |
|---|---|
| `MessageBubble.vue` | Renders a single message — own (right, violet) vs. received (left, gray). Shows sender name, reply quote, attachments, reaction bar, read receipt ticks |
| `MessageInput.vue` | Textarea (Enter = send, Shift+Enter = newline), attachment picker, pending attachment previews, reply bar, typing events (debounced 1 s, stops after 3 s silence) |
| `ReactionPicker.vue` | Emoji reaction bubbles below messages; `+` button opens hardcoded emoji grid (no external library) |
| `AttachmentPreview.vue` | Image thumbnail or file icon + name; click triggers signed download URL |
| `MembersSidebar.vue` | Member list with green online dot (from presence channel `here/joining/leaving`) and role badge |
| `TypingIndicator.vue` | "Alice is typing..." with animated dots; hidden when no one is typing |
| `ReadReceiptDisplay.vue` | Single tick (sent) / double tick (at least one member read) for own messages; avatar stack on hover |

### Message display features

- **Message grouping**: Consecutive messages from the same user within 5 minutes are visually grouped (no repeated avatar/name)
- **Date separators**: "Today", "Yesterday", full date — rendered as centered pills between messages
- **System messages**: Centered pill style (e.g. "Invoice #INV-001 marked as paid")
- **Sender avatars**: Shown on received messages (not own)

### Browser push (`usePushNotifications` composable)

Called from `AuthenticatedLayout.vue` `onMounted`. Requests notification permission, subscribes via `pushManager.subscribe()`, sends keys to `POST /push/subscribe`. A service worker at `public/sw.js` handles incoming push events and shows native browser notifications.

### Navigation

In `AuthenticatedLayout.vue`, a **Groups** NavLink is shown when the user has `chat.room.view` permission:

```vue
<NavLink :href="route('chat.groups.index', { tenant: currentTenantId() })"
         :active="route().current('chat.groups.*')">Groups</NavLink>
```

---

## 9. Notifications

### `SystemNotification` (`app/Notifications/SystemNotification.php`)

Implements `ShouldQueue`. Channels: `database` (stored in `notifications` table) + `broadcast` (fires `BroadcastSystemNotification`) + `web_push` (via `WebPushChannel`).

**Constructor params**: `tenantId, title, body, eventType, data[], ?targetUserId`

### `WebPushChannel` (`app/Notifications/Channels/WebPushChannel.php`)

Uses `minishlink/web-push`. Iterates the notifiable's `pushSubscriptions` and delivers each one. Expired subscriptions (reported by the push service) are automatically deleted.

VAPID config lives in `config/services.php` under the `vapid` key.

### `ChatNotificationService` (`app/Services/ChatNotificationService.php`)

Static helper used by controllers to fire a system notification + insert a system message into the tenant Notifications room in one call. Hook points:

| Controller | Method | Event type | Group rooms |
|---|---|---|---|
| `InvoiceController` | `store()` | `invoice.created` | ✓ |
| `InvoiceController` | `update()` when status → `paid` | `invoice.paid` | ✓ |
| `CreateInvoiceTool` | `handle()` (AI agent) | `invoice.created` | ✓ |
| `TallySyncController` | `trigger()` | `tally.sync.started` | |
| Tally inbound job | `handle()` on finish | `tally.sync.completed` | |
| `TeamMemberController` | `store()` | `member.added` | |
| `InvitationController` | `accept()` / `acceptById()` | `member.joined` | |

The `postToGroupRooms: true` flag causes `notify()` to also insert the same system message into every `type = 'group'` chat room for the tenant and broadcast it over each room's presence channel. Use it for background/automated events (e.g. Tally sync) that have no specific user actor.

For user-triggered actions (invoice creation, etc.), use `ChatNotificationService::postAsUser(tenantId, userId, body, metadata)` instead — this posts a `type=text` message as the real user so the bubble appears under their name and avatar. Pass `download_url` in `metadata` to render a "View Invoice →" download button inside the bubble.

### Auto-create system rooms

Every new `Tenant` gets two system rooms created via the `Tenant::created` hook in `AppServiceProvider`:

| Room | `type` | `is_system` | Auto-members |
|---|---|---|---|
| Notifications | `notifications` | `true` | None (receives broadcast events) |
| General | `group` | `true` | Users with `owner`, `TenantAdmin`, `ExternalAccountant`, `CAManager` roles |

The General room is protected (cannot be renamed or deleted). Members are added automatically via `ChatRoom::addToGeneralIfQualified()` at every join entry point: `User::createPersonalTenant()`, `TeamMemberController::store()`, `InvitationController::accept()`, `InvitationController::acceptById()`.

---

## 10. File Attachments

**Storage disk**: `local` (private, served by Laravel — never publicly accessible)  
**Path pattern**: `chat/{tenant_id}/{Y_m}/{uuid}.{ext}`  
**Max size**: 20 MB per file · **Max per message**: 5 files  
**Allowed types**: `jpg/jpeg/png/gif/webp/pdf/doc/docx/xls/xlsx/txt/csv`

MIME validation uses Laravel's `mimes:` rule which reads actual file magic bytes (not the client-supplied Content-Type).

**Two-step upload flow**:
1. User picks a file → `POST /groups/{room}/attachments` → file stored, `ChatAttachment` row created with `chat_message_id = null`
2. User sends the message → `POST /groups/{room}/messages` with `attachment_ids[]` → `ChatAttachment` rows are linked to the new `ChatMessage`

**Serving**: `GET /groups/{room}/attachments/{attachment}/download` — membership check enforced; file streamed via `Storage::disk('local')->download(...)`.

**Orphan cleanup**: `php artisan chat:clean-orphan-attachments` — scheduled daily. Deletes `ChatAttachment` rows with no message link older than 1 hour and removes files from disk.

---

## 11. Permissions

### Permission definitions

| Permission | Who gets it | What it gates |
|---|---|---|
| `chat.room.view` | All roles except `IntegrationUser` | Groups nav link, room list |
| `chat.room.create` | admin, owner, TenantAdmin, Manager, OwnerPartner | Create new group rooms |
| `chat.room.manage` | admin, owner, TenantAdmin, OwnerPartner | Rename/delete rooms, add/remove members |
| `chat.message.send` | All except Viewer, Auditor, ExternalAccountant, IntegrationUser | Send messages, react, mark read, type indicator |
| `chat.message.delete` | admin, owner, TenantAdmin, Manager, OwnerPartner | Delete any message (not just own) |

Seeded in `database/seeders/RolesAndPermissionsSeeder.php`.  
Permission group registered in `config/permission_groups.php` under `"Group Chat"` — appears automatically in the Dashboard "Your Access" card.

---

## 12. Infrastructure & Ops

### Supervisor processes

**`/etc/supervisor/conf.d/accobot-reverb.conf`**

```ini
[program:accobot-reverb]
command=php /path/to/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/supervisor/accobot-reverb.log
```

Queue workers already run as `accobot-worker` (2 procs, existing config).

### Nginx WebSocket proxy

```nginx
location /app {
    proxy_pass             http://127.0.0.1:8080;
    proxy_http_version     1.1;
    proxy_set_header       Upgrade $http_upgrade;
    proxy_set_header       Connection "upgrade";
    proxy_set_header       Host $host;
    proxy_read_timeout     60s;
}
```

TLS is terminated by nginx. Reverb runs HTTP internally (`REVERB_SCHEME=http`).

### Environment variables

| Variable | Production value | Notes |
|---|---|---|
| `BROADCAST_CONNECTION` | `reverb` | |
| `REVERB_APP_ID` | `accobot-prod` | Arbitrary identifier |
| `REVERB_APP_KEY` | `<key>` | Public — also set as `VITE_REVERB_APP_KEY` |
| `REVERB_APP_SECRET` | `<secret>` | Keep secret |
| `REVERB_HOST` | `0.0.0.0` | Reverb listens on all interfaces |
| `REVERB_PORT` | `8080` | Internal port (nginx proxies) |
| `REVERB_SCHEME` | `http` | TLS handled by nginx |
| `VITE_REVERB_HOST` | `accobot.eightsis.com` | **Must be the public domain** |
| `VITE_REVERB_PORT` | `443` | Public SSL port |
| `VITE_REVERB_SCHEME` | `https` | |
| `VAPID_SUBJECT` | `mailto:...` | Valid email or URL |
| `VAPID_PUBLIC_KEY` | `<key>` | Also set as `VITE_VAPID_PUBLIC_KEY` |
| `VAPID_PRIVATE_KEY` | `<secret>` | Changing this invalidates all push subscriptions |

### Build workflow (important)

Frontend assets are committed to git (`public/build` is **not** gitignored). All builds happen **locally** — never on the server.

A `.env.production` file (gitignored, dev machine only) overrides `VITE_*` vars for production builds:

```
VITE_REVERB_APP_KEY=<key>
VITE_REVERB_HOST=accobot.eightsis.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_VAPID_PUBLIC_KEY=<key>
```

Without this file, `VITE_REVERB_HOST` falls back to `localhost` from `REVERB_HOST`, causing browsers to attempt `wss://localhost:8080` in production.

### Deployment checklist (first-time setup)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # locally, with .env.production present
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start accobot-reverb:*
# Verify: curl -I http://localhost:8080/app
```

### Routine deployment (code push)

```bash
# local
npm run build                    # with .env.production
git add public/build && git commit -m "build assets"
git push

# server
git pull
php artisan migrate
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart accobot-reverb:*
sudo supervisorctl restart accobot-worker:*
```

---

## 13. Known Gaps

### Phase 12 — Native mobile push notifications (not implemented)

The current backend uses VAPID Web Push (`minishlink/web-push`), which only reaches browsers. Mobile apps receive system notifications only while the WebSocket connection is open (foreground). Background push requires:

1. `laravel-notification-channels/fcm` for Android FCM
2. `laravel-notification-channels/apn` for iOS APNs
3. `device_push_tokens` table: `user_id, platform (ios|android), token` (unique per token)
4. `DevicePushToken` model + API endpoints:
   - `POST /api/mobile/push/register-token` (on login)
   - `DELETE /api/mobile/push/register-token` (on logout)
5. Add FCM/APNs channels to `SystemNotification::via()`

See `docs/api-mobile.md` — Mobile Developer Implementation Guide, step 14 for the full gap description.

---

*Last updated: 2026-04-28. For the original implementation plan and rationale, see `docs/group-chat-plan.md`.*