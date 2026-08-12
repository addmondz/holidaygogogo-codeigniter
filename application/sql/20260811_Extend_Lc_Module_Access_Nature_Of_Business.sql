-- Extend the Leads/Customer access grid to cover the new Nature of Business page,
-- so it is owner-granted per page like the other modules (owner implicit full
-- access, everyone else grant-only).
ALTER TABLE `lc_module_access`
  MODIFY `Module`
    ENUM('customer','guests','ghl_leads','manual_leads','campaign','lead_status','nature_of_business')
    NOT NULL;
