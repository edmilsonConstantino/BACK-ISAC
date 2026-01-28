<?php
/**
 * STUDENT PAYMENTS - INDEX / LIST
 * 📁 LOCATION: api/student-payments/index.php
 *
 * GET params (opcionais):
 *  - student_id
 *  - curso_id
 *  - month_reference (YYYY-MM)
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

try {
    // ==============================
    // 1) Filtros recebidos
    // ==============================
    $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    $cursoId = $_GET['curso_id'] ?? null;
    $monthReference = $_GET['month_reference'] ?? null;
    $registrationId = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : null;

    if ($monthReference && !isValidMonthRef($monthReference)) {
        badRequest("Invalid month_reference. Use YYYY-MM");
    }

    // ==============================
    // 2) Base query
    // ==============================
    $sql = "
        SELECT
            sp.id,
            sp.student_id,
            s.name AS student_name,

            sp.curso_id,
            c.nome AS course_name,

            sp.month_reference,
            sp.amount_paid,
            sp.status,
            sp.paid_date,
            sp.receipt_number,
            sp.observacoes,
            sp.data_criacao,

            pt.id AS payment_type_id,
            pt.nome AS payment_type_name
        FROM student_payments sp
        INNER JOIN students s ON s.id = sp.student_id
        INNER JOIN cursos c ON c.codigo = sp.curso_id
        INNER JOIN payment_types pt ON pt.id = sp.payment_type_id
        WHERE 1=1
    ";

    $params = [];

    // ==============================
    // 3) Aplicar filtros
    // ==============================
    if ($studentId) {
        $sql .= " AND sp.student_id = :student_id";
        $params[':student_id'] = $studentId;
    }

    if ($cursoId) {
        $sql .= " AND sp.curso_id = :curso_id";
        $params[':curso_id'] = $cursoId;
    }

    if ($monthReference) {
        $sql .= " AND sp.month_reference = :month_reference";
        $params[':month_reference'] = $monthReference;
    }

    if ($registrationId) {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM registrations r
                WHERE r.student_id = sp.student_id
                  AND r.course_id = sp.curso_id
                  AND r.id = :registration_id
            )
        ";
        $params[':registration_id'] = $registrationId;
    }

    // ==============================
    // 4) Ordenação
    // ==============================
    $sql .= " ORDER BY sp.paid_date DESC, sp.id DESC";

    // ==============================
    // 5) Executar
    // ==============================
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "total" => count($payments),
        "data" => $payments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching payments",
        "error" => $e->getMessage()
    ]);
}
