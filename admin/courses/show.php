<?php
/**
 * API: Gestão de Cursos - Detalhes
 * Endpoint: /api/admin/courses/show.php
 * Métodos: GET (detalhes), PUT (atualizar), DELETE (deletar)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';

// Verificar autenticação de Admin
$user = AuthMiddleware::validateAdmin();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// ID do curso é obrigatório
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID do curso é obrigatório'
    ]);
    exit();
}

$curso_id = (int)$_GET['id'];

try {
    switch($method) {
        case 'GET':
            // ========================================
            // BUSCAR DETALHES DO CURSO
            // ========================================
            
            $query = "SELECT * FROM cursos WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $curso_id]);
            $curso = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$curso) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Curso não encontrado'
                ]);
                exit();
            }
            
            // Formatar dados
            $curso['propina_fixa'] = (bool)$curso['propina_fixa'];
            $curso['permite_bolsa'] = (bool)$curso['permite_bolsa'];
            $curso['mensalidade'] = (float)$curso['mensalidade'];
            $curso['taxa_matricula'] = (float)$curso['taxa_matricula'];
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $curso
            ]);
            break;
            
        case 'PUT':
            // ========================================
            // ATUALIZAR CURSO
            // ========================================
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Verificar se curso existe
            $checkQuery = "SELECT id FROM cursos WHERE id = :id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([':id' => $curso_id]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Curso não encontrado'
                ]);
                exit();
            }
            
            // Validações
            if (empty($input['nome'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nome do curso é obrigatório'
                ]);
                exit();
            }
            
            // Verificar se código já existe em outro curso
            if (!empty($input['codigo'])) {
                $checkCodeQuery = "SELECT id FROM cursos WHERE codigo = :codigo AND id != :id";
                $checkCodeStmt = $db->prepare($checkCodeQuery);
                $checkCodeStmt->execute([
                    ':codigo' => $input['codigo'],
                    ':id' => $curso_id
                ]);
                
                if ($checkCodeStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Código já está em uso por outro curso'
                    ]);
                    exit();
                }
            }
            
            // Definir tipo_duracao automaticamente
            $tipo_duracao = ($input['tipo_curso'] === 'tecnico_superior') ? 'anos' : 'meses';
            
            // Atualizar curso
            $query = "UPDATE cursos SET
                     nome = :nome,
                     codigo = :codigo,
                     tipo_curso = :tipo_curso,
                     duracao_valor = :duracao_valor,
                     tipo_duracao = :tipo_duracao,
                     regime_permitido = :regime,
                     mensalidade = :mensalidade,
                     taxa_matricula = :taxa_matricula,
                     propina_fixa = :propina_fixa,
                     permite_bolsa = :permite_bolsa,
                     status = :status,
                     observacoes = :observacoes
                     WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            $success = $stmt->execute([
                ':id' => $curso_id,
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
                // Buscar curso atualizado
                $stmt = $db->prepare("SELECT * FROM cursos WHERE id = :id");
                $stmt->execute([':id' => $curso_id]);
                $curso = $stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Curso atualizado com sucesso!',
                    'data' => $curso
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao atualizar curso'
                ]);
            }
            break;
            
        case 'DELETE':
            // ========================================
            // DELETAR CURSO
            // ========================================
            
            // Verificar se curso existe
            $checkQuery = "SELECT id, nome FROM cursos WHERE id = :id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([':id' => $curso_id]);
            $curso = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$curso) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Curso não encontrado'
                ]);
                exit();
            }
            
            // TODO: Verificar se há turmas/alunos vinculados ao curso
            // Se houver, não permitir deletar (ou fazer soft delete)
            
            $query = "DELETE FROM cursos WHERE id = :id";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([':id' => $curso_id]);
            
            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Curso deletado com sucesso!'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao deletar curso'
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
    