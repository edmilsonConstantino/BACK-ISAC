<?php
/**
 * ============================================================
 * API DE NÍVEIS DE CURSOS
 * ============================================================
 * GET    - Listar níveis de um curso
 * POST   - Criar nível (admin)
 * PUT    - Atualizar nível (admin)
 * DELETE - Deletar nível (admin)
 * 
 * 📁 LOCAL: API-LOGIN/api/niveis.php
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

// ==================== CONEXÃO ====================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthMiddleware();

$method = $_SERVER['REQUEST_METHOD'];

// ==================== ENDPOINTS ====================
switch($method) {
    // ========== GET - LISTAR NÍVEIS ==========
    case 'GET':
        $authResult = $auth->verificarAutenticacao();
        
        if (!$authResult['success']) {
            http_response_code(401);
            echo json_encode($authResult);
            exit();
        }
        
        try {
            if(isset($_GET['curso_id'])) {
                // Buscar níveis de um curso específico
                $query = "SELECT * FROM curso_niveis 
                          WHERE curso_id = :curso_id AND status = 'ativo' 
                          ORDER BY ordem ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':curso_id', $_GET['curso_id']);
                $stmt->execute();
                $niveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif(isset($_GET['id'])) {
                // Buscar nível específico
                $query = "SELECT * FROM curso_niveis WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                $niveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Parâmetro curso_id ou id é obrigatório"
                ]);
                exit();
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $niveis
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erro ao buscar níveis: " . $e->getMessage()
            ]);
        }
        break;

    // ========== POST - CRIAR NÍVEL ==========
    case 'POST':
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->curso_id) && !empty($data->nivel) && !empty($data->nome)) {
            try {
                $query = "INSERT INTO curso_niveis 
                          (curso_id, nivel, nome, descricao, duracao_meses, ordem, prerequisito_nivel_id, status) 
                          VALUES 
                          (:curso_id, :nivel, :nome, :descricao, :duracao_meses, :ordem, :prerequisito_nivel_id, :status)";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':curso_id', $data->curso_id);
                $stmt->bindParam(':nivel', $data->nivel);
                $stmt->bindParam(':nome', $data->nome);
                
                $descricao = $data->descricao ?? null;
                $stmt->bindParam(':descricao', $descricao);
                
                $duracao_meses = $data->duracao_meses ?? 4;
                $stmt->bindParam(':duracao_meses', $duracao_meses);
                
                $ordem = $data->ordem ?? $data->nivel;
                $stmt->bindParam(':ordem', $ordem);
                
                $prerequisito_nivel_id = $data->prerequisito_nivel_id ?? null;
                $stmt->bindParam(':prerequisito_nivel_id', $prerequisito_nivel_id);
                
                $status = $data->status ?? 'ativo';
                $stmt->bindParam(':status', $status);
                
                if($stmt->execute()) {
                    $nivelId = $db->lastInsertId();
                    
                    // Buscar nível criado
                    $queryGet = "SELECT * FROM curso_niveis WHERE id = :id";
                    $stmtGet = $db->prepare($queryGet);
                    $stmtGet->bindParam(':id', $nivelId);
                    $stmtGet->execute();
                    $nivelCriado = $stmtGet->fetch(PDO::FETCH_ASSOC);
                    
                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "Nível criado com sucesso.",
                        "data" => $nivelCriado
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao criar nível: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Dados incompletos. curso_id, nivel e nome são obrigatórios."
            ]);
        }
        break;

    // ========== PUT - ATUALIZAR NÍVEL ==========
    case 'PUT':
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->id)) {
            try {
                $query = "UPDATE curso_niveis SET 
                          nivel = :nivel,
                          nome = :nome,
                          descricao = :descricao,
                          duracao_meses = :duracao_meses,
                          ordem = :ordem,
                          prerequisito_nivel_id = :prerequisito_nivel_id,
                          status = :status
                          WHERE id = :id";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':id', $data->id);
                $stmt->bindParam(':nivel', $data->nivel);
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':descricao', $data->descricao);
                $stmt->bindParam(':duracao_meses', $data->duracao_meses);
                $stmt->bindParam(':ordem', $data->ordem);
                $stmt->bindParam(':prerequisito_nivel_id', $data->prerequisito_nivel_id);
                $stmt->bindParam(':status', $data->status);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Nível atualizado com sucesso."
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao atualizar nível: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID não fornecido."
            ]);
        }
        break;

    // ========== DELETE - DELETAR NÍVEL ==========
    case 'DELETE':
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->id)) {
            try {
                // Soft delete
                $query = "UPDATE curso_niveis SET status = 'inativo' WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Nível desativado com sucesso."
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao deletar nível: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "ID não fornecido."
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Método não permitido."
        ]);
        break;
}
?>