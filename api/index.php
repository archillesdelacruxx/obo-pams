<?php
require_once __DIR__ . '/../includes/auth.php';
startSession();
requireAuth();

$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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
            $status = $data['status'] ?? 'pending';

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
            $status = $data['status'] ?? '';

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
           NOTIFICATIONS  (table: notifications)
           ===================================================================== */
        case 'notifications/list':
            $stmt = $pdo->prepare('SELECT n.*, u.full_name AS sender_name FROM notifications n LEFT JOIN users u ON u.id = n.sender_id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 100');
            $stmt->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);

        case 'notifications/unread-count':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true, 'count' => (int)$stmt->fetchColumn()]);

        case 'notifications/mark-all-read':
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$_SESSION['user_id']]);
            jsonResponse(['success' => true]);

        case 'notifications/mark-read':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $_SESSION['user_id']]);
            }
            jsonResponse(['success' => true]);

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

            logActivity($_SESSION['user_id'], 'announcement_created', "Created announcement: $title");
            jsonResponse(['success' => true, 'message' => 'Announcement posted.']);

        case 'announcements/delete':
            requireAdmin();
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'ID required.'], 422);
            $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
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

            $countStmt = $pdo->query('SELECT COUNT(*) FROM activity_logs');
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name AS user_name
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute();
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);

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
            jsonResponse(['success' => true, 'data' => $stats]);

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
           DASHBOARD EXPORT CSV
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

            $output = fopen('php://temp', 'w+');
            fputcsv($output, ['Name', 'Username', 'OP', 'Workflow', 'Approved', 'Releasing', 'Total']);
            foreach ($rows as $r) {
                $total = $r['op'] + $r['workflow'] + $r['approved'] + $r['releasing'];
                fputcsv($output, [$r['name'], $r['username'], $r['op'], $r['workflow'], $r['approved'], $r['releasing'], $total]);
            }
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            jsonResponse(['success' => true, 'csv' => $csv]);

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
                SELECT w.*, u.full_name AS encoded_by_name,
                    (SELECT COALESCE(SUM(lr2.processing_days), 0) FROM workflow_rounds lr2 WHERE lr2.workflow_id = w.id) AS total_tat
                FROM permit_workflows w
                LEFT JOIN users u ON u.id = w.encoded_by
                $whereSql
                ORDER BY w.created_at DESC
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $writer = new XlsxWriter('Permit Workflow');
            $writer->setHeaders(['Application No.', 'Applicant', 'Project Type', 'Permit Type', 'Assessment Approval', 'Date Paid', 'Released', 'Status', 'Current Round', 'First In', 'Encoded By', 'TAT (days)']);
            foreach ($data as $r) {
                $writer->addRow([
                    $r['application_no'] ?? '',
                    $r['applicant_name'] ?? '',
                    $r['project_type'] ?? '',
                    $r['permit_type'] ?? '',
                    $r['assessment_approval'] ?? '',
                    $r['date_paid'] ?? '',
                    $r['released'] ?? '',
                    $r['status'] ?? '',
                    $r['current_round'] ?? '',
                    $r['first_in'] ?? '',
                    $r['encoded_by_name'] ?? '',
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
                    'cols'       => ['Transaction No.', 'Applicant', 'Permit Type', 'Amount (PHP)', 'Status', 'OR No.', 'Payment Date'],
                    'query'      => "SELECT o.transaction_no, o.applicant_name, o.permit_type, o.amount, o.payment_status, o.official_receipt_no, o.payment_date
                                     FROM order_of_payments o LEFT JOIN users u ON u.id = o.encoded_by {WHERE} ORDER BY o.created_at DESC"
                ],
                'permit_approval' => [
                    'label'      => 'Permit Approval Records',
                    'alias'      => 'p',
                    'searchCols' => ['application_no', 'applicant_name', 'permit_type'],
                    'dateCol'    => 'approval_date',
                    'cols'       => ['App No.', 'Applicant', 'Permit Type', 'BP#', 'Location', 'Type of Occupancy', 'Fees (PHP)', 'OR No.', 'Date Paid', 'Approval Date', 'TAT (days)', 'Approved By'],
                    'query'      => "SELECT p.application_no, p.applicant_name, p.permit_type, p.bp_no, p.location, p.type_of_occupancy, p.fees, p.or_no, p.date_paid, p.approval_date, p.tat, u.full_name AS approved_by
                                     FROM permit_approvals p LEFT JOIN users u ON u.id = p.approved_by {WHERE} ORDER BY p.created_at DESC"
                ],
                'releasing' => [
                    'label'      => 'Releasing Records',
                    'alias'      => 'r',
                    'searchCols' => ['permit_application_no', 'applicant_name'],
                    'dateCol'    => 'date_released',
                    'cols'       => ['App No.', 'Applicant', 'Claimed By', 'Date Released', 'Time Released', 'Released By'],
                    'query'      => "SELECT r.permit_application_no, r.applicant_name, r.claimed_by, r.date_released, r.time_released, u.full_name AS released_by_name
                                     FROM releasing_plans r LEFT JOIN users u ON u.id = r.encoded_by {WHERE} ORDER BY r.created_at DESC"
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

            $writer = new XlsxWriter($cfg['label']);
            $writer->setMeta([$cfg['label'], $subtitle]);
            $writer->setHeaders($cfg['cols']);
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
