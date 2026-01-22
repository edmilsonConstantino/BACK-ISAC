<?php
/**
 * API: Gestão de Cursos
 * Endpoint: /api/admin/courses/index.php
 * Métodos: GET (listar), POST (criar)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';

// Verificar autenticação de Admin
$user = AuthMiddleware::validateAdmin();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            // ========================================
            // LISTAR CURSOS
            // ========================================
            
            // Parâmetros de filtro
            $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $codigo = isset($_GET['codigo']) ? $_GET['codigo'] : null;
            
            $query = "SELECT * FROM cursos WHERE 1=1";
            $params = [];
            
            // Filtro por tipo
            if ($tipo) {
                $query .= " AND tipo_curso = :tipo";
                $params[':tipo'] = $tipo;
            }
            
            // Filtro por status
            if ($status) {
                $query .= " AND status = :status";
                $params[':status'] = $status;
            }
            
            // Filtro por código
            if ($codigo) {
                $query .= " AND codigo = :codigo";
                $params[':codigo'] = $codigo;
            }
            
            $query .= " ORDER BY data_criacao DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatar resposta
            foreach ($cursos as &$curso) {
                $curso['propina_fixa'] = (bool)$curso['propina_fixa'];
                $curso['permite_bolsa'] = (bool)$curso['permite_bolsa'];
                $curso['mensalidade'] = (float)$curso['mensalidade'];
                $curso['taxa_matricula'] = (float)$curso['taxa_matricula'];
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $cursos,
                'total' => count($cursos)
            ]);
            break;
            
        case 'POST':
            // ========================================
            // CRIAR NOVO CURSO
            // ========================================
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validações
            if (empty($input['nome'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nome do curso é obrigatório'
                ]);
                exit();
            }
            
            if (empty($input['codigo'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Código do curso é obrigatório'
                ]);
                exit();
            }
            
            if (strlen($input['codigo']) < 3) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Código deve ter no mínimo 3 caracteres'
                ]);
                exit();
            }
            
            // Verificar se código já existe
            $checkQuery = "SELECT id FROM cursos WHERE codigo = :codigo";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([':codigo' => $input['codigo']]);
            
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Código do curso já existe'
                ]);
                exit();
            }
            
            // Definir tipo_duracao automaticamente
            $tipo_duracao = ($input['tipo_curso'] === 'tecnico_superior') ? 'anos' : 'meses';
            
            // Inserir curso
            $query = "INSERT INTO cursos 
                     (nome, codigo, tipo_curso, duracao_valor, tipo_duracao, regime_permitido, 
                      mensalidade, taxa_matricula, propina_fixa, permite_bolsa, status, observacoes)
                     VALUES 
                     (:nome, :codigo, :tipo_curso, :duracao_valor, :tipo_duracao, :regime, 
                      :mensalidade, :taxa_matricula, :propina_fixa, :permite_bolsa, :status, :observacoes)";
            
            $stmt = $db->prepare($query);
            
            $success = $stmt->execute([
                ':nome' => $input['nome'],
                ':codigo' => strtoupper($input['codigo']),
                ':tipo_curso' => $input['tipo_curso'],
                ':duracao_valor' => $input['duracao_valor'],
                ':tipo_duracao' => $tipo_duracao,
                ':regime' => $input['regime'],
                ':mensalidade' => $input['mensalidade'] ?? 0,
                ':taxa_matricula' => $input['taxa_matricula'] ?? 0,
                ':propina_fixa' => $input['propina_fixa'] ?? true,
                ':permite_bolsa' => $input['permite_bolsa'] ?? true,
                ':status' => $input['status'] ?? 'ativo',
                ':observacoes' => $input['observacoes'] ?? null
            ]);
            
            if ($success) {
                $cursoId = $db->lastInsertId();
                
                // Buscar curso criado
                $stmt = $db->prepare("SELECT * FROM cursos WHERE id = :id");
                $stmt->execute([':id' => $cursoId]);
                $curso = $stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Curso criado com sucesso!',
                    'data' => $curso
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar curso'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método não permitido'
            ]);
            break;
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>