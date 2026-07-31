-- Permit Approval: dedicated Electrical form fields
ALTER TABLE `permit_approvals`
  ADD COLUMN `type_of_occupancy` VARCHAR(255) NULL AFTER `location`;
