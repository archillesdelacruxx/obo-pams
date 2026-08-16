<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/encryption.php';
define('API_MODE', true);
startSession();

/* Mobile app (React Native) support: authenticate via Bearer token when no session cookie is present. */
if (empty($_SESSION['user_id'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($authHeader === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $hName => $hValue) {
            if (strtolower((string)$hName) === 'authorization') {
                $authHeader = (string)$hValue;
                break;
            }
        }
    }
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
        $pdo = getDB();
        $tStmt = $pdo->prepare('SELECT * FROM api_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1');
        $tStmt->execute([$token]);
        $tokenRow = $tStmt->fetch();
        if ($tokenRow) {
            $uStmt = $pdo->prepare('SELECT id, full_name, username, email, profile_photo, is_active, is_admin, role FROM users WHERE id = ? LIMIT 1');
            $uStmt->execute([$tokenRow['user_id']]);
            $user = $uStmt->fetch();
            if ($user && $user['is_active']) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_pic'] = $user['profile_photo'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                $_SESSION['role'] = $user['role'] ?? 'inspector';
                $_SESSION['logged_in_at'] = time();
                $_SESSION['api_token_auth'] = true;
                $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$tokenRow['id']]);
            }
        }
    }
}

requireAuth();

$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* --------------------------------------------------------------------------
   CSRF + HTTP method enforcement.
   GET requests are read-only. Every state-changing action must be sent via
   POST and — for session-cookie (browser) clients — must carry a valid CSRF
   token. Mobile requests authenticated through a Bearer token are exempt
   (an attacker cannot set the Authorization header cross-origin).
   -------------------------------------------------------------------------- */
$readOnlyRoutes = [
    'op/list', 'op/get',
    'workflow/list', 'workflow/get', 'workflow/export',
    'approval/list',
    'releasing/list',
    'inspection/schedules/list',
    'inspection/template', 'inspection/checklist/get',
    'inspection/sync/pull', 'inspection/ai-status',
    'inspection/stats', 'inspection/history/list', 'inspection/reports/list',
    'notifications/list', 'notifications/unread-count',
    'me/permissions',
    'announcements/list',
    'activity/list',
    'dashboard/overview', 'dashboard/stats', 'dashboard/trends', 'dashboard/staff-summary', 'dashboard/export-csv',
    'profile/view',
    'settings/modules', 'settings/ai-get',
    'users/list',
    'teamleaders/list', 'teamleaders/roster',
    'export/csv',
];
if (!in_array("$module/$action", $readOnlyRoutes, true)) {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed. Use POST for this action.'], 405);
    }
    if (empty($_SESSION['api_token_auth'])) {
        $parsedBody = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($parsedBody['_csrf_token'] ?? '');
        if (!validateCSRFToken($csrf)) {
            jsonResponse(['error' => 'Invalid security token. Please refresh the page and try again.'], 403);
        }
    }
}

/* Read the Groq API key from system_settings, transparently handling keys
   stored in plaintext by older versions. */
function getAiApiKey(PDO $pdo): string {
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute(['ai_api_key']);
    $val = trim((string)$stmt->fetchColumn());
    if ($val === '') return '';
    if (strpos($val, '::') !== false) {
        $dec = decryptData($val);
        if ($dec !== '') return $dec;
    }
    return $val;
}

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

        case 'op/get':
            requirePermission('order-of-payment');
            $opId = (int)($_GET['id'] ?? 0);
            if (!$opId) jsonResponse(['error' => 'ID required.'], 422);
            $stmt = $pdo->prepare('SELECT o.*, u.full_name AS encoded_by_name FROM order_of_payments o LEFT JOIN users u ON u.id = o.encoded_by WHERE o.id = ?');
            $stmt->execute([$opId]);
            $rec = $stmt->fetch();
            if (!$rec) jsonResponse(['error' => 'OP record not found.'], 404);
            jsonResponse(['success' => true, 'data' => $rec]);

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
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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

            $latestInSql = "(SELECT lr.last_in FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $latestOutSql = "(SELECT lr.last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $latestNoOutSql = "(SELECT lr.no_last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $firstInSql = "(SELECT lr3.last_in FROM workflow_rounds lr3 WHERE lr3.workflow_id = w.id ORDER BY lr3.round_number ASC LIMIT 1)";
            $lastOutSql = "(SELECT lr2.last_out FROM workflow_rounds lr2 WHERE lr2.workflow_id = w.id AND lr2.last_out IS NOT NULL AND lr2.no_last_out = 0 ORDER BY lr2.round_number DESC LIMIT 1)";
            $latestDaysSql = businessDaysSqlExpr($latestOutSql, $latestInSql);
            $totalTatSql = businessDaysSqlExpr($lastOutSql, $firstInSql);

            $stmt = $pdo->prepare("
                SELECT w.*, u.full_name AS encoded_by_name,
                    (SELECT COUNT(*) FROM workflow_rounds WHERE workflow_id = w.id) AS round_count,
                    $latestInSql AS latest_last_in,
                    $latestOutSql AS latest_last_out,
                    $latestNoOutSql AS latest_no_last_out,
                    $latestDaysSql AS latest_processing_days,
                    $totalTatSql AS total_tat
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
            $firstIn = $data['first_in'] ?? ($datePaid ?: date('Y-m-d'));
            $stmt = $pdo->prepare('INSERT INTO permit_workflows (permit_no, application_no, applicant_name, project_type, permit_type, assessment_approval, date_paid, released, status, first_in, current_round, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
            $stmt->execute([$permitNo, $applicationNo, $applicantName, $projectType, $permitType, $assessmentApproval, $datePaid, $released, $status, $firstIn, $_SESSION['user_id']]);
            $workflowId = $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO workflow_rounds (workflow_id, round_number, last_in, processing_days) VALUES (?, 1, ?, 0)')
                ->execute([$workflowId, $firstIn]);

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
            if ($firstIn) {
                $pdo->prepare('UPDATE workflow_rounds SET last_in = ? WHERE workflow_id = ? AND round_number = 1')
                    ->execute([$firstIn, $id]);
            }
            logActivity($_SESSION['user_id'], 'workflow_updated', "Updated workflow ID $id");
            jsonResponse(['success' => true, 'message' => 'Workflow updated.']);

        case 'workflow/delete':
            requirePermission('permit-workflow');
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM permit_workflows WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'workflow_deleted', "Deleted workflow ID $id");
            jsonResponse(['success' => true, 'message' => 'Workflow deleted.']);

        case 'workflow/add-round':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $lastIn = $data['last_in'] ?? date('Y-m-d');
            $noLastOut = !empty($data['no_last_out']) ? 1 : 0;
            $lastOut = $noLastOut ? null : ($data['last_out'] ?? null);
            $remarks = trim($data['remarks'] ?? '');

            if (!$workflowId) jsonResponse(['error' => 'Workflow ID required.'], 422);

            $stmt = $pdo->prepare('SELECT MAX(round_number) AS max_round FROM workflow_rounds WHERE workflow_id = ?');
            $stmt->execute([$workflowId]);
            $nextRound = (int)$stmt->fetchColumn() + 1;

            $days = businessDaysBetween($lastIn, $lastOut);

            $pdo->prepare('INSERT INTO workflow_rounds (workflow_id, round_number, last_in, last_out, no_last_out, processing_days, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$workflowId, $nextRound, $lastIn, $lastOut, $noLastOut, $days, $remarks]);
            $pdo->prepare('UPDATE permit_workflows SET current_round = ? WHERE id = ?')
                ->execute([$nextRound, $workflowId]);

logActivity($_SESSION['user_id'], 'workflow_round_added', "Added round $nextRound to workflow ID $workflowId");
            jsonResponse(['success' => true, 'round' => $nextRound, 'message' => "Round $nextRound added."]);

        case 'workflow/update-round':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $roundNumber = (int)($data['round_number'] ?? 0);
            $lastIn = $data['last_in'] ?? null;
            $noLastOut = !empty($data['no_last_out']) ? 1 : 0;
            $lastOut = $noLastOut ? null : ($data['last_out'] ?? null);
            $remarks = trim($data['remarks'] ?? '');
            $processingDays = businessDaysBetween($lastIn, $lastOut);

            if (!$workflowId || !$roundNumber) jsonResponse(['error' => 'Workflow ID and Round Number required.'], 422);

            $pdo->prepare('UPDATE workflow_rounds SET last_in=?, last_out=?, no_last_out=?, processing_days=?, remarks=? WHERE workflow_id=? AND round_number=?')
                ->execute([$lastIn, $lastOut, $noLastOut, $processingDays, $remarks, $workflowId, $roundNumber]);

            $maxRound = $pdo->prepare('SELECT MAX(round_number) AS max_round FROM workflow_rounds WHERE workflow_id = ?');
            $maxRound->execute([$workflowId]);
            $latestRound = (int)$maxRound->fetchColumn();
            $pdo->prepare('UPDATE permit_workflows SET current_round = ? WHERE id = ?')
                ->execute([$latestRound, $workflowId]);

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
            $newStatus = 'Under Review';
            $newStage = 'In Progress';
            $pdo->prepare('UPDATE permit_workflows SET current_round = ?, current_stage = ?, status = ? WHERE id = ?')
                ->execute([$latestRound ?: 1, $newStage, $newStatus, $workflowId]);

            logActivity($_SESSION['user_id'], 'workflow_round_deleted', "Deleted round $roundNumber from workflow ID $workflowId");
            jsonResponse(['success' => true, 'message' => "Round $roundNumber deleted."]);

        case 'workflow/update-status':
            requirePermission('permit-workflow');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $status = normalizeWorkflowStatus($data['status'] ?? '');
            $stage = trim($data['stage'] ?? '');

            if (!$workflowId) jsonResponse(['error' => 'Workflow ID required.'], 422);
            $allowedStatus = ['Pending', 'Under Review', 'Approved', 'Disapproved', 'Released'];
            if (!in_array($status, $allowedStatus, true)) jsonResponse(['error' => 'Invalid status.'], 422);
            $allowedStage = ['Pending', 'In Progress', 'Completed'];
            if ($stage && !in_array($stage, $allowedStage, true)) jsonResponse(['error' => 'Invalid stage.'], 422);

            if ($stage) {
                $pdo->prepare('UPDATE permit_workflows SET status = ?, current_stage = ? WHERE id = ?')
                    ->execute([$status, $stage, $workflowId]);
            } else {
                $pdo->prepare('UPDATE permit_workflows SET status = ? WHERE id = ?')
                    ->execute([$status, $workflowId]);
            }
            logActivity($_SESSION['user_id'], 'workflow_status_updated', "Updated workflow ID $workflowId status to $status");
            jsonResponse(['success' => true, 'message' => "Workflow status set to $status."]);

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

        case 'approval/update':
            requirePermission('permit-approval-encoding');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $appId = (int)($data['id'] ?? 0);
            if (!$appId) jsonResponse(['error' => 'ID required.'], 422);
            $check = $pdo->prepare('SELECT id FROM permit_approvals WHERE id = ?');
            $check->execute([$appId]);
            if (!$check->fetch()) jsonResponse(['error' => 'Approval record not found.'], 404);

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

            $pdo->prepare('UPDATE permit_approvals SET workflow_id=?, application_no=?, applicant_name=?, permit_type=?, approval_date=?, tat=?, bp_no=?, location=?, type_of_occupancy=?, contractor=?, land_others=?, surcharge=?, area=?, line_grade=?, bldg_cost=?, permit_no=?, incharge=?, or_no=?, fees=?, date_paid=?, received_by=?, date_oop=?, date_received=?, date_approved=? WHERE id=?')
                ->execute([$workflowId, $applicationNo, $applicantName, $permitType, $approvalDate, $tat, $bpNo, $location, $typeOfOccupancy, $contractor, $landOthers, $surcharge, $area, $lineGrade, $bldgCost, $permitNo, $incharge, $orNo, $fees, $datePaid, $receivedBy, $dateOop, $dateReceived, $dateApproved, $appId]);
            logActivity($_SESSION['user_id'], 'approval_updated', "Updated approval record ID $appId");
            jsonResponse(['success' => true, 'message' => 'Approval record updated.']);

        case 'approval/delete':
            requirePermission('permit-approval-encoding');
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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

        case 'releasing/update':
            requirePermission('releasing');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $relId = (int)($data['id'] ?? 0);
            if (!$relId) jsonResponse(['error' => 'ID required.'], 422);
            $check = $pdo->prepare('SELECT id FROM releasing_plans WHERE id = ?');
            $check->execute([$relId]);
            if (!$check->fetch()) jsonResponse(['error' => 'Release record not found.'], 404);

            $permitAppNo = trim($data['permit_application_no'] ?? '');
            $applicantName = trim($data['applicant_name'] ?? '');
            $dateReleased = $data['date_released'] ?? date('Y-m-d');
            $claimedBy = trim($data['claimed_by'] ?? '');
            $timeReleased = $data['time_released'] ?? null;

            if (!$permitAppNo || !$applicantName) jsonResponse(['error' => 'Permit Application No and Applicant are required.'], 422);

            $pdo->prepare('UPDATE releasing_plans SET permit_application_no=?, applicant_name=?, date_released=?, claimed_by=?, time_released=? WHERE id=?')
                ->execute([$permitAppNo, $applicantName, $dateReleased, $claimedBy, $timeReleased, $relId]);
            logActivity($_SESSION['user_id'], 'releasing_updated', "Updated release record ID $relId");
            jsonResponse(['success' => true, 'message' => 'Release record updated.']);

        case 'releasing/delete':
            requirePermission('releasing');
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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
            $teamLeader1 = !empty($data['team_leader_1']) ? (int)$data['team_leader_1'] : null;
            $teamLeader2 = !empty($data['team_leader_2']) ? (int)$data['team_leader_2'] : null;

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

                /* Inspectors may only update their own records while the
                   record is still a Draft or Rejected. Reviewers with the
                   inspection-edit module may edit any record. */
                if (!hasPermission('inspection-edit')) {
                    if ((int)$existing['inspector_id'] !== (int)$_SESSION['user_id']) {
                        jsonResponse(['error' => 'You can only edit your own inspection records.'], 403);
                    }
                    if (!in_array($existing['status'], ['Draft', 'Rejected'], true)) {
                        jsonResponse(['error' => 'Only draft or rejected inspection records can be edited.'], 422);
                    }
                }
            }

            $signaturePath = $existing['inspector_signature'] ?? null;
            if ($newSignature) $signaturePath = inspectionSaveSignature($newSignature, 'inspection_signatures');

            if ($action === 'checklist/create') {
                $inspectionNo = nextInspectionNo($pdo);
                if (!$applicationNo) $applicationNo = 'APP-' . $inspectionNo;
                $pdo->prepare('INSERT INTO inspection_records (inspection_no, schedule_id, application_no, permit_no, permit_date_issued, project_title, project_location, owner_representative, contact_number, project_contractor, project_engineer, inspection_team, inspection_date, inspection_type, inspection_result, time_started, time_finished, physical_accomplishment, mech_accomplishment, extra_fields, overall_findings, recommendations, completion_percentage, status, inspector_id, inspector_signature, team_leader_1, team_leader_2, encoded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$inspectionNo, $scheduleId, $applicationNo, $permitNo, $permitDateIssued, $projectTitle, $projectLocation, $ownerRepresentative, $contactNumber, $projectContractor, $projectEngineer, $inspectionTeam, $inspectionDate, $inspectionType, $inspectionResult, $timeStarted, $timeFinished, $physicalAccomplishment, $mechAccomplishment, $extraFields, $overallFindings, $recommendations, $completionPercentage, 'Draft', $_SESSION['user_id'], $signaturePath, $teamLeader1, $teamLeader2, $_SESSION['user_id']]);
                $id = (int)$pdo->lastInsertId();
                logActivity($_SESSION['user_id'], 'inspection_checklist_created', "Created inspection $inspectionNo ($projectTitle)");
            } else {
                $inspectionNo = $existing['inspection_no'];
                if (!$applicationNo) $applicationNo = $existing['application_no'];
                $pdo->prepare('UPDATE inspection_records SET schedule_id=?, application_no=?, permit_no=?, permit_date_issued=?, project_title=?, project_location=?, owner_representative=?, contact_number=?, project_contractor=?, project_engineer=?, inspection_team=?, inspection_date=?, inspection_type=?, inspection_result=?, time_started=?, time_finished=?, physical_accomplishment=?, mech_accomplishment=?, extra_fields=?, overall_findings=?, recommendations=?, completion_percentage=?, inspector_signature=?, team_leader_1=?, team_leader_2=? WHERE id=?')
                    ->execute([$scheduleId, $applicationNo, $permitNo, $permitDateIssued, $projectTitle, $projectLocation, $ownerRepresentative, $contactNumber, $projectContractor, $projectEngineer, $inspectionTeam, $inspectionDate, $inspectionType, $inspectionResult, $timeStarted, $timeFinished, $physicalAccomplishment, $mechAccomplishment, $extraFields, $overallFindings, $recommendations, $completionPercentage, $signaturePath, $teamLeader1, $teamLeader2, $id]);
                logActivity($_SESSION['user_id'], 'inspection_checklist_updated', "Updated inspection ID $id");
            }

            $pdo->prepare('DELETE FROM inspection_results WHERE inspection_id = ?')->execute([$id]);
            $resStmt = $pdo->prepare('INSERT INTO inspection_results (inspection_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $tiExistsStmt = $pdo->prepare('SELECT id FROM inspection_template_items WHERE id = ? LIMIT 1');
            $tiLookupStmt = $pdo->prepare('SELECT id FROM inspection_template_items WHERE category = ? AND item_text = ? LIMIT 1');
            foreach ($results as $r) {
                $cat = trim($r['category'] ?? '');
                $text = trim($r['item_text'] ?? '');
                $ti = (int)($r['template_item_id'] ?? 0);
                if ($ti) {
                    $tiExistsStmt->execute([$ti]);
                    if (!$tiExistsStmt->fetchColumn()) $ti = 0;
                }
                if (!$ti && ($cat !== '' || $text !== '')) {
                    $tiLookupStmt->execute([$cat, $text]);
                    $ti = (int)$tiLookupStmt->fetchColumn();
                }
                if (!$ti && $cat === '' && $text === '') continue;
                $itype = ($r['item_type'] ?? '') === 'checkbox' ? 'checkbox' : 'radio';
                $res = in_array($r['result'] ?? '', ['Pass', 'Fail', 'N/A'], true) ? $r['result'] : 'Pass';
                $rm = trim($r['remarks'] ?? '');
                $resStmt->execute([$id, $ti ?: null, $cat, $text, $itype, $res, $rm]);
            }

            jsonResponse([
                'success' => true,
                'id' => $id,
                'inspection_no' => $inspectionNo,
                'application_no' => $applicationNo,
                'message' => 'Inspection checklist saved.',
            ]);

        case 'inspection/checklist/get':
            requirePermission('inspection-checklist');
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $stmt = $pdo->prepare("SELECT r.*, u.full_name AS inspector_name, rev.full_name AS reviewed_by_name, app.full_name AS approved_by_name, e.full_name AS encoded_by_name, tl1.full_name AS team_leader_1_name, tl1.position AS team_leader_1_position, tl2.full_name AS team_leader_2_name, tl2.position AS team_leader_2_position FROM inspection_records r LEFT JOIN users u ON u.id = r.inspector_id LEFT JOIN users rev ON rev.id = r.reviewed_by LEFT JOIN users app ON app.id = r.approved_by LEFT JOIN users e ON e.id = r.encoded_by LEFT JOIN team_leaders tl1 ON tl1.id = r.team_leader_1 LEFT JOIN team_leaders tl2 ON tl2.id = r.team_leader_2 WHERE r.id = ?");
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

        case 'inspection/ai-status':
            requirePermission('inspection-checklist');
            jsonResponse(['success' => true, 'ai_enabled' => getAiApiKey($pdo) !== '']);

        case 'inspection/remark-ai':
            requirePermission('inspection-checklist');
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $category = trim($data['category'] ?? '');
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            if (!$category) jsonResponse(['error' => 'Category required.'], 422);
            if (!$items) jsonResponse(['error' => 'No checklist items to summarize.'], 422);

            $apiKey = getAiApiKey($pdo);
            if ($apiKey === '') {
                jsonResponse(['success' => true, 'ai_enabled' => false, 'message' => 'AI not configured.']);
            }

            $passed = 0; $failed = 0; $na = 0;
            $lineItems = [];
            foreach ($items as $it) {
                $text = trim($it['item_text'] ?? '');
                $res = $it['result'] ?? 'N/A';
                if ($res === 'Pass') $passed++;
                elseif ($res === 'Fail') $failed++;
                else $na++;
                if ($text !== '') $lineItems[] = '- ' . $text . ' (' . $res . ')';
            }
                $prompt = "You are a building inspector writing the 'Remark/s' section of an on-site ocular inspection checklist. "
                . "Summary: $passed passed, $failed failed, $na not applicable.\n"
                . "Write exactly ONE short sentence summarizing compliance for category \"$category\". "
                . "Mention only notable findings. Example: 'All items passed except lighting fixtures requiring rework.'"
                . " Do not list each item. Keep it to a single short sentence in English.\n\n"
                . "Items:\n" . implode("\n", $lineItems);

            $model = 'llama-3.3-70b-versatile';
            $baseUrl = 'https://api.groq.com/openai/v1';

            try {
                $url = "$baseUrl/chat/completions";
                $payload = ['model' => $model, 'messages' => [['role' => 'system', 'content' => 'You are a concise building inspection assistant.'], ['role' => 'user', 'content' => $prompt]], 'max_tokens' => 60];
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $apiKey"],
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                $raw = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);
                if ($err) {
                    error_log("Groq request failed: $err");
                    jsonResponse(['error' => 'AI provider is unavailable. Please try again.'], 502);
                }
                $decoded = json_decode($raw, true);
                if (isset($decoded['error'])) {
                    error_log("Groq error: " . json_encode($decoded['error']));
                    jsonResponse(['error' => 'AI provider rejected the request.'], 502);
                }
                $summary = $decoded['choices'][0]['message']['content'] ?? '';
                $summary = trim(preg_replace('/\s+/', ' ', (string)$summary));
                if ($summary === '') jsonResponse(['error' => 'AI returned an empty response.'], 502);
                jsonResponse(['success' => true, 'ai_enabled' => true, 'summary' => $summary]);
            } catch (Throwable $e) {
                error_log("AI summary failed: " . $e->getMessage());
                jsonResponse(['error' => 'AI summary failed. Please try again.'], 502);
            }

        case 'inspection/checklist/delete':
            requirePermission('inspection-checklist');
            if (!hasPermission('inspection-delete')) {
                jsonResponse(['error' => 'You do not have permission to delete inspection records.'], 403);
            }
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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
            $path = $signature ? inspectionSaveSignature($signature, 'inspection_signatures') : null;

            $stmt = $pdo->prepare('SELECT inspection_no, status FROM inspection_records WHERE id = ?');
            $stmt->execute([$id]);
            $rec = $stmt->fetch();
            if (!$rec) jsonResponse(['error' => 'Inspection record not found.'], 404);

            if ($action === 'checklist/review') {
                if ($rec['status'] !== 'Under Review') jsonResponse(['error' => 'Record must be Under Review before reviewing.'], 422);
                $pdo->prepare('UPDATE inspection_records SET reviewed_by=?, review_signature=?, review_date=NOW(), review_remarks=?, status=? WHERE id=?')
                    ->execute([$_SESSION['user_id'], $path, $remarks, $remarks ? 'Rejected' : 'Approved', $id]);
                $newStatus = $remarks ? 'Rejected' : 'Approved';
                logActivity($_SESSION['user_id'], 'inspection_reviewed', "Reviewed inspection {$rec['inspection_no']} as $newStatus");
                jsonResponse(['success' => true, 'status' => $newStatus, 'message' => "Inspection $newStatus."]);
            } else {
                if ($rec['status'] !== 'Under Review') jsonResponse(['error' => 'Only Under Review records can be rejected.'], 422);
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
            $imgInfo = @getimagesize($f['tmp_name']);
            if ($imgInfo === false) jsonResponse(['error' => 'The uploaded file is not a valid image.'], 422);
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
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $photoId = (int)($delData['id'] ?? 0);
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

        /* =====================================================================
           INSPECTION SYNC — Pull admin review decisions back to the mobile app
           Returns current status + review/approval fields for the caller's own
           synced records (scoped to the inspector). Admin/editor roles may
           query any record.
           ===================================================================== */
        case 'inspection/sync/pull':
            requirePermission('inspection-checklist');
            $syncData = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = is_array($syncData['ids'] ?? null) ? array_values(array_filter(array_map('intval', $syncData['ids']))) : [];
            if (!$ids) jsonResponse(['success' => true, 'data' => []]);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT r.id, r.inspection_no, r.status, r.updated_at,
                           rev.full_name AS reviewed_by_name, r.review_remarks, r.review_date,
                           app.full_name AS approved_by_name, r.approval_remarks, r.approval_date
                    FROM inspection_records r
                    LEFT JOIN users rev ON rev.id = r.reviewed_by
                    LEFT JOIN users app ON app.id = r.approved_by
                    WHERE r.id IN ($placeholders)";
            $params = $ids;
            if (!hasPermission('inspection-edit')) {
                $sql .= ' AND r.inspector_id = ?';
                $params[] = $_SESSION['user_id'];
            }
            $pullStmt = $pdo->prepare($sql);
            $pullStmt->execute($params);
            jsonResponse(['success' => true, 'data' => $pullStmt->fetchAll()]);

        case 'inspection/stats':
            if (!hasPermission('inspection-checklist') && !hasPermission('inspection-history')) {
                jsonResponse(['error' => 'Forbidden.'], 403);
            }
            $userId = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM inspection_records WHERE inspector_id = ? GROUP BY status");
            $stmt->execute([$userId]);
            $rows = [];
            foreach ($stmt->fetchAll() as $r) $rows[$r['status']] = (int)$r['c'];
            jsonResponse(['success' => true, 'data' => [
                'drafts'       => ($rows['Draft'] ?? 0) + ($rows['Rejected'] ?? 0),
                'under_review' => $rows['Under Review'] ?? 0,
                'done'         => ($rows['Approved'] ?? 0) + ($rows['Completed'] ?? 0),
            ]]);

        case 'inspection/history/list':
            if (!hasPermission('inspection-checklist') && !hasPermission('inspection-history')) {
                jsonResponse(['error' => 'Forbidden.'], 403);
            }
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $inspectorId = (int)($_GET['inspector_id'] ?? 0);

            $where = []; $params = [];
            if ($search) {
                $where[] = '(r.inspection_no LIKE ? OR r.permit_no LIKE ? OR r.application_no LIKE ? OR r.project_title LIKE ? OR r.owner_representative LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($status) { $where[] = 'r.status = ?'; $params[] = $status; }
            if ($inspectorId) { $where[] = 'r.inspector_id = ?'; $params[] = $inspectorId; }
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
            if ($status) {
                $statuses = array_values(array_filter(array_map('trim', explode(',', $status)), function ($s) { return $s !== ''; }));
                if ($statuses) {
                    $where[] = 'r.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
                    foreach ($statuses as $s) $params[] = $s;
                }
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name AS inspector_name, tl1.full_name AS team_leader_1_name, tl2.full_name AS team_leader_2_name
                FROM inspection_records r
                LEFT JOIN users u ON u.id = r.inspector_id
                LEFT JOIN team_leaders tl1 ON tl1.id = r.team_leader_1
                LEFT JOIN team_leaders tl2 ON tl2.id = r.team_leader_2
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
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $_SESSION['user_id']]);
            } else {
                $recordId = (int)($data['record_id'] ?? $_GET['record_id'] ?? 0);
                if ($recordId) {
                    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE module_name = 'announcements' AND record_id = ? AND user_id = ?")->execute([$recordId, $_SESSION['user_id']]);
                }
            }
            jsonResponse(['success' => true]);

        /* =====================================================================
           SELF PERMISSIONS  (drives live sidebar visibility for users)
           ===================================================================== */
        case 'me/permissions':
            $role = $_SESSION['role'] ?? 'inspector';
            $alwaysVisible = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
            if (!empty($_SESSION['is_admin']) && in_array($role, ['developer', 'admin'], true)) {
                $granted = array_merge($alwaysVisible, array_keys(MODULES));
            } else {
                $stmt = $pdo->prepare('SELECT module_key FROM user_permissions WHERE user_id = ? AND is_granted = 1');
                $stmt->execute([$_SESSION['user_id']]);
                $granted = array_values(array_unique(array_merge($alwaysVisible, $stmt->fetchAll(PDO::FETCH_COLUMN))));
            }
            jsonResponse(['success' => true, 'granted' => array_values(array_unique($granted))]);

        /* =====================================================================
           ANNOUNCEMENTS  (table: announcements)
           ===================================================================== */
        case 'announcements/list':
            $stmt = $pdo->prepare('SELECT a.*, u.full_name AS posted_by_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by WHERE a.is_active = 1 ORDER BY a.created_at DESC LIMIT 50');
            $stmt->execute();
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'announcements/create':
            requireSystemAdmin();
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
            requireSystemAdmin();
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
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
            if (!canViewActivityLogs()) jsonResponse(['error' => 'Forbidden.'], 403);
            $scopeRole = getActivityLogScope();
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $userId = (int)($_GET['user_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');
            $module = trim($_GET['activity_module'] ?? '');

            $where = []; $params = [];
            if ($scopeRole) { $where[] = 'u.role = ?'; $params[] = $scopeRole; }
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
            $viewRole = $_SESSION['role'] ?? 'admin';
            $userScope = $viewRole === 'admin' ? " WHERE role = 'admin_aid'" : '';
            $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users' . $userScope)->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_of_payments WHERE payment_date = ?');
            $stmt->execute([$today]);
            $opToday = (int)$stmt->fetchColumn();
            $activeWorkflows = (int)$pdo->query("SELECT COUNT(*) FROM permit_workflows WHERE status NOT IN ('Approved','Disapproved','Released')")->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM permit_approvals WHERE approval_date >= ?');
            $stmt->execute([$monthStart]);
            $approvalsMonth = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM releasing_plans WHERE date_released = ?');
            $stmt->execute([$today]);
            $releasingToday = (int)$stmt->fetchColumn();
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
            requireSystemAdmin();
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
            requireSystemAdmin();
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
            if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'Upload failed.'], 422);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) jsonResponse(['error' => 'Allowed: jpg, png, gif, webp.'], 422);
            $imgInfo = @getimagesize($file['tmp_name']);
            if ($imgInfo === false) jsonResponse(['error' => 'The uploaded file is not a valid image.'], 422);
            $dest = __DIR__ . '/../uploads/profile_photos/pp_' . $_SESSION['user_id'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dir = dirname($dest);
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            if (!move_uploaded_file($file['tmp_name'], $dest)) jsonResponse(['error' => 'Failed to save file.'], 500);
            $path = 'uploads/profile_photos/' . basename($dest);
            $pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$path, $_SESSION['user_id']]);
            $_SESSION['profile_pic'] = $path;
            logActivity($_SESSION['user_id'], 'profile_photo_updated', 'Profile photo updated');
            jsonResponse(['success' => true, 'path' => $path]);

        /* =====================================================================
           SETTINGS (system_settings)
           ===================================================================== */
        case 'settings/modules':
            requireSystemAdmin();
            $moduleLabels = [
                'order-of-payment' => 'Order of Payment',
                'op-records' => 'OP Records',
                'permit-workflow' => 'Permit Workflow',
                'workflow-details' => 'Workflow Details',
                'permit-approval-encoding' => 'Permit Approval Encoding',
                'permit-approval-records' => 'Permit Approval Records',
                'releasing' => 'Releasing Plans',
                'releasing-records' => 'Releasing Records',
                'inspection-checklist' => 'Ocular Inspection Checklist',
                'inspection-reports' => 'Monitoring Reports',
            ];
            $stmt = $pdo->prepare("SELECT setting_key AS `key`, setting_value AS `status` FROM system_settings WHERE setting_key LIKE 'module_%'");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            if (!$rows) {
                $modules = [];
                foreach ($moduleLabels as $k => $v) {
                    $modules[] = ['key' => $k, 'label' => $v, 'status' => 'active'];
                }
                jsonResponse(['success' => true, 'data' => $modules]);
            }
            $modules = array_map(function($r) use ($moduleLabels) {
                $key = str_replace('module_', '', $r['key']);
                return ['key' => $key, 'label' => $moduleLabels[$key] ?? $key, 'status' => $r['status']];
            }, $rows);
            jsonResponse(['success' => true, 'data' => $modules]);

        case 'settings/ai-get':
            requireSystemAdmin();
            jsonResponse(['success' => true, 'data' => ['ai_api_key' => getAiApiKey($pdo)]]);

        case 'settings/ai-save':
            requireSystemAdmin();
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $val = trim((string)($data['ai_api_key'] ?? ''));
            $stored = $val === '' ? '' : encryptData($val);
            $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
                ->execute(['ai_api_key', $stored, $stored]);
            jsonResponse(['success' => true, 'message' => 'AI settings saved.']);

        case 'settings/toggle-module':
            requireSystemAdmin();
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
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $stmt = $pdo->query('SELECT u.id, u.full_name, u.username, u.email, u.is_admin, u.is_active, u.role, u.position, u.created_at, u.last_login, GROUP_CONCAT(up.module_key) AS granted_modules FROM users u LEFT JOIN user_permissions up ON up.user_id = u.id AND up.is_granted = 1 GROUP BY u.id ORDER BY u.created_at DESC');
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'users/toggle-permission':
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = (int)($data['user_id'] ?? 0);
            $moduleKey = $data['module_key'] ?? '';
            $granted = !empty($data['is_granted']) ? 1 : 0;
            if (!$userId || !$moduleKey) jsonResponse(['error' => 'User ID and module key required.'], 422);
            $roleRow = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $roleRow->execute([$userId]);
            $targetRole = $roleRow->fetchColumn();
            if ($userId !== (int)$_SESSION['user_id'] && !canRegisterRole((string)$targetRole)) {
                jsonResponse(['error' => 'You cannot change access for users with this role.'], 403);
            }
            $stmt = $pdo->prepare('SELECT id FROM user_permissions WHERE user_id = ? AND module_key = ?');
            $stmt->execute([$userId, $moduleKey]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE user_permissions SET is_granted = ? WHERE user_id = ? AND module_key = ?')->execute([$granted, $userId, $moduleKey]);
            } else {
                $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, ?)')->execute([$userId, $moduleKey, $granted]);
            }
            jsonResponse(['success' => true, 'message' => 'Permission updated.']);

        case 'users/create':
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $fullName = trim($data['fullName'] ?? $data['full_name'] ?? '');
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'inspector';
            $position = $data['position'] ?? '';
            $modules = $data['modules'] ?? [];
            if (!$fullName || !$username || !$password) jsonResponse(['error' => 'Full name, username, and password required.'], 422);
            if (!in_array($role, ['developer', 'admin', 'admin_aid', 'inspector', 'inspector-admin'])) jsonResponse(['error' => 'Invalid role.'], 422);
            if (!canRegisterRole($role)) jsonResponse(['error' => 'You can only register users with an allowed role.'], 403);
            $roleModules = getRoleModuleKeys($role);
            if ($roleModules !== null) {
                $modules = array_values(array_intersect(is_array($modules) ? $modules : [], $roleModules));
            }
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) jsonResponse(['error' => 'Username already exists.'], 422);
            $isadmin = in_array($role, ['developer', 'admin', 'admin_aid']) ? 1 : 0;
            $pdo->prepare('INSERT INTO users (full_name, username, password_hash, is_admin, role, position) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$fullName, $username, password_hash($password, PASSWORD_DEFAULT), $isadmin, $role, $position]);
            $newId = (int)$pdo->lastInsertId();
            if (!empty($modules) && is_array($modules)) {
                $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, 1)');
                foreach ($modules as $mod) {
                    $permStmt->execute([$newId, $mod]);
                }
            }
            logActivity($_SESSION['user_id'], 'user_created', "Created user: $fullName ($username) role=$role");
            jsonResponse(['success' => true, 'message' => 'User created.']);

        case 'users/update':
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = (int)($data['id'] ?? 0);
            $fullName = trim($data['fullName'] ?? $data['full_name'] ?? '');
            $role = trim((string)($data['role'] ?? ''));
            $position = trim((string)($data['position'] ?? ''));
            $modules = $data['modules'] ?? null;
            if (!$userId || !$fullName) jsonResponse(['error' => 'User ID and full name required.'], 422);

            $targetStmt = $pdo->prepare('SELECT role, is_admin FROM users WHERE id = ?');
            $targetStmt->execute([$userId]);
            $target = $targetStmt->fetch();
            if (!$target) jsonResponse(['error' => 'User not found.'], 404);

            $effectiveRole = $role !== '' ? $role : (string)$target['role'];
            if ($role !== '' && !in_array($role, ['developer', 'admin', 'admin_aid', 'inspector', 'inspector-admin'], true)) {
                jsonResponse(['error' => 'Invalid role.'], 422);
            }
            if ($userId === (int)$_SESSION['user_id']) {
                if ($role !== '' && $role !== (string)$target['role']) {
                    jsonResponse(['error' => 'You cannot change your own role.'], 403);
                }
                $effectiveRole = (string)$target['role'];
            } elseif (!canRegisterRole($effectiveRole)) {
                jsonResponse(['error' => 'You cannot manage users with this role.'], 403);
            }

            $isadmin = in_array($effectiveRole, ['developer', 'admin', 'admin_aid']) ? 1 : 0;
            $pdo->prepare('UPDATE users SET full_name = ?, is_admin = ?, role = ?, position = ? WHERE id = ?')
                ->execute([$fullName, $isadmin, $effectiveRole, $position, $userId]);
            if (is_array($modules)) {
                $roleModules = getRoleModuleKeys($effectiveRole);
                if ($roleModules !== null) {
                    $modules = array_values(array_intersect($modules, $roleModules));
                }
                $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
                $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, 1)');
                foreach ($modules as $mod) {
                    $permStmt->execute([$userId, $mod]);
                }
            }
            logActivity($_SESSION['user_id'], 'user_updated', "Updated user ID $userId: $fullName");
            jsonResponse(['success' => true, 'message' => 'User updated.']);

        case 'users/delete':
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $userId = (int)($data['id'] ?? 0);
            if (!$userId) jsonResponse(['error' => 'User ID required.'], 422);
            if ($userId == $_SESSION['user_id']) jsonResponse(['error' => 'Cannot delete your own account.'], 422);
            $roleRow = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $roleRow->execute([$userId]);
            if (!canRegisterRole((string)$roleRow->fetchColumn())) {
                jsonResponse(['error' => 'You cannot delete users with this role.'], 403);
            }
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM activity_logs WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            logActivity($_SESSION['user_id'], 'user_deleted', "Deleted user ID $userId");
            jsonResponse(['success' => true, 'message' => 'User deleted.']);

        case 'users/reset-password':
            if (!canManageUsers()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $userId = (int)($data['id'] ?? 0);
            if (!$userId) jsonResponse(['error' => 'User ID required.'], 422);
            if ($userId !== (int)$_SESSION['user_id']) {
                $roleRow = $pdo->prepare('SELECT role FROM users WHERE id = ?');
                $roleRow->execute([$userId]);
                if (!canRegisterRole((string)$roleRow->fetchColumn())) {
                    jsonResponse(['error' => 'You cannot reset passwords for users with this role.'], 403);
                }
            }
            $tempPw = bin2hex(random_bytes(4));
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($tempPw, PASSWORD_DEFAULT), $userId]);
            jsonResponse(['success' => true, 'message' => "Password reset. Temporary password: $tempPw"]);

        /* =====================================================================
           TEAM LEADERS  (table: team_leaders)
           ===================================================================== */
        case 'teamleaders/list':
            if (!canManageTeamLeaders()) jsonResponse(['error' => 'Forbidden.'], 403);
            $stmt = $pdo->query('SELECT id, full_name, position, team_no, is_active, created_at FROM team_leaders ORDER BY team_no, full_name');
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'teamleaders/roster':
            $stmt = $pdo->query('SELECT id, full_name, position, team_no FROM team_leaders WHERE is_active = 1 ORDER BY team_no, full_name');
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'teamleaders/create':
            if (!canManageTeamLeaders()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $fullName = trim($data['full_name'] ?? '');
            $position = trim($data['position'] ?? '');
            $teamNo = (int)($data['team_no'] ?? 1);
            if (!in_array($teamNo, [1, 2], true)) $teamNo = 1;
            if (!$fullName) jsonResponse(['error' => 'Full name is required.'], 422);
            $pdo->prepare('INSERT INTO team_leaders (full_name, position, team_no, is_active) VALUES (?, ?, ?, 1)')
                ->execute([$fullName, $position, $teamNo]);
            logActivity($_SESSION['user_id'], 'team_leader_created', "Registered team leader: $fullName (Team $teamNo)");
            jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => 'Team leader registered.']);

        case 'teamleaders/update':
            if (!canManageTeamLeaders()) jsonResponse(['error' => 'Forbidden.'], 403);
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($data['id'] ?? 0);
            $fullName = trim($data['full_name'] ?? '');
            $position = trim($data['position'] ?? '');
            $teamNo = (int)($data['team_no'] ?? 1);
            if (!in_array($teamNo, [1, 2], true)) $teamNo = 1;
            if (!$id || !$fullName) jsonResponse(['error' => 'ID and full name are required.'], 422);
            $pdo->prepare('UPDATE team_leaders SET full_name = ?, position = ?, team_no = ? WHERE id = ?')
                ->execute([$fullName, $position, $teamNo, $id]);
            logActivity($_SESSION['user_id'], 'team_leader_updated', "Updated team leader ID $id");
            jsonResponse(['success' => true, 'message' => 'Team leader updated.']);

        case 'teamleaders/delete':
            if (!canManageTeamLeaders()) jsonResponse(['error' => 'Forbidden.'], 403);
            $delData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($delData['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM team_leaders WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'team_leader_deleted', "Deleted team leader ID $id");
            jsonResponse(['success' => true, 'message' => 'Team leader deleted.']);

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

            $latestInSql = "(SELECT lr.last_in FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $latestOutSql = "(SELECT lr.last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $latestNoOutSql = "(SELECT lr.no_last_out FROM workflow_rounds lr WHERE lr.workflow_id = w.id ORDER BY lr.round_number DESC LIMIT 1)";
            $firstInSql = "(SELECT lr3.last_in FROM workflow_rounds lr3 WHERE lr3.workflow_id = w.id ORDER BY lr3.round_number ASC LIMIT 1)";
            $lastOutSql = "(SELECT lr2.last_out FROM workflow_rounds lr2 WHERE lr2.workflow_id = w.id AND lr2.last_out IS NOT NULL AND lr2.no_last_out = 0 ORDER BY lr2.round_number DESC LIMIT 1)";
            $latestDaysSql = businessDaysSqlExpr($latestOutSql, $latestInSql);
            $totalTatSql = businessDaysSqlExpr($lastOutSql, $firstInSql);

            $stmt = $pdo->prepare("
                SELECT w.application_no, w.applicant_name, w.current_round, w.status,
                    $latestInSql AS latest_last_in,
                    $latestOutSql AS latest_last_out,
                    $latestNoOutSql AS latest_no_last_out,
                    $latestDaysSql AS latest_processing_days,
                    $totalTatSql AS total_tat
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
                    !empty($r['latest_no_last_out']) ? 'No last out date for this round' : ($r['latest_last_out'] ?? ($r['latest_last_in'] ? 'In progress' : '')),
                    !empty($r['latest_no_last_out']) ? null : (int)($r['latest_processing_days'] ?? 0),
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

            $allowed = [
                'order_of_payment' => 'op-records',
                'permit_approval' => 'permit-approval-records',
                'releasing' => 'releasing-records',
            ];
            if (!array_key_exists($table, $allowed)) jsonResponse(['error' => 'Table not allowed.'], 403);
            if (!hasPermission($allowed[$table])) jsonResponse(['error' => 'Forbidden.'], 403);

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
    error_log('API DB error: ' . $e->getMessage());
    jsonResponse(['error' => 'A database error occurred. Please try again.'], 500);
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    jsonResponse(['error' => 'An unexpected error occurred. Please try again.'], 500);
}
