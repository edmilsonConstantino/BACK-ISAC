<?php
/**
 * ================================================
 * API DE CURSOS - VERSÃO CORRIGIDA
 * ================================================
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

// Responder OPTIONS
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
    // ========== GET - LISTAR CURSOS ==========
    case 'GET':
        // Verificar autenticação
        $authResult = $auth->verificarAutenticacao();
        
        if (!$authResult['success']) {
            http_response_code(401);
            echo json_encode($authResult);
            exit();
        }
        
        try {
            if(isset($_GET['id'])) {
                $query = "SELECT * FROM cursos WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $query = "SELECT * FROM cursos ORDER BY nome ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // 🔧 Converter propina_fixa e permite_bolsa para boolean
            foreach ($cursos as &$curso) {
                $curso['propina_fixa'] = (bool)$curso['propina_fixa'];
                $curso['permite_bolsa'] = (bool)$curso['permite_bolsa'];
                $curso['mensalidade'] = (float)$curso['mensalidade'];
                $curso['taxa_matricula'] = (float)$curso['taxa_matricula'];
                $curso['duracao_valor'] = (int)$curso['duracao_valor'];
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $cursos
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erro ao buscar cursos: " . $e->getMessage()
            ]);
        }
        break;

    // ========== POST - CRIAR CURSO ==========
    case 'POST':
        // Apenas admin pode criar
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->nome) && !empty($data->codigo)) {
            try {
                $query = "INSERT INTO cursos 
                          (nome, codigo, tipo_curso, duracao_valor, regime, 
                           mensalidade, taxa_matricula, propina_fixa, permite_bolsa, 
                           status, observacoes) 
                          VALUES 
                          (:nome, :codigo, :tipo_curso, :duracao_valor, :regime, 
                           :mensalidade, :taxa_matricula, :propina_fixa, :permite_bolsa, 
                           :status, :observacoes)";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':codigo', $data->codigo);
                
                $tipo_curso = $data->tipo_curso ?? 'tecnico_superior';
                $stmt->bindParam(':tipo_curso', $tipo_curso);
                
                $duracao_valor = $data->duracao_valor ?? 2;
                $stmt->bindParam(':duracao_valor', $duracao_valor);
                
                $regime = $data->regime ?? 'laboral';
                $stmt->bindParam(':regime', $regime);
                
                $mensalidade = $data->mensalidade ?? 0.00;
                $stmt->bindParam(':mensalidade', $mensalidade);
                
                $taxa_matricula = $data->taxa_matricula ?? 0.00;
                $stmt->bindParam(':taxa_matricula', $taxa_matricula);
                
                $propina_fixa = isset($data->propina_fixa) ? (int)$data->propina_fixa : 1;
                $stmt->bindParam(':propina_fixa', $propina_fixa);
                
                $permite_bolsa = isset($data->permite_bolsa) ? (int)$data->permite_bolsa : 1;
                $stmt->bindParam(':permite_bolsa', $permite_bolsa);
                
                $status = $data->status ?? 'ativo';
                $stmt->bindParam(':status', $status);
                
                $observacoes = $data->observacoes ?? null;
                $stmt->bindParam(':observacoes', $observacoes);
                
                if($stmt->execute()) {
                    $cursoId = $db->lastInsertId();
                    
                    // 🔧 BUSCAR O CURSO CRIADO E RETORNAR
                    $queryGet = "SELECT * FROM cursos WHERE id = :id";
                    $stmtGet = $db->prepare($queryGet);
                    $stmtGet->bindParam(':id', $cursoId);
                    $stmtGet->execute();
                    $cursoCriado = $stmtGet->fetch(PDO::FETCH_ASSOC);
                    
                    // Converter para boolean
                    $cursoCriado['propina_fixa'] = (bool)$cursoCriado['propina_fixa'];
                    $cursoCriado['permite_bolsa'] = (bool)$cursoCriado['permite_bolsa'];
                    $cursoCriado['mensalidade'] = (float)$cursoCriado['mensalidade'];
                    $cursoCriado['taxa_matricula'] = (float)$cursoCriado['taxa_matricula'];
                    $cursoCriado['duracao_valor'] = (int)$cursoCriado['duracao_valor'];
                    
                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "Curso criado com sucesso.",
                        "data" => $cursoCriado
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao criar curso: " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Dados incompletos. Nome e código são obrigatórios."
            ]);
        }
        break;

    // ========== PUT - ATUALIZAR CURSO ==========
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
                $query = "UPDATE cursos SET 
                          nome = :nome,
                          codigo = :codigo,
                          tipo_curso = :tipo_curso,
                          duracao_valor = :duracao_valor,
                          regime = :regime,
                          mensalidade = :mensalidade,
                          taxa_matricula = :taxa_matricula,
                          propina_fixa = :propina_fixa,
                          permite_bolsa = :permite_bolsa,
                          status = :status,
                          observacoes = :observacoes
                          WHERE id = :id";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':id', $data->id);
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':codigo', $data->codigo);
                $stmt->bindParam(':tipo_curso', $data->tipo_curso);
                $stmt->bindParam(':duracao_valor', $data->duracao_valor);
                $stmt->bindParam(':regime', $data->regime);
                $stmt->bindParam(':mensalidade', $data->mensalidade);
                $stmt->bindParam(':taxa_matricula', $data->taxa_matricula);
                
                $propina_fixa = (int)$data->propina_fixa;
                $stmt->bindParam(':propina_fixa', $propina_fixa);
                
                $permite_bolsa = (int)$data->permite_bolsa;
                $stmt->bindParam(':permite_bolsa', $permite_bolsa);
                
                $stmt->bindParam(':status', $data->status);
                $stmt->bindParam(':observacoes', $data->observacoes);
                
                if($stmt->execute()) {
                    // 🔧 BUSCAR O CURSO ATUALIZADO E RETORNAR
                    $queryGet = "SELECT * FROM cursos WHERE id = :id";
                    $stmtGet = $db->prepare($queryGet);
                    $stmtGet->bindParam(':id', $data->id);
                    $stmtGet->execute();
                    $cursoAtualizado = $stmtGet->fetch(PDO::FETCH_ASSOC);
                    
                    // Converter para boolean
                    $cursoAtualizado['propina_fixa'] = (bool)$cursoAtualizado['propina_fixa'];
                    $cursoAtualizado['permite_bolsa'] = (bool)$cursoAtualizado['permite_bolsa'];
                    $cursoAtualizado['mensalidade'] = (float)$cursoAtualizado['mensalidade'];
                    $cursoAtualizado['taxa_matricula'] = (float)$cursoAtualizado['taxa_matricula'];
                    $cursoAtualizado['duracao_valor'] = (int)$cursoAtualizado['duracao_valor'];
                    
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Curso atualizado com sucesso.",
                        "data" => $cursoAtualizado
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao atualizar curso: " . $e->getMessage()
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

    // ========== DELETE - DELETAR CURSO ==========
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
                $query = "UPDATE cursos SET status = 'inativo' WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Curso desativado com sucesso."
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao deletar curso: " . $e->getMessage()
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