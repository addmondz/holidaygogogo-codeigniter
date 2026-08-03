-- Per-admin, per-page view/edit access for the new "Leads/Customer" tab
-- (Customer, Guest List, GHL Leads, Manual Leads). Managed by the OWNER on
-- Leads/Customer > Access Settings (controller Leads_Customer_Access).
--
-- Owner (level 10) always has full view+edit and is NEVER stored here. Every
-- other admin has no access by default (no row = blocked); a row grants view
-- and/or edit for one module. Edit implies view (enforced in the helper).
-- See application/helpers/leads_customer_access_helper.php.
CREATE TABLE `lc_module_access` (
  `LcAccessID` INT AUTO_INCREMENT PRIMARY KEY,
  `AdminID` INT NOT NULL,
  `Module` ENUM('customer','guests','ghl_leads','manual_leads') NOT NULL,
  `CanView` TINYINT(1) NOT NULL DEFAULT 0,
  `CanEdit` TINYINT(1) NOT NULL DEFAULT 0,
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_lc_admin_module` (`AdminID`,`Module`)
);
