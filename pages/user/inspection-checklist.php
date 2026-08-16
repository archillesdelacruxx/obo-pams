<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('inspection-checklist');
$pdo = getDB();
$perms = getUserModulePermissions();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>On-Site Ocular Inspection Checklist · PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260816c">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260817d">
</head>
<body data-page="inspection-checklist" data-is-admin="<?php echo empty($_SESSION['is_admin']) ? '0' : '1'; ?>" data-permissions='<?php echo json_encode(array_keys(array_filter($perms))); ?>'>
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('inspection-checklist'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Inspection Checklist'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Inspection Management</span><span class="sep">/</span><span class="current">On-Site Ocular Inspection Checklist</span></div>
        <div class="page-head">
          <div><h1>On-Site Ocular Inspection Checklist</h1><p class="subtitle">Record results of the on-site inspection against standard checklist items.</p></div>
          <a href="inspection-reports.php" class="btn btn-secondary"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back to Reports</a>
        </div>

        <div class="section-card form-card" id="inschProjectCard">
          <div class="section-head"><h3>Project Information</h3><span class="badge badge-neutral" id="inschStatusPill"></span></div>
          <div class="section-body">
            <form id="inschForm">
              <input type="hidden" id="inschId" value="">
              <input type="hidden" id="inschScheduleId" value="">
              <input type="hidden" id="inschInspectionNo" value="">
              <div class="form-grid">
                <input type="hidden" id="inschAppNo" value="">
                <div class="form-group"><label>Team Leader 1</label><div class="tl-select t1"><div class="tl-team-chip">1</div><select id="inschTeamLeader1"><option value="">Select team leader</option></select><svg class="tl-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></div></div>
                <div class="form-group"><label>Team Leader 2</label><div class="tl-select t2"><div class="tl-team-chip">2</div><select id="inschTeamLeader2"><option value="">Select team leader</option></select><svg class="tl-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></div></div>
                <div class="form-group"><label>Date Inspected <span class="req">*</span></label><input class="form-control" type="date" id="inschDate"></div>
                <div class="form-group">
                  <label>Inspection Type</label>
                  <div class="insp-type-pills">
                    <label class="radio-pill insp-pill"><input type="radio" name="inschInspCount" value="1st Inspection"><span>1st</span></label>
                    <label class="radio-pill insp-pill"><input type="radio" name="inschInspCount" value="2nd Inspection"><span>2nd</span></label>
                    <label class="radio-pill insp-pill"><input type="radio" name="inschInspCount" value="3rd Inspection"><span>3rd</span></label>
                    <label class="radio-pill insp-pill insp-others-pill"><input type="radio" name="inschInspCount" value="others"><span>Others</span></label>
                  </div>
                  <input class="form-control insp-others-input" type="text" id="inschInspectionType" placeholder="e.g. Final / Re-inspection / 4th" style="display:none;">
                </div>
                <div class="form-group"><label>Building Permit No.</label><input class="form-control form-mono" type="text" id="inschPermitNo" placeholder="e.g. BP-2026-001"></div>
                <div class="form-group"><label>Date Re-inspected</label><input class="form-control" type="date" id="inschDateIssued"></div>
                <div class="form-group"><label>Project Title <span class="req">*</span></label><input class="form-control" type="text" id="inschProjectTitle" placeholder="Project title"></div>
                <div class="form-group"><label>Physical accomplishment (%)</label><input class="form-control" type="number" id="inschPhysical" min="0" max="100" placeholder="e.g. 75"></div>
                <div class="form-group"><label>Owner / representative</label><input class="form-control" type="text" id="inschOwner" placeholder="Owner or representative"></div>
                <div class="form-group"><label>Contact number</label><input class="form-control form-mono" type="text" id="inschContact" placeholder="e.g. 0917-000-0000"></div>
                <div class="form-group"><label>Contractor</label><input class="form-control" type="text" id="inschContractor" placeholder="Contractor"></div>
                <div class="form-group"><label>Project Engineer</label><input class="form-control" type="text" id="inschEngineer" placeholder="Project engineer"></div>
                <div class="form-group"><label>Location</label><input class="form-control" type="text" id="inschLocation" placeholder="Site address"></div>
                <div class="form-group"><label>Time Start</label><input class="form-control" type="time" id="inschTimeStart"></div>
                <div class="form-group"><label>Time Finished</label><input class="form-control" type="time" id="inschTimeEnd"></div>
                <div class="form-group full"><label>Overall Findings</label><textarea class="form-control" id="inschFindings" rows="3" placeholder="Summary of findings"></textarea></div>
                <div class="form-group full"><label>Recommendations</label><textarea class="form-control" id="inschRecommendations" rows="3" placeholder="Recommended actions / compliance items"></textarea></div>
                <div class="form-group full">
                  <label>Inspection Result</label>
                  <div class="insp-result-pills">
                    <label class="radio-pill"><input type="radio" name="inschResult" value="Passed"><span>Passed</span></label>
                    <label class="radio-pill"><input type="radio" name="inschResult" value="Passed with Remarks"><span>Passed with Remarks</span></label>
                    <label class="radio-pill"><input type="radio" name="inschResult" value="Ongoing"><span>Ongoing</span></label>
                    <label class="radio-pill"><input type="radio" name="inschResult" value="Failed"><span>Failed</span></label>
                    <label class="radio-pill"><input type="radio" name="inschResult" value="For Re-inspection"><span>For Re-inspection</span></label>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="section-card form-card" id="inschResultsCard">
          <div class="section-head"><h3>Checklist Results</h3><span class="text-xs text-muted" id="inschResultsSummary"></span></div>
          <div class="section-body" id="inschResultsBody">
            <div class="section-hint">Loading standard checklist items…</div>
          </div>
        </div>

        <div class="insch-split">
          <div class="section-card form-card" id="inschPhotosCard">
            <div class="section-head"><h3>Site Photos</h3></div>
            <div class="section-body">
              <div class="photo-drop" id="inschPhotoDrop">
                <input type="file" id="inschPhotoInput" accept="image/*" multiple hidden>
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                <p>Click to attach site photos <span class="text-xs text-muted">(JPG, PNG, GIF, WEBP · up to 8 MB each)</span></p>
              </div>
              <div class="photo-grid" id="inschPhotoList"></div>
            </div>
          </div>
        </div>

        <div class="section-card form-card" id="inschReviewCard" style="display:none;">
          <div class="section-head"><h3 id="inschReviewTitle">Review</h3></div>
          <div class="section-body">
            <div class="form-grid">
              <div class="form-group full" id="inschReviewRemarksWrap"><label>Rejection Remarks <span class="req">*</span></label><textarea class="form-control" id="inschReviewRemarks" rows="2" placeholder="Required when rejecting this inspection"></textarea></div>
            </div>
            <div class="flex gap-sm" style="margin-top:14px;">
              <button type="button" class="btn btn-success" id="inschApproveBtn" style="display:none;">Approve</button>
              <button type="button" class="btn btn-danger" id="inschRejectBtn" style="display:none;">Reject</button>
            </div>
          </div>
        </div>

        <div class="flex gap-sm" style="margin-top:8px;">
          <button type="button" class="btn btn-primary" id="inschSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Draft</button>
          <button type="button" class="btn btn-primary-outline" id="inschSubmitBtn" style="display:none;">Submit for Review</button>
        </div>
      </main>
    </div>
  </div>
  <script src="../../assets/js/utilities.js"></script>
  <script src="../../assets/js/api.js"></script>
  <script src="../../assets/js/validation.js"></script>
  <script src="../../assets/js/sidebar.js"></script>
  <script src="../../assets/js/dropdown.js"></script>
  <script src="../../assets/js/notification.js"></script>
  <script src="../../assets/js/modal.js"></script>
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260819a"></script>
</body>
</html>
