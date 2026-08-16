<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('permit-approval-encoding');

$type = isset($_GET['type']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['type'])) : '';
$permitNames = [
    'building'           => 'Building Permit',
    'occupancy'          => 'Occupancy Permit',
    'sign'               => 'Sign Permit',
    'mechanical'         => 'Mechanical Permit',
    'fencing'            => 'Fencing Permit',
    'plumbing'           => 'Plumbing / Sanitary',
    'coe'                => 'COE Certificate of Operation',
    'cfei'               => 'CFEI',
    'electrical'         => 'Electrical Permit',
    'electronics'        => 'Electronics Permit',
    'excavation'         => 'Excavation Permit',
    'demolition'         => 'Demolition Permit',
    'temporary-sidewalk' => 'Temporary Sidewalk Permit'
];
if (!array_key_exists($type, $permitNames)) { header('Location: permit-approval-encoding.php'); exit; }
$permitLabel = $permitNames[$type];
$isBuilding = $type === 'building';
$isElectrical = $type === 'electrical';
$isCfei = $type === 'cfei';
$isCoe = $type === 'coe';
$isOccupancy = $type === 'occupancy';
$isMechanical = $type === 'mechanical';
$isPlumbing = $type === 'plumbing';
$isSign = $type === 'sign';
$isElectronics = $type === 'electronics';
$isFencing = $type === 'fencing';
$isExcavation = $type === 'excavation';
$isDemolition = $type === 'demolition';
$isFencingStyle = $isFencing || $isExcavation || $isDemolition;
$permitNoLabel = 'Fp#';
$appNoLabel = 'FAN#';
$permitNoPh = 'e.g. Fp-2026-001';
$appNoPh = 'Fencing application number';
if ($type === 'excavation') { $permitNoLabel = 'EXCP#'; $appNoLabel = 'EXCAN#'; $permitNoPh = 'e.g. EXCP-2026-001'; $appNoPh = 'Excavation application number'; }
if ($type === 'demolition') { $permitNoLabel = 'DP#'; $appNoLabel = 'DAN#'; $permitNoPh = 'e.g. DP-2026-001'; $appNoPh = 'Demolition application number'; }
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Encode <?php echo $permitLabel; ?> Â· PAMS</title>
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
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260812c">
</head>
<body data-page="permit-encoding-form" data-type="<?php echo $type; ?>">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('permit-approval-encoding'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Permit Approval Encoding'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Permit Approval Encoding</span><span class="sep">/</span><span class="current"><?php echo $permitLabel; ?></span></div>
        <div class="page-head"><div><h1>Encode <?php echo $permitLabel; ?></h1><p class="subtitle">Fill in the details below and save the approved permit record.</p></div></div>

        <div class="section-card">
          <div class="section-head"><h3><?php echo $permitLabel; ?> â€” Encoding Form</h3><a class="btn btn-ghost btn-sm" href="permit-approval-encoding.php"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back to permit selection</a></div>
          <div class="section-body">
            <?php if ($isBuilding): ?>
            <form id="buildingForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>BP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="bBpNo" placeholder="e.g. BP-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="bApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="bLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="bOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>Date Received <span class="req">*</span></label><input class="form-control" type="date" id="bDateReceived"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="bDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="buildingSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="buildingClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isElectrical): ?>
            <form id="electricalForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>EP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eEpNo" placeholder="e.g. EP-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="eApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="eLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="eOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>Elect. Cost <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eCost" placeholder="0.00"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>EA# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eEaNo" placeholder="Electrical application number"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eFees" placeholder="0.00"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="eDatePaid"></div>
                <div class="form-group"><label>E. Charge <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eCharge" placeholder="0.00"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="eReceivedBy" placeholder="Who received the payment"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="eDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="electricalSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="electricalClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isCoe): ?>
            <form id="coeForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>No. <span class="req">*</span></label><input class="form-control form-mono" type="text" id="cNo" placeholder="e.g. COE-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="cApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="cLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>MP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="cMpNo" placeholder="e.g. MP-2026-001"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="cOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>P.M.E <span class="req">*</span></label><input class="form-control" type="text" id="cPme" placeholder="Name of P.M.E."></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="cFees" placeholder="0.00"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="cOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="cDatePaid"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="cReceivedBy" placeholder="Who received the payment"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="cDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="coeSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="coeClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isCfei): ?>
            <form id="cfeiForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>CFEI# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="cfNo" placeholder="e.g. CFEI-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="cfApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="cfLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="cfOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>PEE <span class="req">*</span></label><input class="form-control" type="text" id="cfPee" placeholder="Name of P.E.E."></div>
                <div class="form-group"><label>Incharge <span class="req">*</span></label><input class="form-control" type="text" id="cfIncharge" placeholder="Person in charge"></div>
                <div class="form-group"><label>Date Received <span class="req">*</span></label><input class="form-control" type="date" id="cfDateReceived"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="cfDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="cfeiSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="cfeiClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isOccupancy): ?>
            <form id="occupancyForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>OP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="oOpNo" placeholder="e.g. OP-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="oApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Date Received <span class="req">*</span></label><input class="form-control" type="date" id="oDateReceived"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="oDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="occupancySaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="occupancyClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isMechanical): ?>
            <form id="mechanicalForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>MP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="mMpNo" placeholder="e.g. MP-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="mApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="mLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="mOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>BLDG COST. <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="mBldgCost" placeholder="0.00"></div>
                <div class="form-group"><label>MA# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="mMaNo" placeholder="Mechanical application number"></div>
                <div class="form-group"><label>Incharge <span class="req">*</span></label><input class="form-control" type="text" id="mIncharge" placeholder="Person in charge"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="mOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="mFees" placeholder="0.00"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="mDatePaid"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="mReceivedBy" placeholder="Who received the payment"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="mechanicalSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="mechanicalClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isPlumbing): ?>
            <form id="plumbingForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>PP/SP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="pPlSpNo" placeholder="e.g. PP/SP-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="pApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="pLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="pOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>Bldg Cost <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="pBldgCost" placeholder="0.00"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="pFees" placeholder="0.00"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="pOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>PP/SP# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="pPlSpAppNo" placeholder="Application number"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="pDatePaid"></div>
                <div class="form-group"><label>PP/SP Incharge <span class="req">*</span></label><input class="form-control" type="text" id="pIncharge" placeholder="Person in charge"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="pReceivedBy" placeholder="Who received the payment"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="pDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="plumbingSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="plumbingClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isSign): ?>
            <form id="signForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>Sign Permit# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="sSignNo" placeholder="e.g. SIGN-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="sApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="sLocation" placeholder="Sign / establishment location"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="sFees" placeholder="0.00"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="sOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="sDatePaid"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="sReceivedBy" placeholder="Who received the payment"></div>
                <div class="form-group"><label>Date OOP <span class="req">*</span></label><input class="form-control" type="date" id="sDateOop"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="sDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="signSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="signClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isElectronics): ?>
            <form id="electronicsForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>ECE# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eceNo" placeholder="e.g. ECE-2026-001"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="eceApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="eceLocation" placeholder="Project / building location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="eceOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>Contractor <span class="req">*</span></label><input class="form-control" type="text" id="eceContractor" placeholder="Contractor name"></div>
                <div class="form-group"><label>OThers <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eceOthers" placeholder="0.00"></div>
                <div class="form-group"><label>Surcharge <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eceSurcharge" placeholder="0.00"></div>
                <div class="form-group"><label>Bldg cost <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eceBldgCost" placeholder="0.00"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="eceFees" placeholder="0.00"></div>
                <div class="form-group"><label>ECEA# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eceEceaNo" placeholder="Electronics application number"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="eceOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="eceDatePaid"></div>
                <div class="form-group"><label>Date OOP <span class="req">*</span></label><input class="form-control" type="date" id="eceDateOop"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="eceDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="electronicsSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="electronicsClearBtn">Clear</button>
              </div>
            </form>
            <?php elseif ($isFencingStyle): ?>
            <form id="fencingForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label><?php echo $permitNoLabel; ?> <span class="req">*</span></label><input class="form-control form-mono" type="text" id="fFpNo" placeholder="<?php echo $permitNoPh; ?>"></div>
                <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="fApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Location <span class="req">*</span></label><input class="form-control" type="text" id="fLocation" placeholder="Project / fencing location"></div>
                <div class="form-group"><label>Type of Occupancy <span class="req">*</span></label><input class="form-control" type="text" id="fOccType" placeholder="e.g. Residential, Commercial"></div>
                <div class="form-group"><label>Contractor <span class="req">*</span></label><input class="form-control" type="text" id="fContractor" placeholder="Contractor name"></div>
                <div class="form-group"><label>Land &amp; Others <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="fLandOthers" placeholder="0.00"></div>
                <div class="form-group"><label>Surcharge <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="fSurcharge" placeholder="0.00"></div>
                <div class="form-group"><label>Area <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="fArea" placeholder="sqm"></div>
                <div class="form-group"><label>Cost <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="fCost" placeholder="0.00"></div>
                <div class="form-group"><label>Line &amp; Grade <span class="req">*</span></label><input class="form-control" type="text" id="fLineGrade" placeholder="Line and grade reference"></div>
                <div class="form-group"><label>Fees <span class="req">*</span></label><input class="form-control form-mono" type="number" min="0" step="0.01" id="fFees" placeholder="0.00"></div>
                <div class="form-group"><label><?php echo $appNoLabel; ?> <span class="req">*</span></label><input class="form-control form-mono" type="text" id="fFanNo" placeholder="<?php echo $appNoPh; ?>"></div>
                <div class="form-group"><label>Date Paid <span class="req">*</span></label><input class="form-control" type="date" id="fDatePaid"></div>
                <div class="form-group"><label>OR# <span class="req">*</span></label><input class="form-control form-mono" type="text" id="fOrNo" placeholder="Official receipt number"></div>
                <div class="form-group"><label>Received By <span class="req">*</span></label><input class="form-control" type="text" id="fReceivedBy" placeholder="Who received the payment"></div>
                <div class="form-group"><label>Date Approved <span class="req">*</span></label><input class="form-control" type="date" id="fDateApproved"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="fencingSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="fencingClearBtn">Clear</button>
              </div>
            </form>
            <?php else: ?>
            <form id="genericForm" class="approval-form">
              <div class="form-grid form-grid-3">
                <div class="form-group"><label>Application No. <span class="req">*</span></label><input class="form-control form-mono" type="text" id="gAppNo" placeholder="e.g. APP-2026-001"></div>
                <div class="form-group"><label>Applicant Name <span class="req">*</span></label><input class="form-control" type="text" id="gApplicant" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Permit Type <span class="req">*</span></label><select class="form-control" id="gPermitType"></select></div>
                <div class="form-group"><label>Approval Date <span class="req">*</span></label><input class="form-control" type="date" id="gDate"></div>
                <div class="form-group"><label>TAT (days)</label><input class="form-control form-mono" type="number" min="0" id="gTat" placeholder="0"></div>
              </div>
              <div class="flex gap-sm form-actions">
                <button type="button" class="btn btn-primary" id="gSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Approval</button>
                <button type="button" class="btn btn-secondary" id="gClearBtn">Clear</button>
              </div>
            </form>
            <?php endif; ?>
          </div>
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
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

