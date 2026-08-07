<?php
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireAuth();

$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* Save a base64 data-URL image (signature) into the given uploads subfolder. */
function inspectionSaveSignature(string $dataUrl, string $subdir): string {
    if (!preg_match('#^data:image/(png|jpeg);base64,#i', $dataUrl, $m)) return '';
    $ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
    $dir = __DIR__ . "/../uploads/$subdir/";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $subdir . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
    if ($bin === false || $bin === '') return '';
    file_put_contents($dir . $name, $bin);
    return "uploads/$subdir/$name";
}

/* Generate the next sequential inspection number: INS-YYYY-#### */
function nextInspectionNo(PDO $pdo): string {
    $prefix = 'INS-' . date('Y') . '-';
    $row = $pdo->query("SELECT MAX(CAST(SUBSTRING(inspection_no, LENGTH('$prefix') + 1) AS UNSIGNED)) FROM inspection_records WHERE inspection_no LIKE '$prefix%'")->fetchColumn();
    $seq = ((int)$row) + 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/* Fixed ordering of the seven inspection categories. */
function inspectionCategories(): array {
    return ['General Safety', 'Architectural Works', 'Civil / Structural Works', 'Electrical Works', 'Mechanical Works', 'Sanitary / Plumbing Works', 'Electronics Works'];
}

try {
    $pdo = getDB();
    switch ("$module/$action") {

        /* =====================================================================
           ORDER OF PAYMENT  (table: order_of_payments)
           ===================================================================== */
        case 'op/list':
            requirePermission('order-of-payment');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = $_GET['search'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';

            $where = []; $params = [];
            if ($search) {
                $where[] = '(o.transaction_no LIKE ? OR o.applicant_name LIKE ? OR o.official_receipt_no LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($dateFrom) { $where[] = 'o.payment_date >= ?'; $params[] = $dateFrom; }
            if ($dateTo) { $where[] = 'o.payment_date <= ?'; $params[] = $dateTo; }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM order_of_payments o $whereSql");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT o.*, u.full_name AS encoded_by_name
                FROM order_of_payments o
                LEFT JOIN users u ON u.id = o.encoded_by
                $whereSql
                ORDER BY o.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            $records = $stmt->fetchAll();

            jsonResponse(['success' => true, 'data' => $records, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);

        case 'op/create':
            requirePermission('order-of-payment');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $transactionNo = trim($data['transaction_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $permitType = $data['permit_type'] ?? '';
            $amount = (float)($data['amount'] ?? 0);
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $timeIn = $data['time_in'] ?? null;
            $timeOut = $data['time_out'] ?? null;
            $orNo = trim($data['official_receipt_no'] ?? '');

            if (!$transactionNo) jsonResponse(['error' => 'Transaction number is required.'], 422);

            $elapsed = null;
            if ($timeIn && $timeOut) {
                [$ih, $im] = explode(':', $timeIn);
                [$oh, $om] = explode(':', $timeOut);
                $elapsed = max(0, ((int)$oh * 60 + (int)$om) - ((int)$ih * 60 + (int)$im));
            }

            $stmt = $pdo->prepare('INSERT INTO order_of_payments (transaction_no, applicant_name, permit_type, amount, payment_date, time_in, time_out, elapsed_minutes, official_receipt_no, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$transactionNo, $applicantName, $permitType, $amount, $paymentDate, $timeIn, $timeOut, $elapsed, $orNo, $_SESSION['user_id']]);
            $id = $pdo->lastInsertId();

            logActivity($_SESSION['user_id'], 'op_created', "Created OP record ID $id: $transactionNo");
            jsonResponse(['success' => true, 'id' => $id, 'message' => 'OP record saved successfully.']);

        case 'op/update':
            requirePermission('order-of-payment');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);

            $transactionNo = trim($data['transaction_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $permitType = $data['permit_type'] ?? '';
            $amount = (float)($data['amount'] ?? 0);
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $paymentStatus = $data['payment_status'] ?? 'Pending';
            $timeIn = $data['time_in'] ?? null;
            $timeOut = $data['time_out'] ?? null;
            $orNo = trim($data['official_receipt_no'] ?? '');

            $elapsed = null;
            if ($timeIn && $timeOut) {
                [$ih, $im] = explode(':', $timeIn);
                [$oh, $om] = explode(':', $timeOut);
                $elapsed = max(0, ((int)$oh * 60 + (int)$om) - ((int)$ih * 60 + (int)$im));
            }

            $pdo->prepare('UPDATE order_of_payments SET transaction_no=?, applicant_name=?, permit_type=?, amount=?, payment_date=?, payment_status=?, time_in=?, time_out=?, elapsed_minutes=?, official_receipt_no=? WHERE id=?')
                ->execute([$transactionNo, $applicantName, $permitType, $amount, $paymentDate, $paymentStatus, $timeIn, $timeOut, $elapsed, $orNo, $id]);
            logActivity($_SESSION['user_id'], 'op_updated', "Updated OP record ID $id");
            jsonResponse(['success' => true, 'message' => 'OP record updated.']);

        case 'op/delete':
            requirePermission('order-of-payment');
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM order_of_payments WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'op_deleted', "Deleted OP record ID $id");
            jsonResponse(['success' => true, 'message' => 'OP record deleted.']);

        /* =====================================================================
           PERMIT WORKFLOW  (table: permit_workflows)
           ===================================================================== */
        case 'workflow/list':
            requirePermission('permit-workflow');
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = []; $params = [];
            if ($search) {
                $where[] = '(w.application_no LIKE ? OR w.applicant_name LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($status) { $where[] = 'w.status = ?'; $params[] = $status; }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT w.*, u.full_name AS encoded_by_name,
                    (SELECT COUNT(*) FROM workflow_rounds WHERE workflow_id = w.id) AS round_count,
                    (SELECT lr.last_in FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_last_in,
                    (SELECT lr.last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_last_out,
                    (SELECT lr.processing_days FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_processing_days,
                    (SELECT COALESCE(SUM(lr2.processing_days), 0) FROM workflow_rounds lr2 WHERE lr2.workflow_id = w.id) AS total_tat
                FROM permit_workflows w
                LEFT JOIN users u ON u.id = w.encoded_by
                $whereSql
                ORDER BY w.created_at DESC
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'workflow/create':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $permitNo = trim($data['permit_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $applicationNo = trim($data['application_no'] ?? '');
            $projectType = $data['project_type'] ?? '';
            $permitType = $data['permit_type'] ?? '';
            $assessmentApproval = $data['assessment_approval'] ?? '';
            $datePaid = $data['date_paid'] ?? null;
            $released = $data['released'] ?? null;
            $rawStatus = $data['status'] ?? 'pending';
            $status = normalizeWorkflowStatus($rawStatus);

            if (!$permitNo || !$applicantName || !$applicationNo || !$projectType || !$permitType || !$status) {
                jsonResponse(['error' => 'Permit No., Applicant Name, App. No., Application, Permit Type, and Status are required.'], 422);
            }

            $pdo->beginTransaction();
            $firstIn = $datePaid ?: date('Y-m-d');
            $stmt = $pdo->prepare('INSERT INTO permit_workflows (permit_no, application_no, applicant_name, project_type, permit_type, assessment_approval, date_paid, released, status, first_in, current_round, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
            $stmt->execute([$permitNo, $applicationNo, $applicantName, $projectType, $permitType, $assessmentApproval, $datePaid, $released, $status, $firstIn, $_SESSION['user_id']]);
            $workflowId = $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO workflow_rounds (workflow_id, round_number, last_in, processing_days) VALUES (?, 1, ?, ?)')
                ->execute([$workflowId, $firstIn, 1]);

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'workflow_created', "Created workflow for $permitNo ($applicantName)");
            jsonResponse(['success' => true, 'id' => $workflowId, 'message' => 'Workflow created successfully.']);

        case 'workflow/get':
            requirePermission('permit-workflow');
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM permit_workflows WHERE id = ?');
            $stmt->execute([$id]);
            $workflow = $stmt->fetch();
            if (!$workflow) jsonResponse(['error' => 'Workflow not found.'], 404);

            $rStmt = $pdo->prepare('SELECT * FROM workflow_rounds WHERE workflow_id = ? ORDER BY round_number');
            $rStmt->execute([$id]);
            $workflow['rounds'] = $rStmt->fetchAll();

            jsonResponse(['success' => true, 'data' => $workflow]);

        case 'workflow/update':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);

            $permitNo = trim($data['permit_no'] ?? '');
            $applicationNo = trim($data['application_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $projectType = $data['project_type'] ?? '';
            $permitType = $data['permit_type'] ?? '';
            $assessmentApproval = $data['assessment_approval'] ?? '';
            $datePaid = $data['date_paid'] ?? null;
            $released = $data['released'] ?? null;
            $firstIn = $data['first_in'] ?? null;
            $rawStatus = $data['status'] ?? '';
            $status = normalizeWorkflowStatus($rawStatus);

            $pdo->prepare('UPDATE permit_workflows SET permit_no=?, application_no=?, applicant_name=?, project_type=?, permit_type=?, assessment_approval=?, date_paid=?, released=?, first_in=?, status=? WHERE id=?')
                ->execute([$permitNo, $applicationNo, $applicantName, $projectType, $permitType, $assessmentApproval, $datePaid, $released, $firstIn, $status, $id]);
            logActivity($_SESSION['user_id'], 'workflow_updated', "Updated workflow ID $id");
            jsonResponse(['success' => true, 'message' => 'Workflow updated.']);

        case 'workflow/delete':
            requirePermission('permit-workflow');
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM permit_workflows WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'workflow_deleted', "Deleted workflow ID $id");
            jsonResponse(['success' => true, 'message' => 'Workflow deleted.']);

        case 'workflow/add-round':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $lastIn = $data['last_in'] ?? date('Y-m-d');
            $lastOut = $data['last_out'] ?? null;
            $remarks = trim($data['remarks'] ?? '');

            if (!$workflowId) jsonResponse(['error' => 'Workflow ID required.'], 422);

            $stmt = $pdo->prepare('SELECT MAX(round_number) AS max_round FROM workflow_rounds WHERE workflow_id = ?');
            $stmt->execute([$workflowId]);
            $nextRound = (int)$stmt->fetchColumn() + 1;

            $days = $lastOut ? max(1, (int)((strtotime($lastOut) - strtotime($lastIn)) / 86400)) : 1;

            $pdo->prepare('INSERT INTO workflow_rounds (workflow_id, round_number, last_in, last_out, processing_days, remarks) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$workflowId, $nextRound, $lastIn, $lastOut, $days, $remarks]);
            $pdo->prepare('UPDATE permit_workflows SET current_round = ?, current_stage = ?, status = ? WHERE id = ?')
                ->execute([$nextRound, $lastOut ? 'Completed' : 'In Progress', $lastOut ? 'Approved' : 'Under Review', $workflowId]);

logActivity($_SESSION['user_id'], 'workflow_round_added', "Added round $nextRound to workflow ID $workflowId");
            jsonResponse(['success' => true, 'round' => $nextRound, 'message' => "Round $nextRound added."]);

        case 'workflow/update-round':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $roundNumber = (int)($data['round_number'] ?? 0);
            $lastIn = $data['last_in'] ?? null;
            $lastOut = $data['last_out'] ?? null;
            $processingDays = (int)($data['processing_days'] ?? 0);
            $remarks = trim($data['remarks'] ?? '');

            if (!$workflowId || !$roundNumber) jsonResponse(['error' => 'Workflow ID and Round Number required.'], 422);

            $pdo->prepare('UPDATE workflow_rounds SET last_in=?, last_out=?, processing_days=?, remarks=? WHERE workflow_id=? AND round_number=?')
                ->execute([$lastIn, $lastOut, $processingDays, $remarks, $workflowId, $roundNumber]);

            $maxRound = $pdo->prepare('SELECT MAX(round_number) AS max_round FROM workflow_rounds WHERE workflow_id = ?');
            $maxRound->execute([$workflowId]);
            $latestRound = (int)$maxRound->fetchColumn();
            $status = 'In Progress';
            if ($lastOut) $status = 'Completed';
            $pdo->prepare('UPDATE permit_workflows SET current_round = ?, status = ? WHERE id = ?')
                ->execute([$latestRound, $status, $workflowId]);

            logActivity($_SESSION['user_id'], 'workflow_round_updated', "Updated round $roundNumber for workflow ID $workflowId");
            jsonResponse(['success' => true, 'message' => "Round $roundNumber updated."]);

        case 'workflow/delete-round':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $roundNumber = (int)($data['round_number'] ?? 0);

            if (!$workflowId || !$roundNumber) jsonResponse(['error' => 'Workflow ID and Round Number required.'], 422);

            $pdo->prepare('DELETE FROM workflow_rounds WHERE workflow_id = ? AND round_number = ?')->execute([$workflowId, $roundNumber]);

            $maxRound = $pdo->prepare('SELECT MAX(round_number) AS max_round FROM workflow_rounds WHERE workflow_id = ?');
            $maxRound->execute([$workflowId]);
            $latestRound = (int)$maxRound->fetchColumn();
            $newStatus = 'In Progress';
            if ($latestRound > 0) {
                $latestRoundData = $pdo->prepare('SELECT last_out FROM workflow_rounds WHERE workflow_id = ? AND round_number = ?');
                $latestRoundData->execute([$workflowId, $latestRound]);
                $lr = $latestRoundData->fetch();
                if ($lr && $lr['last_out']) $newStatus = 'Completed';
            }
            $pdo->prepare('UPDATE permit_workflows SET current_round = ?, status = ? WHERE id = ?')
                ->execute([$latestRound ?: 1, $newStatus, $workflowId]);

            logActivity($_SESSION['user_id'], 'workflow_round_deleted', "Deleted round $roundNumber from workflow ID $workflowId");
            jsonResponse(['success' => true, 'message' => "Round $roundNumber deleted."]);

        /* =====================================================================
            PERMIT APPROVAL  (table: permit_approvals)
            ===================================================================== */
        case 'approval/list':
            if (!hasPermission('permit-approval-encoding') && !hasPermission('permit-approval-records')) {
                jsonResponse(['error' => 'Forbidden.'], 403);
            }
            $search = $_GET['search'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';

            $where = []; $params = [];
            if ($search) {
                $where[] = '(a.application_no LIKE ? OR a.applicant_name LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($dateFrom) { $where[] = 'a.approval_date >= ?'; $params[] = $dateFrom; }
            if ($dateTo) { $where[] = 'a.approval_date <= ?'; $params[] = $dateTo; }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name AS approved_by_name
                FROM permit_approvals a
                LEFT JOIN users u ON u.id = a.approved_by
                $whereSql
                ORDER BY a.created_at DESC
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'approval/create':
            requirePermission('permit-approval-encoding');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $applicationNo = trim($data['application_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $permitType = $data['permit_type'] ?? '';
            $approvalDate = $data['approval_date'] ?? date('Y-m-d');
            $tat = (int)($data['tat'] ?? 0);
            $workflowId = !empty($data['workflow_id']) ? (int)$data['workflow_id'] : null;
            $bpNo = trim($data['bp_no'] ?? '');
            $location = trim($data['location'] ?? '');
            $typeOfOccupancy = trim($data['type_of_occupancy'] ?? '');
            $contractor = trim($data['contractor'] ?? '');
            $landOthers = ($data['land_others'] ?? '') !== '' ? (float)$data['land_others'] : null;
            $surcharge = ($data['surcharge'] ?? '') !== '' ? (float)$data['surcharge'] : null;
            $area = ($data['area'] ?? '') !== '' ? (float)$data['area'] : null;
            $lineGrade = trim($data['line_grade'] ?? '');
            $bldgCost = ($data['bldg_cost'] ?? '') !== '' ? (float)$data['bldg_cost'] : null;
            $permitNo = trim($data['permit_no'] ?? '');
            $incharge = trim($data['incharge'] ?? '');
            $orNo = trim($data['or_no'] ?? '');
            $fees = ($data['fees'] ?? '') !== '' ? (float)$data['fees'] : null;
            $datePaid = $data['date_paid'] ?? null;
            $receivedBy = trim($data['received_by'] ?? '');
            $dateOop = $data['date_oop'] ?? null;
            $dateReceived = $data['date_received'] ?? null;
            $dateApproved = $data['date_approved'] ?? null;

            if (!$applicationNo || !$applicantName) jsonResponse(['error' => 'Application No and Applicant Name are required.'], 422);

            $pdo->prepare('INSERT INTO permit_approvals (workflow_id, application_no, applicant_name, permit_type, approval_date, tat, approved_by, bp_no, location, type_of_occupancy, contractor, land_others, surcharge, area, line_grade, bldg_cost, permit_no, incharge, or_no, fees, date_paid, received_by, date_oop, date_received, date_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$workflowId, $applicationNo, $applicantName, $permitType, $approvalDate, $tat, $_SESSION['user_id'], $bpNo, $location, $typeOfOccupancy, $contractor, $landOthers, $surcharge, $area, $lineGrade, $bldgCost, $permitNo, $incharge, $orNo, $fees, $datePaid, $receivedBy, $dateOop, $dateReceived, $dateApproved]);
            if ($workflowId) {
                $pdo->prepare('UPDATE permit_workflows SET status = ? WHERE id = ?')->execute(['Approved', $workflowId]);
            }
            logActivity($_SESSION['user_id'], 'approval_created', "Approved permit $applicationNo ($applicantName)");
            jsonResponse(['success' => true, 'message' => 'Permit approved successfully.']);

        case 'approval/delete':
            requirePermission('permit-approval-encoding');
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM permit_approvals WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'approval_deleted', "Deleted approval record ID $id");
            jsonResponse(['success' => true, 'message' => 'Approval record deleted.']);

        /* =====================================================================
           RELEASING  (table: releasing_plans)
           ===================================================================== */
        case 'releasing/list':
            requirePermission('releasing');
            $search = $_GET['search'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';

            $where = []; $params = [];
            if ($search) {
                $where[] = '(r.permit_application_no LIKE ? OR r.applicant_name LIKE ? OR r.claimed_by LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($dateFrom) { $where[] = 'r.date_released >= ?'; $params[] = $dateFrom; }
            if ($dateTo) { $where[] = 'r.date_released <= ?'; $params[] = $dateTo; }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name AS encoded_by_name
                FROM releasing_plans r
                LEFT JOIN users u ON u.id = r.encoded_by
                $whereSql
                ORDER BY r.created_at DESC
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'releasing/create':
            requirePermission('releasing');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $permitAppNo = trim($data['permit_application_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $dateReleased = $data['date_released'] ?? date('Y-m-d');
            $claimedBy = trim($data['claimed_by'] ?? '');
            $timeReleased = $data['time_released'] ?? null;

            if (!$permitAppNo || !$applicantName) jsonResponse(['error' => 'Permit Application No and Applicant are required.'], 422);

            $pdo->prepare('INSERT INTO releasing_plans (permit_application_no, applicant_name, date_released, claimed_by, time_released, encoded_by) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$permitAppNo, $applicantName, $dateReleased, $claimedBy, $timeReleased, $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'releasing_created', "Released permit $permitAppNo to $claimedBy");
            jsonResponse(['success' => true, 'message' => 'Release record saved.']);

        case 'releasing/delete':
            requirePermission('releasing');
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM releasing_plans WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'releasing_deleted', "Deleted release record ID $id");
            jsonResponse(['success' => true, 'message' => 'Release record deleted.']);

        /* =====================================================================
           INSPECTION MANAGEMENT
           (tables: inspection_schedules, inspection_records,
            inspection_template_items, inspection_results, inspection_photos)
           ===================================================================== */
        case 'inspection/schedules/list':
            requirePermission('inspection-schedule');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');

            $where = []; $params = [];
            if ($search) {
                $where[] = '(s.application_no LIKE ? OR s.permit_no LIKE ? OR s.project_title LIKE ? OR s.applicant_name LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($status) { $where[] = 's.status = ?'; $params[] = $status; }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inspection_schedules s $whereSql");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT s.*, u.full_name AS inspector_name, e.full_name AS encoded_by_name
                FROM inspection_schedules s
                LEFT JOIN users u ON u.id = s.inspector_id
                LEFT JOIN users e ON e.id = s.encoded_by
                $whereSql
                ORDER BY s.scheduled_date DESC, s.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);

        case 'inspection/schedules/create':
        case 'inspection/schedules/update':
            requirePermission('inspection-schedule');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            $applicationNo = trim($data['application_no'] ?? '');
            $permitNo = trim($data['permit_no'] ?? '');
            $projectTitle = trim($data['project_title'] ?? '');
            $projectLocation = trim($data['project_location'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $ownerRepresentative = trim($data['owner_representative'] ?? '');
            $contactNumber = trim($data['contact_number'] ?? '');
            $scheduledDate = $data['scheduled_date'] ?? null;
            $scheduledTime = $data['scheduled_time'] ?? null;
            $inspectorId = ($data['inspector_id'] ?? null) ? (int)$data['inspector_id'] : null;
            $status = $data['status'] ?? 'Scheduled';
            $remarks = trim($data['remarks'] ?? '');

            if (!$applicationNo || !$projectTitle || !$applicantName) {
                jsonResponse(['error' => 'Application No., Project Title, and Applicant are required.'], 422);
            }
            if ($action === 'schedules/create') {
                $pdo->prepare('INSERT INTO inspection_schedules (application_no, permit_no, project_title, project_location, applicant_name, owner_representative, contact_number, scheduled_date, scheduled_time, inspector_id, status, remarks, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$applicationNo, $permitNo, $projectTitle, $projectLocation, $applicantName, $ownerRepresentative, $contactNumber, $scheduledDate, $scheduledTime, $inspectorId, $status, $remarks, $_SESSION['user_id']]);
                $newId = $pdo->lastInsertId();
                logActivity($_SESSION['user_id'], 'inspection_schedule_created', "Scheduled inspection for $applicationNo ($projectTitle)");
                jsonResponse(['success' => true, 'id' => $newId, 'message' => 'Inspection schedule saved.']);
            } else {
                if (!$id) jsonResponse(['error' => 'ID required.'], 422);
                $pdo->prepare('UPDATE inspection_schedules SET application_no=?, permit_no=?, project_title=?, project_location=?, applicant_name=?, owner_representative=?, contact_number=?, scheduled_date=?, scheduled_time=?, inspector_id=?, status=?, remarks=? WHERE id=?')
                    ->execute([$applicationNo, $permitNo, $projectTitle, $projectLocation, $applicantName, $ownerRepresentative, $contactNumber, $scheduledDate, $scheduledTime, $inspectorId, $status, $remarks, $id]);
                logActivity($_SESSION['user_id'], 'inspection_schedule_updated', "Updated inspection schedule ID $id");
                jsonResponse(['success' => true, 'message' => 'Inspection schedule updated.']);
            }

        case 'inspection/schedules/delete':
            requirePermission('inspection-schedule');
            $delData = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($delData['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM inspection_schedules WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'inspection_schedule_deleted', "Deleted inspection schedule ID $id");
            jsonResponse(['success' => true, 'message' => 'Inspection schedule deleted.']);

        case 'inspection/template':
            requirePermission('inspection-checklist');
            $rows = $pdo->query('SELECT id, category, item_text, item_type, sort_order FROM inspection_template_items WHERE is_active = 1 ORDER BY category, sort_order')->fetchAll();
            $grouped = [];
            foreach (inspectionCategories() as $cat) $grouped[$cat] = [];
            foreach ($rows as $r) $grouped[$r['category']][] = $r;
            jsonResponse(['success' => true, 'categories' => inspectionCategories(), 'data' => $grouped]);

        case 'inspection/checklist/create':
        case 'inspection/checklist/update':
            requirePermission('inspection-checklist');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            $applicationNo = trim($data['application_no'] ?? '');
            $permitNo = trim($data['permit_no'] ?? '');
            $permitDateIssued = ($data['permit_date_issued'] ?? '') !== '' ? $data['permit_date_issued'] : null;
            $projectTitle = trim($data['project_title'] ?? '');
            $projectLocation = trim($data['project_location'] ?? '');
            $ownerRepresentative = trim($data['owner_representative'] ?? '');
            $contactNumber = trim($data['contact_number'] ?? '');
            $projectContractor = trim($data['project_contractor'] ?? '');
            $projectEngineer = trim($data['project_engineer'] ?? '');
            $inspectionTeam = trim($data['inspection_team'] ?? '');
            $inspectionDate = $data['inspection_date'] ?? date('Y-m-d');
            $inspectionType = trim($data['inspection_type'] ?? '');
            $inspectionResult = ($data['inspection_result'] ?? '') !== '' ? $data['inspection_result'] : null;
            if ($inspectionResult !== null && !in_array($inspectionResult, ['Passed', 'Passed with Remarks', 'Ongoing', 'Failed', 'For Re-inspection'], true)) $inspectionResult = null;
            $timeStarted = $data['time_started'] ?? null;
            $timeFinished = $data['time_finished'] ?? null;
            $physicalAccomplishment = ($data['physical_accomplishment'] ?? '') !== '' ? (float)$data['physical_accomplishment'] : null;
            $mechAccomplishment = ($data['mech_accomplishment'] ?? '') !== '' ? (float)$data['mech_accomplishment'] : null;
            $extraFields = $data['extra_fields'] ?? null;
            if (is_array($extraFields) || is_object($extraFields)) $extraFields = json_encode($extraFields);
            if ($extraFields !== null && trim((string)$extraFields) === '') $extraFields = null;
            $overallFindings = trim($data['overall_findings'] ?? '');
            $recommendations = trim($data['recommendations'] ?? '');
            $completionPercentage = ($data['completion_percentage'] ?? '') !== '' ? (float)$data['completion_percentage'] : null;
            $scheduleId = !empty($data['schedule_id']) ? (int)$data['schedule_id'] : null;
            $results = is_array($data['results'] ?? null) ? $data['results'] : [];
            $newSignature = trim($data['inspector_signature'] ?? '');

            if (!$projectTitle || !$inspectionDate) {
                jsonResponse(['error' => 'Project Title and Inspection Date are required.'], 422);
            }

            $existing = null;
            if ($action === 'checklist/update') {
                if (!$id) jsonResponse(['error' => 'ID required.'], 422);
                $stmt = $pdo->prepare('SELECT * FROM inspection_records WHERE id = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing) jsonResponse(['error' => 'Inspection record not found.'], 404);
                if (!in_array($existing['status'], ['Draft', 'Rejected'], true)) {
                    jsonResponse(['error' => 'Only draft or rejected inspections can be edited.'], 422);
                }
            }

            $signaturePath = $existing['inspector_signature'] ?? null;
            if ($newSignature) $signaturePath = inspectionSaveSignature($newSignature, 'inspection_signatures');

            if ($action === 'checklist/create') {
                $inspectionNo = nextInspectionNo($pdo);
                if (!$applicationNo) $applicationNo = 'APP-' . $inspectionNo;
                $pdo->prepare('INSERT INTO inspection_records (inspection_no, schedule_id, application_no, permit_no, permit_date_issued, project_title, project_location, owner_representative, contact_number, project_contractor, project_engineer, inspection_team, inspection_date, inspection_type, inspection_result, time_started, time_finished, physical_accomplishment, mech_accomplishment, extra_fields, overall_findings, recommendations, completion_percentage, status, inspector_id, inspector_signature, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$inspectionNo, $scheduleId, $applicationNo, $permitNo, $permitDateIssued, $projectTitle, $projectLocation, $ownerRepresentative, $contactNumber, $projectContractor, $projectEngineer, $inspectionTeam, $inspectionDate, $inspectionType, $inspectionResult, $timeStarted, $timeFinished, $physicalAccomplishment, $mechAccomplishment, $extraFields, $overallFindings, $recommendations, $completionPercentage, 'Draft', $_SESSION['user_id'], $signaturePath, $_SESSION['user_id']]);
                $id = (int)$pdo->lastInsertId();
                logActivity($_SESSION['user_id'], 'inspection_checklist_created', "Created inspection $inspectionNo ($projectTitle)");
            } else {
                if (!$applicationNo) $applicationNo = $existing['application_no'];
                $pdo->prepare('UPDATE inspection_records SET schedule_id=?, application_no=?, permit_no=?, permit_date_issued=?, project_title=?, project_location=?, owner_representative=?, contact_number=?, project_contractor=?, project_engineer=?, inspection_team=?, inspection_date=?, inspection_type=?, inspection_result=?, time_started=?, time_finished=?, physical_accomplishment=?, mech_accomplishment=?, extra_fields=?, overall_findings=?, recommendations=?, completion_percentage=?, inspector_signature=? WHERE id=?')
                    ->execute([$scheduleId, $applicationNo, $permitNo, $permitDateIssued, $projectTitle, $projectLocation, $ownerRepresentative, $contactNumber, $projectContractor, $projectEngineer, $inspectionTeam, $inspectionDate, $inspectionType, $inspectionResult, $timeStarted, $timeFinished, $physicalAccomplishment, $mechAccomplishment, $extraFields, $overallFindings, $recommendations, $completionPercentage, $signaturePath, $id]);
                logActivity($_SESSION['user_id'], 'inspection_checklist_updated', "Updated inspection ID $id");
            }

            $pdo->prepare('DELETE FROM inspection_results WHERE inspection_id = ?')->execute([$id]);
            $resStmt = $pdo->prepare('INSERT INTO inspection_results (inspection_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($results as $r) {
                $ti = (int)($r['template_item_id'] ?? 0);
                if (!$ti) continue;
                $cat = $r['category'] ?? '';
                $text = $r['item_text'] ?? '';
                $itype = ($r['item_type'] ?? '') === 'checkbox' ? 'checkbox' : 'radio';
                $res = in_array($r['result'] ?? '', ['Pass', 'Fail', 'N/A'], true) ? $r['result'] : 'Pass';
                $rm = trim($r['remarks'] ?? '');
                $resStmt->execute([$id, $ti, $cat, $text, $itype, $res, $rm]);
            }

            jsonResponse(['success' => true, 'id' => $id, 'message' => 'Inspection checklist saved.']);

        case 'inspection/checklist/get':
            requirePermission('inspection-checklist');
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $stmt = $pdo->prepare("SELECT r.*, u.full_name AS inspector_name, rev.full_name AS reviewed_by_name, app.full_name AS approved_by_name, e.full_name AS encoded_by_name FROM inspection_records r LEFT JOIN users u ON u.id = r.inspector_id LEFT JOIN users rev ON rev.id = r.reviewed_by LEFT JOIN users app ON app.id = r.approved_by LEFT JOIN users e ON e.id = r.encoded_by WHERE r.id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) jsonResponse(['error' => 'Inspection record not found.'], 404);
            if ($record['extra_fields']) {
                $record['extra_fields'] = json_decode($record['extra_fields'], true) ?: [];
            } else {
                $record['extra_fields'] = [];
            }
            $resStmt = $pdo->prepare('SELECT template_item_id, category, item_text, item_type, result, remarks FROM inspection_results WHERE inspection_id = ?');
            $resStmt->execute([$id]);
            $record['results'] = $resStmt->fetchAll();
            $photoStmt = $pdo->prepare('SELECT id, file_path, caption FROM inspection_photos WHERE inspection_id = ? ORDER BY id');
            $photoStmt->execute([$id]);
            $record['photos'] = $photoStmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $record]);

        case 'inspection/checklist/delete':
            requirePermission('inspection-checklist');
            if (!hasPermission('inspection-delete')) {
                jsonResponse(['error' => 'You do not have permission to delete inspection records.'], 403);
            }
            $delData = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($delData['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $stmt = $pdo->prepare('SELECT inspection_no, project_title FROM inspection_records WHERE id = ?');
            $stmt->execute([$id]);
            $rec = $stmt->fetch();
            $pdo->prepare('DELETE FROM inspection_records WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'inspection_checklist_deleted', "Deleted inspection " . ($rec['inspection_no'] ?? "ID $id"));
            jsonResponse(['success' => true, 'message' => 'Inspection record deleted.']);

        case 'inspection/checklist/submit':
            requirePermission('inspection-checklist');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $stmt = $pdo->prepare('SELECT inspection_no, status FROM inspection_records WHERE id = ?');
            $stmt->execute([$id]);
            $rec = $stmt->fetch();
            if (!$rec) jsonResponse(['error' => 'Inspection record not found.'], 404);
            if (!in_array($rec['status'], ['Draft', 'Rejected'], true)) jsonResponse(['error' => 'Record is already submitted.'], 422);
            $pdo->prepare("UPDATE inspection_records SET status = 'Under Review' WHERE id = ?")->execute([$id]);
            logActivity($_SESSION['user_id'], 'inspection_checklist_submitted', "Submitted inspection {$rec['inspection_no']} for review");
            jsonResponse(['success' => true, 'message' => 'Inspection submitted for review.']);

        case 'inspection/checklist/review':
        case 'inspection/checklist/approve':
        case 'inspection/checklist/reject':
            requirePermission('inspection-checklist');
            if (!hasPermission('inspection-edit')) {
                jsonResponse(['error' => 'You do not have permission to review or approve inspection checklists.'], 403);
            }
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $signature = trim($data['signature'] ?? '');
            $remarks = trim($data['remarks'] ?? '');
            if (!$signature) jsonResponse(['error' => 'A handwritten signature is required.'], 422);

            $stmt = $pdo->prepare('SELECT inspection_no, status FROM inspection_records WHERE id = ?');
            $stmt->execute([$id]);
            $rec = $stmt->fetch();
            if (!$rec) jsonResponse(['error' => 'Inspection record not found.'], 404);

            if ($action === 'checklist/review') {
                if ($rec['status'] !== 'Under Review') jsonResponse(['error' => 'Record must be Under Review before reviewing.'], 422);
                $path = inspectionSaveSignature($signature, 'inspection_signatures');
                $pdo->prepare('UPDATE inspection_records SET reviewed_by=?, review_signature=?, review_date=NOW(), review_remarks=?, status=? WHERE id=?')
                    ->execute([$_SESSION['user_id'], $path, $remarks, $remarks ? 'Rejected' : 'Approved', $id]);
                $newStatus = $remarks ? 'Rejected' : 'Approved';
                logActivity($_SESSION['user_id'], 'inspection_reviewed', "Reviewed inspection {$rec['inspection_no']} as $newStatus");
                jsonResponse(['success' => true, 'status' => $newStatus, 'message' => "Inspection $newStatus."]);
            } elseif ($action === 'checklist/approve') {
                if ($rec['status'] !== 'Approved') jsonResponse(['error' => 'Record must be Approved before final approval.'], 422);
                $path = inspectionSaveSignature($signature, 'inspection_signatures');
                $pdo->prepare('UPDATE inspection_records SET approved_by=?, approval_signature=?, approval_date=NOW(), approval_remarks=?, status=? WHERE id=?')
                    ->execute([$_SESSION['user_id'], $path, $remarks, 'Completed', $id]);
                logActivity($_SESSION['user_id'], 'inspection_approved', "Completed inspection {$rec['inspection_no']}");
                jsonResponse(['success' => true, 'status' => 'Completed', 'message' => 'Inspection completed.']);
            } else {
                if ($rec['status'] !== 'Under Review') jsonResponse(['error' => 'Only Under Review records can be rejected.'], 422);
                $path = inspectionSaveSignature($signature, 'inspection_signatures');
                $pdo->prepare('UPDATE inspection_records SET reviewed_by=?, review_signature=?, review_date=NOW(), review_remarks=?, status=? WHERE id=?')
                    ->execute([$_SESSION['user_id'], $path, $remarks, 'Rejected', $id]);
                logActivity($_SESSION['user_id'], 'inspection_rejected', "Rejected inspection {$rec['inspection_no']}");
                jsonResponse(['success' => true, 'status' => 'Rejected', 'message' => 'Inspection rejected.']);
            }

        case 'inspection/photos/upload':
            requirePermission('inspection-checklist');
            $inspectionId = (int)($_POST['inspection_id'] ?? $_GET['inspection_id'] ?? 0);
            $caption = trim($_POST['caption'] ?? '');
            if (!$inspectionId) jsonResponse(['error' => 'Inspection ID required.'], 422);
            if (empty($_FILES['photo'])) jsonResponse(['error' => 'No file uploaded.'], 422);
            $f = $_FILES['photo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed, true)) jsonResponse(['error' => 'Only image files are allowed (JPG, PNG, GIF, WEBP).'], 422);
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) jsonResponse(['error' => 'Upload failed or file exceeds 8 MB.'], 422);
            $dir = __DIR__ . '/../uploads/inspection_photos/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . $name)) jsonResponse(['error' => 'Could not store file.'], 500);
            $path = "uploads/inspection_photos/$name";
            $pdo->prepare('INSERT INTO inspection_photos (inspection_id, file_path, caption, uploaded_by) VALUES (?, ?, ?, ?)')
                ->execute([$inspectionId, $path, $caption, $_SESSION['user_id']]);
            jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'path' => $path, 'message' => 'Photo uploaded.']);

        case 'inspection/photos/remove':
            requirePermission('inspection-checklist');
            $delData = json_decode(file_get_contents('php://input'), true) ?: [];
            $photoId = (int)($delData['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$photoId) jsonResponse(['error' => 'Photo ID required.'], 422);
            $stmt = $pdo->prepare('SELECT file_path FROM inspection_photos WHERE id = ?');
            $stmt->execute([$photoId]);
            $ph = $stmt->fetch();
            if ($ph) {
                $abs = __DIR__ . '/../' . $ph['file_path'];
                if (is_file($abs)) @unlink($abs);
                $pdo->prepare('DELETE FROM inspection_photos WHERE id = ?')->execute([$photoId]);
            }
            jsonResponse(['success' => true, 'message' => 'Photo removed.']);

        case 'inspection/history/list':
            requirePermission('inspection-history');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');

            $where = []; $params = [];
            if ($search) {
                $where[] = '(r.inspection_no LIKE ? OR r.permit_no LIKE ? OR r.application_no LIKE ? OR r.project_title LIKE ? OR r.owner_representative LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($status) { $where[] = 'r.status = ?'; $params[] = $status; }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inspection_records r $whereSql");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name AS inspector_name
                FROM inspection_records r
                LEFT JOIN users u ON u.id = r.inspector_id
                $whereSql
                ORDER BY r.inspection_date DESC, r.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);

        case 'inspection/reports/list':
            requirePermission('inspection-reports');
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $where = []; $params = [];
            if ($search) {
                $where[] = '(r.inspection_no LIKE ? OR r.permit_no LIKE ? OR r.application_no LIKE ? OR r.project_title LIKE ? OR r.owner_representative LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($status) { $where[] = 'r.status = ?'; $params[] = $status; }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name AS inspector_name
                FROM inspection_records r
                LEFT JOIN users u ON u.id = r.inspector_id
                $whereSql
                ORDER BY r.inspection_date DESC, r.created_at DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        /* =====================================================================
           NOTIFICATIONS  (table: notifications)
           ===================================================================== */
        case 'notifications/list':
            $stmt = $pdo->prepare("SELECT n.*, u.full_name AS sender_name FROM notifications n LEFT JOIN users u ON u.id = n.sender_id LEFT JOIN announcements a ON n.module_name = 'announcements' AND a.id = n.record_id WHERE n.user_id = ? AND (n.module_name != 'announcements' OR a.id IS NOT NULL) ORDER BY n.created_at DESC LIMIT 100");
            $stmt->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'notifications/unread-count':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n LEFT JOIN announcements a ON n.module_name = 'announcements' AND a.id = n.record_id WHERE n.user_id = ? AND n.is_read = 0 AND n.module_name = 'announcements' AND a.id IS NOT NULL");
            $stmt->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true, 'count' => (int)$stmt->fetchColumn()]);

        case 'notifications/mark-all-read':
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true]);

        case 'notifications/mark-read':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $_SESSION['user_id']]);
            } else {
                $recordId = (int)($_GET['record_id'] ?? 0);
                if ($recordId) {
                    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE module_name = 'announcements' AND record_id = ? AND user_id = ?")->execute([$recordId, $_SESSION['user_id']]);
                }
            }
            jsonResponse(['success' => true]);

        /* =====================================================================
           SELF PERMISSIONS  (drives live sidebar visibility for users)
           ===================================================================== */
        case 'me/permissions':
            $isAdmin = !empty($_SESSION['is_admin']);
            $alwaysVisible = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
            if ($isAdmin) {
                $granted = ['dashboard', 'order-of-payment', 'op-records', 'permit-workflow', 'workflow-details', 'permit-approval-encoding', 'permit-approval-records', 'releasing', 'releasing-records', 'notifications', 'announcements', 'profile', 'settings'];
            } else {
                $stmt = $pdo->prepare('SELECT module_key FROM user_permissions WHERE user_id = ? AND is_granted = 1');
                $stmt->execute([$_SESSION['user_id']]);
                $granted = array_values(array_unique(array_merge($alwaysVisible, $stmt->fetchAll(PDO::FETCH_COLUMN))));
            }
            jsonResponse(['success' => true, 'granted' => $granted]);

        /* =====================================================================
           ANNOUNCEMENTS  (table: announcements)
           ===================================================================== */
        case 'announcements/list':
            $stmt = $pdo->prepare('SELECT a.*, u.full_name AS posted_by_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by WHERE a.is_active = 1 ORDER BY a.created_at DESC LIMIT 50');
            $stmt->execute();
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'announcements/create':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $tag = $data['tag'] ?? 'Reminder';
            $title = trim($data['title'] ?? '');
            $content = trim($data['content'] ?? '');
            if (!$title || !$content) jsonResponse(['error' => 'Title and content required.'], 422);
            $pdo->prepare('INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)')
                ->execute([$title, $content, $_SESSION['user_id']]);
            $annId = (int)$pdo->lastInsertId();

            $recipients = $pdo->query('SELECT id FROM users WHERE is_active = 1 AND is_admin = 0')->fetchAll(PDO::FETCH_COLUMN);
            if ($recipients) {
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, sender_id, title, message, module_name, record_id) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($recipients as $uid) {
                    $stmt->execute([$uid, $_SESSION['user_id'], $title, $content, 'announcements', $annId]);
                }
            }
            logActivity($_SESSION['user_id'], 'announcement_created', "Created announcement: $title (notified " . count($recipients) . " user(s))");
            jsonResponse(['success' => true, 'message' => 'Announcement posted and ' . count($recipients) . ' user(s) notified.']);

        case 'announcements/delete':
            requireAdmin();
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM notifications WHERE module_name = 'announcements' AND record_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'announcement_deleted', "Deleted announcement ID $id");
            jsonResponse(['success' => true, 'message' => 'Announcement deleted.']);

        /* =====================================================================
           ACTIVITY LOGS (table: activity_logs)
           ===================================================================== */
        case 'activity/list':
            requireAdmin();
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $userId = (int)($_GET['user_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');
            $module = trim($_GET['module'] ?? '');

            $where = []; $params = [];
            if ($userId) { $where[] = 'a.user_id = ?'; $params[] = $userId; }
            if ($module) { $where[] = 'a.module_name = ?'; $params[] = $module; }
            if ($search) { $where[] = '(a.description LIKE ? OR a.action LIKE ? OR u.full_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id $whereSql");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name AS user_name
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id
                $whereSql
                ORDER BY a.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);

        /* =====================================================================
           DASHBOARD OVERVIEW (Admin stat figures, realtime polling)
           ===================================================================== */
        case 'dashboard/overview':
            requireAdmin();
            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');
            $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $opToday = (int)$pdo->query("SELECT COUNT(*) FROM order_of_payments WHERE payment_date = '$today'")->fetchColumn();
            $activeWorkflows = (int)$pdo->query("SELECT COUNT(*) FROM permit_workflows WHERE status NOT IN ('Approved','Disapproved','Released')")->fetchColumn();
            $approvalsMonth = (int)$pdo->query("SELECT COUNT(*) FROM permit_approvals WHERE approval_date >= '$monthStart'")->fetchColumn();
            $releasingToday = (int)$pdo->query("SELECT COUNT(*) FROM releasing_plans WHERE date_released = '$today'")->fetchColumn();
            $notifCount = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
            jsonResponse(['success' => true, 'data' => [
                'op' => $opToday,
                'workflow' => $activeWorkflows,
                'approvals' => $approvalsMonth,
                'releasing' => $releasingToday,
                'users' => $totalUsers,
                'notifications' => $notifCount,
            ]]);

        /* =====================================================================
           DASHBOARD STATS (All authenticated users, scoped by permissions)
           ===================================================================== */
        case 'dashboard/stats':
            requireAuth();
            $today = date('Y-m-d');
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $monthStart = date('Y-m-01');
            $yearStart = date('Y-01-01');
            $userId = $_SESSION['user_id'];
            $stats = [];

            $periodCounts = function (string $table, string $dateCol, string $userCol) use ($pdo, $userId, $weekStart, $monthStart, $yearStart) {
                $stmt = $pdo->prepare("SELECT
                    COALESCE(SUM(CASE WHEN $dateCol >= ? THEN 1 ELSE 0 END), 0) AS week,
                    COALESCE(SUM(CASE WHEN $dateCol >= ? THEN 1 ELSE 0 END), 0) AS month,
                    COALESCE(SUM(CASE WHEN $dateCol >= ? THEN 1 ELSE 0 END), 0) AS year
                    FROM $table WHERE $userCol = ?");
                $stmt->execute([$weekStart, $monthStart, $yearStart, $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return [
                    'week'  => (int)$row['week'],
                    'month' => (int)$row['month'],
                    'year'  => (int)$row['year'],
                ];
            };

            if (hasPermission('order-of-payment')) {
                $stats['op'] = $periodCounts('order_of_payments', 'payment_date', 'encoded_by');
            }
            if (hasPermission('permit-workflow')) {
                $stats['workflow'] = $periodCounts('permit_workflows', 'created_at', 'encoded_by');
            }
            if (hasPermission('permit-approval-encoding') || hasPermission('permit-approval-records')) {
                $stats['approval'] = $periodCounts('permit_approvals', 'approval_date', 'approved_by');
            }
            if (hasPermission('releasing')) {
                $stats['releasing'] = $periodCounts('releasing_plans', 'date_released', 'encoded_by');
            }
            if (hasPermission('inspection-checklist')) {
                $stats['inspection'] = $periodCounts('inspection_records', 'inspection_date', 'inspector_id');
            }
            jsonResponse(['success' => true, 'data' => $stats]);

        /* =====================================================================
           DASHBOARD TRENDS (Weekly / Monthly usage analytics, per user)
           ===================================================================== */
        case 'dashboard/trends':
            requireAuth();
            $userId = $_SESSION['user_id'];

            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $trendEnd = date('Y-m-d', strtotime($monthEnd . ' +1 day'));

            $weekDates = [];
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("$weekStart +$i day"));
                $weekDates[$date] = date('D', strtotime($date));
            }

            $monthDates = [];
            for ($d = $monthStart; strtotime($d) <= strtotime($monthEnd); $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $monthDates[$d] = (int)substr($d, 8, 2);
            }

            $trendBuilder = function (string $table, string $dateCol, string $userCol) use ($pdo, $userId, $weekStart, $trendEnd, $weekDates, $monthDates) {
                $stmt = $pdo->prepare("SELECT DATE($dateCol) AS d, COUNT(*) AS c FROM $table WHERE $userCol = ? AND $dateCol >= ? AND $dateCol < ? GROUP BY DATE($dateCol)");
                $stmt->execute([$userId, $weekStart, $trendEnd]);
                $rows = [];
                foreach ($stmt->fetchAll() as $r) {
                    $rows[$r['d']] = (int)$r['c'];
                }

                $week = [];
                foreach ($weekDates as $date => $label) {
                    $week[] = ['label' => $label, 'date' => $date, 'count' => $rows[$date] ?? 0];
                }

                $month = [];
                foreach ($monthDates as $date => $dayNum) {
                    $month[] = ['label' => (string)$dayNum, 'date' => $date, 'count' => $rows[$date] ?? 0];
                }

                return ['week' => $week, 'month' => $month];
            };

            $trends = [];
            if (hasPermission('order-of-payment')) {
                $trends['op'] = $trendBuilder('order_of_payments', 'payment_date', 'encoded_by');
            }
            if (hasPermission('permit-workflow')) {
                $trends['workflow'] = $trendBuilder('permit_workflows', 'created_at', 'encoded_by');
            }
            if (hasPermission('permit-approval-encoding') || hasPermission('permit-approval-records')) {
                $trends['approval'] = $trendBuilder('permit_approvals', 'approval_date', 'approved_by');
            }
            if (hasPermission('releasing')) {
                $trends['releasing'] = $trendBuilder('releasing_plans', 'date_released', 'encoded_by');
            }
            jsonResponse(['success' => true, 'data' => $trends]);

        /* =====================================================================
           DASHBOARD STAFF SUMMARY (Admin reports)
           ===================================================================== */
        case 'dashboard/staff-summary':
            requireAdmin();
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name AS name, u.username,
                    (SELECT COUNT(*) FROM order_of_payments WHERE encoded_by = u.id AND payment_date BETWEEN ? AND ?) AS op,
                    (SELECT COUNT(*) FROM permit_workflows WHERE encoded_by = u.id AND created_at BETWEEN ? AND ?) AS workflow,
                    (SELECT COUNT(*) FROM permit_approvals WHERE approved_by = u.id AND approval_date BETWEEN ? AND ?) AS approved,
                    (SELECT COUNT(*) FROM releasing_plans WHERE encoded_by = u.id AND date_released BETWEEN ? AND ?) AS releasing
                FROM users u WHERE u.is_active = 1 AND u.is_admin = 0
                ORDER BY u.full_name
            ");
            $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        /* =====================================================================
           DASHBOARD EXPORT — Staff Productivity Report (.xlsx)
           ===================================================================== */
        case 'dashboard/export-csv':
            requireAdmin();
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT u.full_name AS name, u.username,
                    (SELECT COUNT(*) FROM order_of_payments WHERE encoded_by = u.id AND payment_date BETWEEN ? AND ?) AS op,
                    (SELECT COUNT(*) FROM permit_workflows WHERE encoded_by = u.id AND created_at BETWEEN ? AND ?) AS workflow,
                    (SELECT COUNT(*) FROM permit_approvals WHERE approved_by = u.id AND approval_date BETWEEN ? AND ?) AS approved,
                    (SELECT COUNT(*) FROM releasing_plans WHERE encoded_by = u.id AND date_released BETWEEN ? AND ?) AS releasing
                FROM users u WHERE u.is_active = 1 AND u.is_admin = 0
                ORDER BY u.full_name
            ");
            $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo]);
            $rows = $stmt->fetchAll();

            require_once __DIR__ . '/../includes/XlsxWriter.php';

            $writer = new XlsxWriter('Staff Productivity Report');
            $writer->setMeta([
                'Generated: ' . date('Y-m-d H:i'),
                'Period: ' . ($dateFrom ?: '…') . ' to ' . ($dateTo ?: '…'),
            ]);
            $writer->setHeaders(['User', 'Order of Payment', 'Permit Workflow', 'Permit Approved', 'Releasing', 'Total Transactions']);
            $writer->setColumnFormats([1 => 'int', 2 => 'int', 3 => 'int', 4 => 'int', 5 => 'int']);

            $totals = ['op' => 0, 'workflow' => 0, 'approved' => 0, 'releasing' => 0];
            foreach ($rows as $r) {
                $total = $r['op'] + $r['workflow'] + $r['approved'] + $r['releasing'];
                $writer->addRow([$r['name'], (int)$r['op'], (int)$r['workflow'], (int)$r['approved'], (int)$r['releasing'], (int)$total]);
                $totals['op'] += (int)$r['op'];
                $totals['workflow'] += (int)$r['workflow'];
                $totals['approved'] += (int)$r['approved'];
                $totals['releasing'] += (int)$r['releasing'];
            }
            $grandTotal = array_sum($totals);
            $writer->setSummary(['GRAND TOTAL', $totals['op'], $totals['workflow'], $totals['approved'], $totals['releasing'], $grandTotal]);
            $writer->output('Staff_Productivity_Report_' . date('Y-m-d'));

        /* =====================================================================
           PROFILE (User)
           ===================================================================== */
        case 'profile/view':
            $stmt = $pdo->prepare('SELECT id, full_name, username, email, last_login, created_at FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            jsonResponse(['success' => true, 'data' => $user]);

        case 'profile/update':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $fullName = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            if (!$fullName) jsonResponse(['error' => 'Full name required.'], 422);
            $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')->execute([$fullName, $email, $_SESSION['user_id']]);
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;
            logActivity($_SESSION['user_id'], 'profile_updated', 'Profile updated');
            jsonResponse(['success' => true, 'message' => 'Profile updated.']);

        case 'profile/change-password':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $current = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';

            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!password_verify($current, $user['password_hash'])) jsonResponse(['error' => 'Current password is incorrect.'], 422);
            if (strlen($newPassword) < 6) jsonResponse(['error' => 'New password must be at least 6 characters.'], 422);

            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'password_changed', 'Password changed');
            jsonResponse(['success' => true, 'message' => 'Password updated.']);

        case 'profile/upload-photo':
            requireAuth();
            if (empty($_FILES['photo'])) jsonResponse(['error' => 'No file uploaded.'], 422);
            $file = $_FILES['photo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) jsonResponse(['error' => 'Allowed: jpg, png, gif, webp.'], 422);
            $dest = __DIR__ . '/../uploads/profile_photos/' . $_SESSION['user_id'] . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dest)) jsonResponse(['error' => 'Failed to save file.'], 500);
            $path = 'uploads/profile_photos/' . $_SESSION['user_id'] . '.' . $ext;
            $pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$path, $_SESSION['user_id']]);
            $_SESSION['profile_pic'] = $path;
            logActivity($_SESSION['user_id'], 'profile_photo_updated', 'Profile photo updated');
            jsonResponse(['success' => true, 'path' => $path]);

        /* =====================================================================
           SETTINGS (system_settings)
           ===================================================================== */
        case 'settings/modules':
            requireAdmin();
            $stmt = $pdo->prepare("SELECT setting_key AS `key`, setting_value AS `status` FROM system_settings WHERE setting_key LIKE 'module_%'");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            if (!$rows) {
                $defaultModules = [
                    ['key' => 'op', 'label' => 'Order of Payment', 'desc' => 'Encoding and records of order of payment transactions.', 'status' => 'active'],
                    ['key' => 'workflow', 'label' => 'Permit Workflow', 'desc' => 'Routing and processing of permit applications.', 'status' => 'active'],
                    ['key' => 'approved', 'label' => 'Permit Approved', 'desc' => 'Approved permits and related reporting.', 'status' => 'active'],
                    ['key' => 'releasing', 'label' => 'Releasing', 'desc' => 'Releasing plans and releasing reports.', 'status' => 'active']
                ];
                jsonResponse(['success' => true, 'data' => $defaultModules]);
            }
            $modules = array_map(function($r) {
                return ['key' => str_replace('module_', '', $r['key']), 'status' => $r['status']];
            }, $rows);
            jsonResponse(['success' => true, 'data' => $modules]);

        case 'settings/toggle-module':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $key = $data['key'] ?? '';
            $status = $data['status'] ?? 'active';
            if (!$key) jsonResponse(['error' => 'Module key required.'], 422);
            $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
                ->execute(["module_$key", $status, $status]);
            jsonResponse(['success' => true, 'message' => 'Module status updated.']);

        case 'settings/update':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $fullName = trim($data['full_name'] ?? '');
            $email = trim($data['email'] ?? '');
            if (!$fullName) jsonResponse(['error' => 'Full name required.'], 422);
            $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')->execute([$fullName, $email, $_SESSION['user_id']]);
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;
            logActivity($_SESSION['user_id'], 'settings_updated', 'Account settings updated');
            jsonResponse(['success' => true, 'message' => 'Settings saved.']);

        case 'users/list':
            requireAdmin();
            $stmt = $pdo->query('SELECT u.*, GROUP_CONCAT(up.module_key) AS granted_modules FROM users u LEFT JOIN user_permissions up ON up.user_id = u.id AND up.is_granted = 1 GROUP BY u.id ORDER BY u.created_at DESC');
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'users/toggle-permission':
            requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = (int)($data['user_id'] ?? 0);
            $moduleKey = $data['module_key'] ?? '';
            $granted = !empty($data['is_granted']) ? 1 : 0;
            if (!$userId || !$moduleKey) jsonResponse(['error' => 'User ID and module key required.'], 422);
            $stmt = $pdo->prepare('SELECT id FROM user_permissions WHERE user_id = ? AND module_key = ?');
            $stmt->execute([$userId, $moduleKey]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE user_permissions SET is_granted = ? WHERE user_id = ? AND module_key = ?')->execute([$granted, $userId, $moduleKey]);
            } else {
                $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, ?)')->execute([$userId, $moduleKey, $granted]);
            }
            jsonResponse(['success' => true, 'message' => 'Permission updated.']);

        /* =====================================================================
           WORKFLOW — Export to Excel
           ===================================================================== */
        case 'workflow/export':
            requirePermission('permit-workflow');
            require_once __DIR__ . '/../includes/XlsxWriter.php';

            $search = $_GET['search'] ?? '';
            $where = []; $params = [];
            if ($search) {
                $where[] = '(w.application_no LIKE ? OR w.applicant_name LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%";
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT w.application_no, w.applicant_name, w.current_round, w.status,
                    (SELECT lr.last_in FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_last_in,
                    (SELECT lr.last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_last_out,
                    (SELECT lr.processing_days FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1) AS latest_processing_days,
                    (SELECT COALESCE(SUM(lr2.processing_days), 0) FROM workflow_rounds lr2 WHERE lr2.workflow_id = w.id) AS total_tat
                FROM permit_workflows w
                $whereSql
                ORDER BY w.created_at DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $statusMap = [
                'pending' => 'Pending',
                'in-progress' => 'In Process',
                'completed' => 'Completed',
                'returned' => 'Returned',
                'approved' => 'Approved',
            ];

            $writer = new XlsxWriter('Permit Workflow');
            $subtitle = 'Generated: ' . date('Y-m-d H:i') . ($search ? '   |   Search: "' . $search . '"' : '');
            $writer->setMeta([$subtitle]);
            $writer->setHeaders(['Application No.', 'Applicant', 'Current Round', 'Last In Date', 'Last Out Date', 'Processing Days', 'Current Status', 'TAT (days)']);
            $writer->setColumnFormats([5 => 'int', 7 => 'number']);
            foreach ($data as $r) {
                $writer->addRow([
                    $r['application_no'] ?? '',
                    $r['applicant_name'] ?? '',
                    'Round ' . ($r['current_round'] ?? 1),
                    $r['latest_last_in'] ?? '',
                    $r['latest_last_out'] ?? ($r['latest_last_in'] ? 'In progress' : ''),
                    (int)($r['latest_processing_days'] ?? 0),
                    $statusMap[$r['status'] ?? ''] ?? ($r['status'] ?? ''),
                    $r['total_tat'] ?? 0,
                ]);
            }
            $writer->output('Permit_Workflow_' . date('Y-m-d'));

        /* =====================================================================
           EXPORT — proper .xlsx (or fallback .xls)
           ===================================================================== */
        case 'export/csv':
            requireAuth();
            $table = preg_replace('/[^a-z_]/', '', $_GET['table'] ?? '');
            if (!$table) jsonResponse(['error' => 'Invalid table.'], 422);

            $allowed = ['order_of_payment', 'permit_approval', 'releasing'];
            if (!in_array($table, $allowed)) jsonResponse(['error' => 'Table not allowed.'], 403);

            require_once __DIR__ . '/../includes/XlsxWriter.php';

            $search   = trim($_GET['search'] ?? '');
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo   = $_GET['date_to'] ?? '';

            $map = [
                'order_of_payment' => [
                    'label'      => 'Order of Payment',
                    'alias'      => 'o',
                    'searchCols' => ['transaction_no', 'applicant_name', 'official_receipt_no'],
                    'dateCol'    => 'payment_date',
                    'cols'       => ['Transaction No.', 'Applicant', 'Permit Type', 'Amount', 'Status', 'OR No.', 'Date'],
                    'query'      => "SELECT o.transaction_no, o.applicant_name, o.permit_type, o.amount, o.payment_status, o.official_receipt_no, o.payment_date
                                     FROM order_of_payments o LEFT JOIN users u ON u.id = o.encoded_by {WHERE} ORDER BY o.created_at DESC"
                ],
                'permit_approval' => [
                    'label'      => 'Permit Approval Records',
                    'alias'      => 'p',
                    'searchCols' => ['application_no', 'applicant_name', 'permit_type'],
                    'dateCol'    => 'approval_date',
                    'cols'       => ['App No.', 'Applicant', 'Permit Type', 'Approval Date', 'Approved By'],
                    'query'      => "SELECT p.application_no, p.applicant_name, p.permit_type, p.approval_date, u.full_name AS approved_by
                                     FROM permit_approvals p LEFT JOIN users u ON u.id = p.approved_by {WHERE} ORDER BY p.created_at DESC"
                ],
                'releasing' => [
                    'label'      => 'Releasing Records',
                    'alias'      => 'r',
                    'searchCols' => ['permit_application_no', 'applicant_name'],
                    'dateCol'    => 'date_released',
                    'cols'       => ['Release Date', 'Permit App No.', 'Applicant', 'Claimed By', 'Time Released'],
                    'query'      => "SELECT r.date_released, r.permit_application_no, r.applicant_name, r.claimed_by, r.time_released
                                     FROM releasing_plans r {WHERE} ORDER BY r.created_at DESC"
                ]
            ];

            $cfg = $map[$table];
            $where = []; $params = [];
            if ($search) {
                $likes = array_map(fn($c) => "{$cfg['alias']}.$c LIKE ?", $cfg['searchCols']);
                $where[] = '(' . implode(' OR ', $likes) . ')';
                foreach ($cfg['searchCols'] as $_) { $params[] = "%$search%"; }
            }
            if ($dateFrom) { $where[] = "{$cfg['alias']}.{$cfg['dateCol']} >= ?"; $params[] = $dateFrom; }
            if ($dateTo)   { $where[] = "{$cfg['alias']}.{$cfg['dateCol']} <= ?"; $params[] = $dateTo; }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare(str_replace('{WHERE}', $whereSql, $cfg['query']));
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            $subtitle = 'Generated: ' . date('Y-m-d H:i');
            if ($dateFrom || $dateTo) {
                $subtitle .= '   |   Period: ' . ($dateFrom ?: '…') . ' to ' . ($dateTo ?: '…');
            }
            if ($search) {
                $subtitle .= '   |   Search: "' . $search . '"';
            }

            $writer = new XlsxWriter($cfg['label']);
            $writer->setMeta([$subtitle]);
            $writer->setHeaders($cfg['cols']);
            if ($table === 'order_of_payment') {
                $writer->setColumnFormats([3 => 'currency']);
            }
            foreach ($rows as $row) {
                $writer->addRow($row);
            }
            $writer->output($cfg['label'] . '_' . date('Y-m-d'));

        default:
            jsonResponse(['error' => "Unknown action: $module/$action"], 404);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
