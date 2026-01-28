<?php
/**
 * STUDENT PAYMENTS - CREATE
 * 📁 LOCATION: api/student-payments/create.php
 *
 * POST body (JSON):
 *  - payment_type_id (required)
 *  - amount_paid (required > 0)
 *  - paid_date (optional, YYYY-MM-DD) default today
 *  - month_reference (optional, YYYY-MM) -> if present, we treat as monthly and update plan
 *  - student_id + curso_id OR registration_id
 *  - receipt_number (optional)
 *  - observacoes (optional)
 *
 * Flags (optional):
 *  - is_enrollment_fee (boolean) -> enforce one-time per student+curso+payment_type_id
 *  - monthly_plan_apply (boolean) -> forces updating the monthly plan even if you want
 */

// ✅ CORS (ajusta origin se precisares)
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

/**
 * Helpers
 */
function badRequest($msg) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

function serverError($msg) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

function isValidMonthRef($value) {
    return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value);
}

function normalizeDate($value) {
    if (!empty($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    return date('Y-m-d');
}

try {
    // ==============================
    // 1) Validar inputs mínimos
    // ==============================
    if (empty($data->payment_type_id)) badRequest("payment_type_id is required");
    if (!isset($data->amount_paid) || floatval($data->amount_paid) <= 0) badRequest("amount_paid must be > 0");

    $paymentTypeId = intval($data->payment_type_id);
    $amountPaid = floatval($data->amount_paid);
    $paidDate = normalizeDate($data->paid_date ?? null);

    // month_reference é opcional:
    // - se existir => mensalidade + atualiza plano
    // - se não existir => matrícula (uma vez) e NÃO atualiza plano (por padrão)
    $monthReference = $data->month_reference ?? null;
    if (!empty($monthReference) && !isValidMonthRef($monthReference)) {
        badRequest("Invalid month_reference. Use YYYY-MM (e.g., 2026-03)");
    }

    // receipt/observações (opcional)
    $receiptNumber = $data->receipt_number ?? null;
    $observacoes = $data->observacoes ?? null;

    // Flags
    $isEnrollmentFee = !empty($data->is_enrollment_fee) ? true : false;
    $monthlyPlanApply = !empty($data->monthly_plan_apply) ? true : false;

    // ==============================
    // 2) Resolver student_id e curso_id
    // ==============================
    $studentId = null;
    $cursoId = null;

    if (!empty($data->registration_id)) {
        $registrationId = intval($data->registration_id);

        $qReg = "SELECT student_id, course_id FROM registrations WHERE id = :id";
        $stReg = $db->prepare($qReg);
        $stReg->execute([':id' => $registrationId]);

        $reg = $stReg->fetch(PDO::FETCH_ASSOC);
        if (!$reg) badRequest("Registration not found");

        $studentId = intval($reg['student_id']);
        $cursoId = $reg['course_id'];
    } else {
        if (empty($data->student_id)) badRequest("student_id is required (or provide registration_id)");
        if (empty($data->curso_id)) badRequest("curso_id is required (or provide registration_id)");

        $studentId = intval($data->student_id);
        $cursoId = trim($data->curso_id);
    }

    // ==============================
    // 3) Validar FK básicas (student / curso / payment_type)
    // ==============================
    $stCheckStudent = $db->prepare("SELECT COUNT(*) FROM students WHERE id = :id");
    $stCheckStudent->execute([':id' => $studentId]);
    if (intval($stCheckStudent->fetchColumn()) === 0) badRequest("Student not found");

    $stCheckCourse = $db->prepare("SELECT COUNT(*) FROM cursos WHERE codigo = :c AND status = 'ativo'");
    $stCheckCourse->execute([':c' => $cursoId]);
    if (intval($stCheckCourse->fetchColumn()) === 0) badRequest("Course not found or inactive");

    $stCheckType = $db->prepare("SELECT COUNT(*) FROM payment_types WHERE id = :id");
    $stCheckType->execute([':id' => $paymentTypeId]);
    if (intval($stCheckType->fetchColumn()) === 0) badRequest("Invalid payment_type_id");

    // ==============================
    // 4) Buscar valores em course_fees
    // ==============================
    $stFees = $db->prepare("
        SELECT matricula_valor, mensalidade_valor, meses_total, ativo
        FROM course_fees
        WHERE curso_id = :c
    ");
    $stFees->execute([':c' => $cursoId]);
    $fees = $stFees->fetch(PDO::FETCH_ASSOC);

    if (!$fees) badRequest("Course fees not configured for this course (course_fees missing)");
    if (intval($fees['ativo']) !== 1) badRequest("Course fees are inactive for this course");

    $mensalidadeValor = floatval($fees['mensalidade_valor']);
    $matriculaValor = floatval($fees['matricula_valor']);

    // Se é mensalidade (month_reference no payload), amount_due = mensalidade_valor.
    // Se é matrícula (sem month_reference), amount_due = matricula_valor (pago uma vez).
    $isMonthlyPayment = !empty($monthReference);
    $amountDue = $isMonthlyPayment ? $mensalidadeValor : $matriculaValor;

    // status do pagamento individual
    $paymentStatus = ($amountPaid >= $amountDue && $amountDue > 0) ? 'paid' : 'partial';

    // month_reference é NOT NULL na tabela student_payments.
    // Então:
    // - se não veio month_reference (matrícula), usamos o mês do paid_date (YYYY-MM)
    if (empty($monthReference)) {
        $monthReference = substr($paidDate, 0, 7);
    }

    // ==============================
    // 5) Transação: inserir payment + atualizar plano (se for mensalidade)
    // ==============================
    $db->beginTransaction();

    // (A) Regra: matrícula deve ser paga apenas uma vez por student+curso+payment_type_id
    // Não vamos adivinhar qual payment_type_id é "matrícula".
    // Só aplica se o frontend enviar is_enrollment_fee=true.
    if ($isEnrollmentFee) {
        $stOnce = $db->prepare("
            SELECT COUNT(*)
            FROM student_payments
            WHERE student_id = :s
              AND curso_id = :c
              AND payment_type_id = :pt
              AND status <> 'reversed'
        ");
        $stOnce->execute([':s' => $studentId, ':c' => $cursoId, ':pt' => $paymentTypeId]);

        if (intval($stOnce->fetchColumn()) > 0) {
            $db->rollBack();
            badRequest("Enrollment fee already paid for this student in this course");
        }
    }

    // Inserir pagamento
    $qIns = "
        INSERT INTO student_payments
        (student_id, curso_id, month_reference, amount_paid, payment_type_id, status, paid_date, receipt_number, observacoes)
        VALUES
        (:student_id, :curso_id, :month_reference, :amount_paid, :payment_type_id, :status, :paid_date, :receipt_number, :observacoes)
    ";
    $stIns = $db->prepare($qIns);

    $stIns->execute([
        ':student_id' => $studentId,
        ':curso_id' => $cursoId,
        ':month_reference' => $monthReference,
        ':amount_paid' => $amountPaid,
        ':payment_type_id' => $paymentTypeId,
        ':status' => ($paymentStatus === 'paid') ? 'paid' : 'partial',
        ':paid_date' => $paidDate,
        ':receipt_number' => $receiptNumber,
        ':observacoes' => $observacoes
    ]);

    $paymentId = intval($db->lastInsertId());

    // (B) Atualizar plano mensal:
    // ✅ Só atualiza se for claramente mensalidade:
    // - veio month_reference no payload (isMonthlyPayment)
    // OU monthly_plan_apply=true
    $shouldUpdatePlan = ($isMonthlyPayment || $monthlyPlanApply);

    if ($shouldUpdatePlan) {
        // 1) Garantir que existe a linha no plano
        $stPlanExists = $db->prepare("
            SELECT id, due_date, amount_due
            FROM student_payment_plans
            WHERE student_id = :s AND curso_id = :c AND month_reference = :m
            LIMIT 1
        ");
        $stPlanExists->execute([':s' => $studentId, ':c' => $cursoId, ':m' => $monthReference]);
        $plan = $stPlanExists->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            // ✅ due_date = dia 10 (regra do teu sistema)
            $dueDate = $monthReference . "-10";
            $amountDuePlan = $mensalidadeValor;

            $stPlanIns = $db->prepare("
                INSERT INTO student_payment_plans
                (student_id, curso_id, month_reference, due_date, amount_due, status, payment_id, observacoes)
                VALUES
                (:s, :c, :m, :due, :due_amount, 'pending', :pid, NULL)
            ");
            $stPlanIns->execute([
                ':s' => $studentId,
                ':c' => $cursoId,
                ':m' => $monthReference,
                ':due' => $dueDate,
                ':due_amount' => $amountDuePlan,
                ':pid' => $paymentId
            ]);

            $planId = intval($db->lastInsertId());
            $planDueDate = $dueDate;
            $planAmountDue = $amountDuePlan;
        } else {
            $planId = intval($plan['id']);
            $planDueDate = $plan['due_date'];
            $planAmountDue = floatval($plan['amount_due']);
        }

        // 2) Somar total pago no mês (ignorar reversed)
        $stSum = $db->prepare("
            SELECT COALESCE(SUM(amount_paid),0) AS total_paid
            FROM student_payments
            WHERE student_id = :s
              AND curso_id = :c
              AND month_reference = :m
              AND status <> 'reversed'
        ");
        $stSum->execute([':s' => $studentId, ':c' => $cursoId, ':m' => $monthReference]);
        $totalPaidMonth = floatval(($stSum->fetch(PDO::FETCH_ASSOC)['total_paid']) ?? 0);

        // 3) Definir status do plano (sem multa aqui; multa é calculada no index/listagem)
        $today = date('Y-m-d');
        $newPlanStatus = 'pending';

        if ($totalPaidMonth >= $planAmountDue && $planAmountDue > 0) {
            $newPlanStatus = 'paid';
        } else if ($totalPaidMonth > 0 && $totalPaidMonth < $planAmountDue) {
            $newPlanStatus = 'partial';
        } else {
            $newPlanStatus = ($planDueDate < $today) ? 'overdue' : 'pending';
        }

        // 4) Atualizar plano (apontar pro último pagamento realizado)
        $stPlanUpd = $db->prepare("
            UPDATE student_payment_plans
            SET status = :st, payment_id = :pid
            WHERE id = :id
        ");
        $stPlanUpd->execute([
            ':st' => $newPlanStatus,
            ':pid' => $paymentId,
            ':id' => $planId
        ]);
    }

    $db->commit();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Payment created successfully",
        "payment_id" => $paymentId,
        "student_id" => $studentId,
        "curso_id" => $cursoId,
        "month_reference" => $monthReference,
        "status" => $paymentStatus,
        "updated_plan" => $shouldUpdatePlan
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();

    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Duplicate record: " . $e->getMessage()
        ]);
        exit;
    }

    serverError("Error: " . $e->getMessage());
}
