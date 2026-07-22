# Lead and Lead Ownership Logic and the Difference

## Lead Logic

The lead dashboard is based on rows in `ghl_processed_leads`.

One row in `ghl_processed_leads` represents one sales opportunity, not necessarily one whole GHL conversation forever.

Current split rules:

1. The first inbound customer message in a conversation starts a lead.
2. If that lead has already converted and the customer sends another inbound message after the converted booking time, the new inbound message starts a new lead.
3. If there is no known conversion yet, a new inbound customer message after 90 days of conversation inactivity starts a new lead.

Example:

1. Customer messages in March for Trip A.
2. The March lead converts into a booking.
3. Customer messages again in August in the same GHL conversation for Trip B.
4. The August inbound message becomes a new processed lead because the previous lead was already converted.

This lets repeat customers in the same GHL conversation be counted as separate leads when they return for a new trip.

## Conversion Logic

A processed lead is marked converted when its phone number matches the first booking confirmation created on or after that lead's `lead_started_at`.

Only `BOOKING CONFIRMATION` bookings count as conversion. Quotations and proforma invoices do not convert a lead.

When a conversation is rebuilt, existing conversion data is preserved by this key:

```text
conversation_id + lead_started_at + first_customer_message_id
```

## Lead Dashboard

The lead dashboard counts and filters `ghl_processed_leads`.

This means each split lead is counted separately. If one GHL conversation has a March converted lead and an August new-trip lead, the dashboard can show two leads from that same conversation.

The assigned agent comes from the conversation assignment used when the processed lead is rebuilt.

## Lead Ownership Logic

Lead ownership is based on rows in `ghl_lead_ownership`.

Ownership is separate from the base lead count because one processed lead can have more than one owner.

An owner can be included because:

1. The agent was assigned to the lead during the lead's time window.
2. The agent replied more than the configured outbound reply threshold.

## Outbound Reply Threshold

The outbound reply threshold controls when an agent becomes a reply owner.

Current rule:

```text
If an agent sends more than 2 outbound replies during a lead's time window, that agent becomes a reply owner.
```

This means:

| Agent outbound replies | Reply owner? |
| ---: | --- |
| 1 | No |
| 2 | No |
| 3 | Yes |
| 4 or more | Yes |

Example:

1. A lead is assigned to Agent A.
2. Agent B sends 3 outbound replies to the customer during that lead's time window.
3. Agent A is counted as the assigned owner.
4. Agent B is counted as a reply owner.

Lead ownership is rebuilt from `ghl_processed_leads`, `ghl_messages`, and `ghl_lead_assignment_history`.

## Main Difference

Lead dashboard answers:

```text
How many leads exist, and which of them converted?
```

Lead ownership answers:

```text
Which agents should receive ownership or activity credit for those leads?
```

So one processed lead normally counts once in the lead dashboard, but it can appear under multiple agents in lead ownership if multiple agents qualify as owners.
