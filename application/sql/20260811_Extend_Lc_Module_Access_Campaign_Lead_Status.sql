-- Extend the Leads/Customer access grid to cover the Campaign and Lead Status
-- pages. They used to be gated by hard-coded level checks (Campaign = owner only,
-- Lead Status = owner + team lead); they are now owner-granted per-page like the
-- other four modules (owner implicit full access, everyone else grant-only).
ALTER TABLE `lc_module_access`
  MODIFY `Module`
    ENUM('customer','guests','ghl_leads','manual_leads','campaign','lead_status')
    NOT NULL;
