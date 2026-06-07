-- Adds a Yes/No field capturing whether a non-Malaysian guest is staying in
-- Malaysia with a valid permit/visa.
--
-- Surfaced in the Guest List form ONLY when the guest's nationality is not
-- Malaysia (mirrors the passport-fields visibility rule). Stays NULL for
-- Malaysian guests, who never see the field.

ALTER TABLE `guest_list`
  ADD COLUMN `StayingInMalaysiaWithPermit` ENUM('Yes','No') NULL DEFAULT NULL AFTER `Nationality`;
