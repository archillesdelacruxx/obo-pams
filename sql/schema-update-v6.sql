-- Permit Approval: Building form simplified (Date Received)
ALTER TABLE `permit_approvals`
  ADD COLUMN `date_received` DATE NULL AFTER `date_oop`;
