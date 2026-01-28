<?php
/**
 * STUDENT PAYMENT PLANS - INDEX / LIST + PENALTIES (computed)
 * 📁 LOCATION: api/student-payment-plans/index.php
 *
 * GET params (opcionais):
 *  - student_id
 *  - curso_id
 *  - month_reference (YYYY-MM)
 *  - status (pending|paid|overdue|partial)
 *  - registration_id
 */

// ✅ CORS
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/AuthMiddleware.php';

AuthMiddleware::validate();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

/**
 * Helpers
 */
function badRequest($msg) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

function isValidMonthRef($value) {
    return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value);
}

/**
 * Multa:
 * - depois do dia 10: +10% do base
 * - depois do dia 20: +10% adicional do base
 * Observação: só aplica se ainda não está paid.
 */
function computePenalty($baseAmount, $dueDate, $status) {
    $base = floatval($baseAmount);
    if ($base <= 0) return 0.00;

    // Se já está pago, não tem multa
    if ($status === 'paid') return 0.00;

    $today = new DateTime(date('Y-m-d'));
    $due = new DateTime($dueDate);

    // Se ainda não venceu, 0
    if ($today <= $due) return 0.00;

    // dia do mês atual (pra regra 10/20)
    $day = intval($today->format('d'));

    $penalty = 0.00;

    if ($day > 10) {
        $penalty += $base * 0.10;
    }

    if ($day > 20) {
        $penalty += $base * 0.10;
    }

    // arredondar 2 casas
    return round($penalty, 2);
}

/**
 * Total pago no mês (somando payments, ignorando reversed)
 */
function getTotalPaidForMonth(PDO $db, $studentId, $cursoId, $monthReference) {
    $st = $db->prepare("
        SELECT COALESCE(SUM(amount_paid),0) AS total_paid
        FROM student_payments
        WHERE student_id = :s
          AND curso_id = :c
          AND month_reference = :m
          AND status != 'reversed'
    ");
    $st->execute([
        ':s' => $studentId,
        ':c' => $cursoId,
        ':m' => $monthReference
    ]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return floatval($row['total_paid'] ?? 0);
}

try {
    // ==============================
    // 1) Filtros
    // ==============================
    $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    $cursoId = $_GET['curso_id'] ?? null;
    $monthReference = $_GET['month_reference'] ?? null;
    $status = $_GET['status'] ?? null;
    $registrationId = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : null;

    if ($monthReference && !isValidMonthRef($monthReference)) {
        badRequest("Invalid month_reference. Use YYYY-MM");
    }

    if ($status && !in_array($status, ['pending','paid','overdue','partial'], true)) {
        badRequest("Invalid status. Use pending|paid|overdue|partial");
    }

    // ==============================
    // 2) Query base
    // ==============================
    $sql = "
        SELECT
            spp.id,
            spp.student_id,
            s.name AS student_name,

            spp.curso_id,
            c.nome AS course_name,

            spp.month_reference,
            spp.due_date,
            spp.amount_due,
            spp.status,
            spp.payment_id,
            spp.observacoes,
            spp.data_criacao,
            spp.data_atualizacao

        FROM student_payment_plans spp
        INNER JOIN students s ON s.id = spp.student_id
        INNER JOIN cursos c ON c.codigo = spp.curso_id
        WHERE 1=1
    ";

    $params = [];

    // ==============================
    // 3) Aplicar filtros
    // ==============================
    if ($studentId) {
        $sql .= " AND spp.student_id = :student_id";
        $params[':student_id'] = $studentId;
    }

    if ($cursoId) {
        $sql .= " AND spp.curso_id = :curso_id";
        $params[':curso_id'] = $cursoId;
    }

    if ($monthReference) {
        $sql .= " AND spp.month_reference = :month_reference";
        $params[':month_reference'] = $monthReference;
    }

    if ($status) {
        $sql .= " AND spp.status = :status";
        $params[':status'] = $status;
    }

    if ($registrationId) {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM registrations r
                WHERE r.student_id = spp.student_id
                  AND r.course_id = spp.curso_id
                  AND r.id = :registration_id
            )
        ";
        $params[':registration_id'] = $registrationId;
    }

    $sql .= " ORDER BY spp.month_reference ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==============================
    // 4) Enriquecer com multas e saldos
    // ==============================
    $todayStr = date('Y-m-d');
    $today = new DateTime($todayStr);

    $data = [];

    foreach ($rows as $r) {
        $baseDue = floatval($r['amount_due']);
        $dueDate = $r['due_date'];
        $planStatus = $r['status'];

        // total pago do mês (se status estiver inconsistente, isto ajuda)
        $totalPaid = getTotalPaidForMonth($db, intval($r['student_id']), $r['curso_id'], $r['month_reference']);

        // saldo base (sem multa)
        $baseRemaining = max($baseDue - $totalPaid, 0);

        // multa só aplica se ainda existe saldo
        $penalty = 0.00;
        if ($baseRemaining > 0) {
            $penalty = computePenalty($baseDue, $dueDate, $planStatus);
        }

        $totalDueWithPenalty = round($baseRemaining + $penalty, 2);

        // dias em atraso (se houver)
        $daysOverdue = 0;
        $due = new DateTime($dueDate);
        if ($today > $due && $baseRemaining > 0) {
            $diff = $due->diff($today);
            $daysOverdue = intval($diff->days);
        }

        // status computado (sem gravar BD)
        // regra: se pagou tudo => paid
        // se pagou algo mas falta => partial
        // se não pagou e venceu => overdue
        // senão pending
        $computedStatus = 'pending';
        if ($totalPaid >= $baseDue && $baseDue > 0) {
            $computedStatus = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $baseDue) {
            $computedStatus = 'partial';
        } else {
            $computedStatus = ($today > $due && $baseRemaining > 0) ? 'overdue' : 'pending';
        }

        $data[] = [
            // dados do plano
            "id" => intval($r['id']),
            "student_id" => intval($r['student_id']),
            "student_name" => $r['student_name'],
            "curso_id" => $r['curso_id'],
            "course_name" => $r['course_name'],
            "month_reference" => $r['month_reference'],
            "due_date" => $dueDate,
            "amount_due" => round($baseDue, 2),
            "status" => $planStatus,
            "computed_status" => $computedStatus,
            "payment_id" => $r['payment_id'] ? intval($r['payment_id']) : null,
            "observacoes" => $r['observacoes'],
            "data_criacao" => $r['data_criacao'],
            "data_atualizacao" => $r['data_atualizacao'],

            // calculados
            "total_paid" => round($totalPaid, 2),
            "base_remaining" => round($baseRemaining, 2),
            "penalty" => round($penalty, 2),
            "total_due_with_penalty" => $totalDueWithPenalty,
            "days_overdue" => $daysOverdue,
            "today" => $todayStr
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "total" => count($data),
        "data" => $data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching payment plans",
        "error" => $e->getMessage()
    ]);
}
