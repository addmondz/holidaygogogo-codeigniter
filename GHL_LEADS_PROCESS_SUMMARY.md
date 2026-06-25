# GHL Leads — Process, Logic & Fixes (Summary)

Short reference for how GHL data becomes the lead dashboard, the business rules,
the bugs that were fixed, and how to verify it.

## 1. The pipeline

Data flows through three stages, each its own command and table:

| Stage | Command | Writes | Depends on |
| --- | --- | --- | --- |
| Leads | `process_ghl_leads` | `ghl_processed_leads` | synced `ghl_messages` |
| Conversions | `process_ghl_lead_conversions` | updates `ghl_processed_leads` | leads + `booking` |
| Ownership | `process_ghl_lead_ownership` | `ghl_lead_ownership` | leads + conversions + `ghl_messages` + assignment history |

Run order always: **leads → conversions → ownership** (each needs the previous).

### Initial / full rebuild
```
php index.php Cron process_ghl_leads --rebuild
php index.php Cron process_ghl_lead_conversions
php index.php Cron process_ghl_lead_ownership rebuild
```

### Recurring (incremental "continue")
```
php index.php Cron process_ghl_leads
php index.php Cron process_ghl_lead_conversions
php index.php Cron process_ghl_lead_ownership
```

### Schedule
Driven by `Cron::syncGhlModules()`, every 20 minutes:
- **:00 / :20 / :40** — sync users, contacts, conversations, messages.
- **:10 / :30 / :50** — process leads → conversions → ownership.

## 2. Lead logic

One row in `ghl_processed_leads` = one sales opportunity. A conversation splits
into multiple leads when:
1. The first inbound message starts a lead.
2. A converted lead receives a new inbound message **after** the booking time →
   new lead (repeat customer, new trip).
3. With no conversion, 90 days of inactivity → new lead.

Natural key (unique): `conversation_id + lead_started_at + first_customer_message_id`.

## 3. Ownership logic — two lead types

Each lead can have multiple owners in `ghl_lead_ownership`
(unique per `processed_lead_id + owner_user_id`). An owner qualifies as:
- **Assigned owner** — the agent the conversation is assigned to.
- **Reply owner** — a non-assigned agent who sent **more than 2** outbound replies
  (i.e. 3 or more) in the lead's window.

### Dashboard counting (no double counting)

The dashboard applies this rule at query time:

```sql
assigned_lead = SUM(is_assigned_owner = 1)
reply_lead    = SUM(is_assigned_owner = 0 AND is_reply_owner = 1)
```

| Your relationship to the lead | assigned_lead | reply_lead |
| --- | :---: | :---: |
| Assigned to you (even if you replied many times) | 1 | 0 |
| Assigned to someone else, you replied **> 2** times (3+) | 0 | 1 |
| Assigned to someone else, you replied **≤ 2** times | 0 | 0 |

The assigned owner is **never** also counted as a reply owner — that is the
intended de-duplication.

### Reply threshold = more than 2 (3+)
A non-assigned agent becomes a reply owner only with **more than 2** outbound
replies (i.e. 3 or more). This rule is hardcoded in three places that must stay
in sync:
- `Ghl_Lead_Ownership_Model::get_reply_owners_for_leads` — `HAVING COUNT(*) > ?` (threshold 2)
- `Report_Model.php` — two report queries — `HAVING COUNT(*) > 2`

> Note: this threshold is hardcoded in 3 spots. If it changes, update all 3
> (a shared constant would be a good future cleanup).

## 4. Bugs found & fixed

1. **Duplicate "same lead" in ownership (root cause).**
   `replace_conversation_leads()` did DELETE + INSERT, so leads got a new `id`
   every run. Ownership references `processed_lead_id`, so old rows were orphaned
   and the same logical lead appeared under two ids.
   **Fix:** `replace_conversation_leads()` now UPSERTS by natural key — existing
   leads are updated in place and **keep their id**.

2. **Orphaned ownership rows lingered in continue mode.**
   **Fix:** continue mode now prunes ownership rows whose lead no longer exists
   (`prune_orphan_ownership()`), plus a one-time cleanup SQL
   (`application/sql/20260623_Cleanup_Orphan_GHL_Lead_Ownership.sql`).
   Verified on real data: 192 orphans removed, duplicate logical leads 148 → 0.

3. **Rebuild vs continue produced different leads.**
   Rebuild emptied the table before reading conversion state, so the
   convert-and-return split could not fire on rebuild.
   **Fix:** rebuild now snapshots conversion state before the wipe
   (`get_existing_conversion_map_all()`), so rebuild splits identically to
   continue.

## 5. Known limitation (not a duplicate)

A customer who converts and returns **3+ times** can come out with *fewer* leads
under continue than under a full rebuild, depending on chunk boundaries — an
**under-split**, because each trip's split needs one leads→conversions cycle and
an already-covered message is not re-processed. It is corrected by a rebuild.
Continue **never over-counts** (no duplicates). A future fix: after conversions
run, re-flag newly-converted conversations so the next leads pass can chain the
split.

## 6. Tests

Self-contained (in-memory SQLite / arrays), run with `php`:

| Test | Locks |
| --- | --- |
| `GhlProcessedLeadsUpsertStabilityTest.php` | lead id stays stable; update-in-place |
| `GhlLeadOwnershipOrphanPruneTest.php` | orphan ownership pruned; no duplicate owners |
| `GhlRebuildContinueParityTest.php` | rebuild == continue for convert-and-return |
| `GhlContinueChunkedReplayTest.php` | 20k msgs / 5k chunks: no over-split ever |
| `GhlReplyOwnerThresholdTest.php` | reply owner = ≥2 replies; no double counting |

Run all:
```
for t in tests/helpers/Ghl*Test.php; do echo "== $t =="; php "$t" || break; done
```

## 7. Health check (run after a continue cycle)
`application/sql/verify_ghl_continue.sql` — every query should return 0. Catches
orphans, duplicate logical leads, duplicate natural keys, and ownership drift.
