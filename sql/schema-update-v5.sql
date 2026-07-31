-- Permit Approval: dedicated Fencing form fields
ALTER TABLE `permit_approvals`
  ADD COLUMN `contractor` VARCHAR(255) NULL AFTER `type_of_occupancy`,
  ADD COLUMN `land_others` DECIMAL(14,2) NULL AFTER `contractor`,
  ADD COLUMN `surcharge` DECIMAL(14,2) NULL AFTER `land_others`,
  ADD COLUMN `area` DECIMAL(14,2) NULL AFTER `surcharge`,
  ADD COLUMN `line_grade` VARCHAR(255) NULL AFTER `area`;
