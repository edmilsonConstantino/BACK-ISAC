<?php
/**
 * ============================================================
 * COURSE CATEGORIES API
 * ============================================================
 * GET    - List all categories
 * GET/id - Get specific category
 * POST   - Create category (admin)
 * PUT    - Update category (admin)
 * DELETE - Soft delete category (admin)
 *
 * LOCAL: API-LOGIN/api/categorias.php
 */

// ==================== CORS ====================
$allowedOrigins = [
    "http://localhost:8080",
    "http://localhost:5173",
    "https://seu-front.onrender.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==================== CONNECTION ====================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthMiddleware();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Format category row from DB for API response
 */
function formatCategory(array $row): array {
    $row['has_levels'] = (bool)$row['has_levels'];
    if (isset($row['predefined_levels']) && is_string($row['predefined_levels'])) {
        $row['predefined_levels'] = json_decode($row['predefined_levels'], true);
    }
    return $row;
}

// ==================== ENDPOINTS ====================
switch($method) {
    // ========== GET - LIST CATEGORIES ==========
    case 'GET':
        $authResult = $auth->verificarAutenticacao();

        if (!$authResult['success']) {
            http_response_code(401);
            echo json_encode($authResult);
            exit();
        }

        try {
            if (isset($_GET['id'])) {
                $query = "SELECT * FROM categorias_curso WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $query = "SELECT * FROM categorias_curso WHERE status = 'active' ORDER BY name ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $categories = array_map('formatCategory', $categories);

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $categories
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching categories: " . $e->getMessage()
            ]);
        }
        break;

    // ========== POST - CREATE CATEGORY ==========
    case 'POST':
        $authResult = $auth->verificarAdmin();

        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->name)) {
            try {
                $query = "INSERT INTO categorias_curso
                          (name, description, has_levels, level_type, predefined_levels, status)
                          VALUES
                          (:name, :description, :has_levels, :level_type, :predefined_levels, :status)";

                $stmt = $db->prepare($query);

                $stmt->bindParam(':name', $data->name);

                $description = $data->description ?? null;
                $stmt->bindParam(':description', $description);

                $has_levels = isset($data->has_levels) ? (int)$data->has_levels : 0;
                $stmt->bindParam(':has_levels', $has_levels);

                $level_type = $data->level_type ?? 'numbered';
                $stmt->bindParam(':level_type', $level_type);

                $predefined_levels = isset($data->predefined_levels) ? json_encode($data->predefined_levels) : null;
                $stmt->bindParam(':predefined_levels', $predefined_levels);

                $status = $data->status ?? 'active';
                $stmt->bindParam(':status', $status);

                if ($stmt->execute()) {
                    $categoryId = $db->lastInsertId();

                    $stmtGet = $db->prepare("SELECT * FROM categorias_curso WHERE id = :id");
                    $stmtGet->bindParam(':id', $categoryId);
                    $stmtGet->execute();
                    $created = formatCategory($stmtGet->fetch(PDO::FETCH_ASSOC));

                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "Category created successfully.",
                        "data" => $created
                    ]);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Error creating category: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Category name is required."
            ]);
        }
        break;

    // ========== PUT - UPDATE CATEGORY ==========
    case 'PUT':
        $authResult = $auth->verificarAdmin();

        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            try {
                $query = "UPDATE categorias_curso SET
                          name = :name,
                          description = :description,
                          has_levels = :has_levels,
                          level_type = :level_type,
                          predefined_levels = :predefined_levels,
                          status = :status
                          WHERE id = :id";

                $stmt = $db->prepare($query);

                $stmt->bindParam(':id', $data->id);
                $stmt->bindParam(':name', $data->name);

                $description = $data->description ?? null;
                $stmt->bindParam(':description', $description);

                $has_levels = (int)$data->has_levels;
                $stmt->bindParam(':has_levels', $has_levels);

                $level_type = $data->level_type ?? 'numbered';
                $stmt->bindParam(':level_type', $level_type);

                $predefined_levels = isset($data->predefined_levels) ? json_encode($data->predefined_levels) : null;
                $stmt->bindParam(':predefined_levels', $predefined_levels);

                $stmt->bindParam(':status', $data->status);

                if ($stmt->execute()) {
                    $stmtGet = $db->prepare("SELECT * FROM categorias_curso WHERE id = :id");
                    $stmtGet->bindParam(':id', $data->id);
                    $stmtGet->execute();
                    $updated = formatCategory($stmtGet->fetch(PDO::FETCH_ASSOC));

                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Category updated successfully.",
                        "data" => $updated
                    ]);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Error updating category: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID is required."
            ]);
        }
        break;

    // ========== DELETE - SOFT DELETE CATEGORY ==========
    case 'DELETE':
        $authResult = $auth->verificarAdmin();

        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            try {
                $query = "UPDATE categorias_curso SET status = 'inactive' WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);

                if ($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Category deactivated successfully."
                    ]);
                }

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Error deleting category: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID is required."
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Method not allowed."
        ]);
        break;
}
?>
