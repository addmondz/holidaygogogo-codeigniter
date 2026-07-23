-- Makes the Guest List "Language" a PER-GUEST field.
--
-- Previously ChatLanguage only lived on booking / customer, so the dashboard's
-- inline Language edit could only be set on the guest who LEADS a booking (a
-- plain team member had nowhere to store it). This column lets any guest carry
-- their own language: the listing shows COALESCE(gl.ChatLanguage, customer,
-- booking), and a leader's edit still syncs down to booking/customer as before.
ALTER TABLE `guest_list`
  ADD COLUMN `ChatLanguage` ENUM('CN','EN','ML') NULL DEFAULT NULL AFTER `Email`;
