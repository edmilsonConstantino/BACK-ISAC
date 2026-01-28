<?php
// ✅ CORS
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/AuthMiddleware.php';

AuthMiddleware::validate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

function badRequest($msg) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

function isValidDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

function toMonthRef($date) {
    return substr($date, 0, 7); // YYYY-MM
}

function addMonthsToDate($ymd, $monthsToAdd) {
    $dt = new DateTime($ymd);
    $dt->modify('first day of this month');
    $dt->modify("+{$monthsToAdd} month");
    return $dt->format('Y-m-d');
}

function buildDueDate($monthRef, $dueDay) {
    // monthRef: YYYY-MM
    $day = str_pad(strval($dueDay), 2, '0', STR_PAD_LEFT);
    return "{$monthRef}-{$day}";
}

function calcLateFeeMultiplier($monthReference, $todayYmd) {
    // Retorna 1.0 (sem multa), 1.1 (10%), 1.2 (20%)
    // Regras:
    // - Se hoje <= dia 10 do mês: sem multa
    // - Se hoje entre 11..20: 10%
    // - Se hoje >= 21: 20%

    $day = intval(substr($todayYmd, 8, 2));
    $todayMonth = substr($todayYmd, 0, 7);

    // Só aplica multa se estivermos no mesmo mês do plano OU depois dele
    // Se o plano é de um mês futuro, não aplica multa
    if ($todayMonth < $monthReference) return 1.0;

    // Se hoje está em mês posterior ao plano, considera como >= 21 (multas máximas)
    if ($todayMonth > $monthReference) return 1.2;

    // Mesmo mês:
    if ($day <= 10) return 1.0;
    if ($day <= 20) return 1.1;
    return 1.2;
}

function calcAmountDueWithFees($baseAmount, $monthReference, $todayYmd) {
    $mult = calcLateFeeMultiplier($monthReference, $todayYmd);
    // multa é em cima do valor base
    return round($baseAmount * $mult, 2);
}

try {
    // ============================
    // 1) Resolver origem: registration_id OU student_id+curso_id
    // ============================
    $studentId = null;
    $cursoId = null;
    $startDate = null; // enrollment_date como base

    if (!empty($data->registration_id)) {
        $registrationId = intval($data->registration_id);

        $stReg = $db->prepare("SELECT id, student_id, course_id, enrollment_date 
                               FROM registrations 
                               WHERE id = :id");
        $stReg->execute([':id' => $registrationId]);
        $reg = $stReg->fetch(PDO::FETCH_ASSOC);

        if (!$reg) badRequest("Registration not found");

        $studentId = intval($reg['student_id']);
        $cursoId = $reg['course_id'];
        $startDate = $reg['enrollment_date'];

        if (empty($startDate) || !isValidDate($startDate)) {
            // fallback seguro: hoje
            $startDate = date('Y-m-d');
        }
    } else {
        if (empty($data->student_id)) badRequest("student_id is required (or provide registration_id)");
        if (empty($data->curso_id)) badRequest("curso_id is required (or provide registration_id)");

        $studentId = intval($data->student_id);
        $cursoId = $data->curso_id;

        // pode enviar start_date no payload, se não vier usa hoje
        $startDate = (!empty($data->start_date) && isValidDate($data->start_date))
            ? $data->start_date
            : date('Y-m-d');
    }

    // due_day opcional (1..28)
    $dueDay = 10;

    $stStudent = $db->prepare("SELECT COUNT(*) FROM students WHERE id = :id");
    $stStudent->execute([':id' => $studentId]);
    if (intval($stStudent->fetchColumn()) === 0) badRequest("Student not found");

    $stCourse = $db->prepare("SELECT COUNT(*) FROM cursos WHERE codigo = :c AND status = 'ativo'");
    $stCourse->execute([':c' => $cursoId]);
    if (intval($stCourse->fetchColumn()) === 0) badRequest("Course not found or inactive");

    // ============================
    // 3) Buscar course_fees (mensalidade e meses_total)
    // ============================
    $stFees = $db->prepare("SELECT mensalidade_valor, meses_total, ativo 
                            FROM course_fees 
                            WHERE curso_id = :c");
    $stFees->execute([':c' => $cursoId]);
    $fees = $stFees->fetch(PDO::FETCH_ASSOC);

    if (!$fees) badRequest("Course fees not configured for this course (course_fees missing)");
    if (intval($fees['ativo']) !== 1) badRequest("Course fees are inactive for this course");

    $mensalidadeValor = floatval($fees['mensalidade_valor']);
    $mesesTotal = intval($fees['meses_total']);
    if ($mesesTotal <= 0) badRequest("Invalid meses_total in course_fees");

    // ============================
    // 4) Gerar meses (YYYY-MM) desde startDate
    // ============================
    $startMonthRef = toMonthRef($startDate);

    $db->beginTransaction();

    $created = 0;
    $skipped = 0;
    $updated = 0;

    $months = [];

    for ($i = 0; $i < $mesesTotal; $i++) {
        // mês i a partir do startDate
        $monthDate = addMonthsToDate($startDate, $i);        // YYYY-MM-01
        $monthRef = toMonthRef($monthDate);                 // YYYY-MM
        $dueDate = buildDueDate($monthRef, $dueDay);
        $amountDue = calcAmountDueWithFees($mensalidadeValor, $monthRef, date('Y-m-d'));

        // 4.1) Inserir plano se não existir
        // Unique: (student_id, curso_id, month_reference)
        $stInsert = $db->prepare("
            INSERT INTO student_payment_plans
                (student_id, curso_id, month_reference, due_date, amount_due, status, payment_id, observacoes)
            VALUES
                (:s, :c, :m, :due, :amt, 'pending', NULL, NULL)
            ON DUPLICATE KEY UPDATE
                due_date = VALUES(due_date),
                amount_due = VALUES(amount_due)
        ");

        $stInsert->execute([
            ':s' => $studentId,
            ':c' => $cursoId,
            ':m' => $monthRef,
            ':due' => $dueDate,
            ':amt' => $amountDue
        ]);

        // rowCount() no MySQL com ON DUPLICATE pode variar:
        // - 1: inseriu
        // - 2: atualizou
        // - 0: nada (se valores iguais)
        $rc = $stInsert->rowCount();
        if ($rc === 1) $created++;
        else if ($rc === 2) $updated++;
        else $skipped++;

        $months[] = [
            "month_reference" => $monthRef,
            "due_date" => $dueDate,
            "amount_due" => $mensalidadeValor
        ];
    }

    // ============================
    // 5) Recalcular status do plano com base em pagamentos existentes
    // ============================
    // Para cada mês do plano: somar pagamentos e atualizar status/payment_id
    $today = date('Y-m-d');

    $stPlanRows = $db->prepare("
        SELECT id, month_reference, due_date, amount_due
        FROM student_payment_plans
        WHERE student_id = :s AND curso_id = :c
          AND month_reference >= :start_month
        ORDER BY month_reference ASC
    ");
    $stPlanRows->execute([
        ':s' => $studentId,
        ':c' => $cursoId,
        ':start_month' => $startMonthRef
    ]);

    $plans = $stPlanRows->fetchAll(PDO::FETCH_ASSOC);

    $stSumPaid = $db->prepare("
        SELECT 
          COALESCE(SUM(amount_paid),0) AS total_paid,
          MAX(id) AS last_payment_id
        FROM student_payments
        WHERE student_id = :s
          AND curso_id = :c
          AND month_reference = :m
          AND status != 'reversed'
    ");

    $stUpdatePlan = $db->prepare("
        UPDATE student_payment_plans
        SET status = :st, payment_id = :pid
        WHERE id = :id
    ");

    foreach ($plans as $p) {
        $planId = intval($p['id']);
        $mref = $p['month_reference'];
        $due = $p['due_date'];
        $dueAmount = floatval($p['amount_due']);

        $stSumPaid->execute([
            ':s' => $studentId,
            ':c' => $cursoId,
            ':m' => $mref
        ]);

        $sum = $stSumPaid->fetch(PDO::FETCH_ASSOC);
        $totalPaid = floatval($sum['total_paid']);
        $lastPaymentId = !empty($sum['last_payment_id']) ? intval($sum['last_payment_id']) : null;

        $newStatus = 'pending';

        if ($dueAmount > 0 && $totalPaid >= $dueAmount) {
            $newStatus = 'paid';
        } else if ($totalPaid > 0 && $dueAmount > 0 && $totalPaid < $dueAmount) {
            $newStatus = 'partial';
        } else {
            // nada pago
            $newStatus = ($due < $today) ? 'overdue' : 'pending';
        }

        // Atualizar
        $stUpdatePlan->execute([
            ':st' => $newStatus,
            ':pid' => $lastPaymentId,
            ':id' => $planId
        ]);
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Student payment plan generated successfully",
        "student_id" => $studentId,
        "curso_id" => $cursoId,
        "start_date" => $startDate,
        "due_day" => $dueDay,
        "months_total" => $mesesTotal,
        "created" => $created,
        "updated" => $updated,
        "skipped" => $skipped,
        "months" => $months
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
