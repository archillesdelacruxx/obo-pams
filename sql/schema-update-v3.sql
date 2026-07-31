USE pams_db;

ALTER TABLE permit_approvals
    ADD COLUMN bp_no VARCHAR(50) DEFAULT NULL AFTER application_no,
    ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER applicant_name,
    ADD COLUMN bldg_cost DECIMAL(14,2) DEFAULT NULL AFTER location,
    ADD COLUMN permit_no VARCHAR(50) DEFAULT NULL AFTER bldg_cost,
    ADD COLUMN incharge VARCHAR(150) DEFAULT NULL AFTER permit_no,
    ADD COLUMN or_no VARCHAR(50) DEFAULT NULL AFTER incharge,
    ADD COLUMN fees DECIMAL(14,2) DEFAULT NULL AFTER or_no,
    ADD COLUMN date_paid DATE DEFAULT NULL AFTER fees,
    ADD COLUMN received_by VARCHAR(150) DEFAULT NULL AFTER date_paid,
    ADD COLUMN date_oop DATE DEFAULT NULL AFTER received_by,
    ADD COLUMN date_approved DATE DEFAULT NULL AFTER date_oop;
