<?php
/**
 * ================================================
 * API DE TURMAS - VERSÃO COMPLETA COM GESTÃO DE ESTUDANTES
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

// Responder OPTIONS (preflight)
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
    // ========== GET - LISTAR TURMAS OU ESTUDANTES ==========
    case 'GET':
        // Verificar autenticação
        $authResult = $auth->verificarAutenticacao();
        
        if (!$authResult['success']) {
            http_response_code(401);
            echo json_encode($authResult);
            exit();
        }

        // 🆕 ENDPOINT 1: Listar estudantes disponíveis para adicionar à turma
        if (isset($_GET['action']) && $_GET['action'] === 'get_available_students') {
            try {
                $turma_id = $_GET['turma_id'] ?? null;
                $curso_id = $_GET['curso_id'] ?? null;

                if (!$turma_id || !$curso_id) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "turma_id e curso_id são obrigatórios"
                    ]);
                    exit();
                }

                // Buscar estudantes que:
                // 1. Estão no mesmo curso da turma
                // 2. NÃO estão matriculados nessa turma ainda
                $query = "SELECT s.id, s.nome, s.email, s.telefone, s.data_nascimento, s.curso_id
                          FROM students s
                          WHERE s.curso_id = :curso_id
                            AND s.id NOT IN (
                                SELECT estudante_id 
                                FROM turma_estudantes 
                                WHERE turma_id = :turma_id
                                  AND status = 'ativo'
                            )
                          ORDER BY s.nome ASC";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':curso_id', $curso_id);
                $stmt->bindParam(':turma_id', $turma_id);
                $stmt->execute();
                
                $estudantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "data" => $estudantes,
                    "total" => count($estudantes)
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao buscar estudantes: " . $e->getMessage()
                ]);
            }
            exit();
        }

        // 🆕 ENDPOINT 2: Listar estudantes matriculados em uma turma
        if (isset($_GET['action']) && $_GET['action'] === 'get_students') {
            try {
                $turma_id = $_GET['turma_id'] ?? null;

                if (!$turma_id) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "turma_id é obrigatório"
                    ]);
                    exit();
                }

                $query = "SELECT 
                            s.id,
                            s.nome,
                            s.email,
                            s.telefone,
                            s.curso_id,
                            te.data_matricula,
                            te.status,
                            te.nota_final,
                            te.frequencia
                          FROM students s
                          INNER JOIN turma_estudantes te ON te.estudante_id = s.id
                          WHERE te.turma_id = :turma_id
                            AND te.status = 'ativo'
                          ORDER BY s.nome ASC";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':turma_id', $turma_id);
                $stmt->execute();
                
                $estudantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "data" => $estudantes,
                    "total" => count($estudantes)
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao buscar estudantes da turma: " . $e->getMessage()
                ]);
            }
            exit();
        }
        
        // 🔥 GET PADRÃO - Listar turmas COM CONTAGEM DE ESTUDANTES
        try {
            if(isset($_GET['id'])) {
                // Buscar turma específica com contagem de estudantes
                $query = "SELECT 
                            t.*,
                            COALESCE(COUNT(DISTINCT te.id), 0) as vagas_ocupadas,
                            p.nome as professor_nome
                          FROM turmas t
                          LEFT JOIN turma_estudantes te ON t.id = te.turma_id AND te.status = 'ativo'
                          LEFT JOIN professores p ON t.professor_id = p.id
                          WHERE t.id = :id
                          GROUP BY t.id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
                
            } else {
                // Listar todas as turmas com contagem de estudantes
                $query = "SELECT 
                            t.*,
                            COALESCE(COUNT(DISTINCT te.id), 0) as vagas_ocupadas,
                            p.nome as professor_nome
                          FROM turmas t
                          LEFT JOIN turma_estudantes te ON t.id = te.turma_id AND te.status = 'ativo'
                          LEFT JOIN professores p ON t.professor_id = p.id
                          GROUP BY t.id
                          ORDER BY t.data_criacao DESC";
                
                $stmt = $db->prepare($query);
            }
            
            $stmt->execute();
            $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Converter vagas_ocupadas para inteiro
            foreach ($turmas as &$turma) {
                $turma['vagas_ocupadas'] = (int)$turma['vagas_ocupadas'];
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $turmas
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erro ao buscar turmas: " . $e->getMessage()
            ]);
        }
        break;

    // ========== POST - CRIAR TURMA OU ADICIONAR ESTUDANTES ==========
    case 'POST':
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }

        // 🆕 ENDPOINT 3: Adicionar estudantes à turma
        if (isset($_GET['action']) && $_GET['action'] === 'add_students') {
            $data = json_decode(file_get_contents("php://input"));
            
            if (!empty($data->turma_id) && !empty($data->estudante_ids) && is_array($data->estudante_ids)) {
                try {
                    $db->beginTransaction();
                    
                    $success_count = 0;
                    $errors = [];

                    foreach ($data->estudante_ids as $estudante_id) {
                        try {
                            // Verificar se já não está na turma
                            $check_query = "SELECT COUNT(*) FROM turma_estudantes 
                                          WHERE turma_id = :turma_id 
                                            AND estudante_id = :estudante_id";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->bindParam(':turma_id', $data->turma_id);
                            $check_stmt->bindParam(':estudante_id', $estudante_id);
                            $check_stmt->execute();
                            
                            if ($check_stmt->fetchColumn() > 0) {
                                $errors[] = "Estudante ID $estudante_id já está na turma";
                                continue;
                            }

                            // Inserir na tabela intermediária
                            $query = "INSERT INTO turma_estudantes 
                                     (turma_id, estudante_id, data_matricula, status) 
                                     VALUES (:turma_id, :estudante_id, NOW(), 'ativo')";
                            
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':turma_id', $data->turma_id);
                            $stmt->bindParam(':estudante_id', $estudante_id);
                            $stmt->execute();
                            
                            $success_count++;

                        } catch (PDOException $e) {
                            $errors[] = "Erro ao adicionar estudante ID $estudante_id: " . $e->getMessage();
                        }
                    }

                    // Atualizar contador de vagas_ocupadas na turma
                    $update_query = "UPDATE turmas SET 
                                    vagas_ocupadas = (
                                        SELECT COUNT(*) FROM turma_estudantes 
                                        WHERE turma_id = :turma_id AND status = 'ativo'
                                    )
                                    WHERE id = :turma_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':turma_id', $data->turma_id);
                    $update_stmt->execute();

                    $db->commit();
                    
                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "$success_count estudante(s) adicionado(s) com sucesso",
                        "added" => $success_count,
                        "errors" => $errors
                    ]);

                } catch (PDOException $e) {
                    $db->rollBack();
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "message" => "Erro ao adicionar estudantes: " . $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "turma_id e estudante_ids (array) são obrigatórios"
                ]);
            }
            exit();
        }
        
        // POST PADRÃO - Criar turma
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->nome) && !empty($data->curso_id)) {

          try {

            $ano_letivo = $data->ano_letivo ?? date('Y');

            // 🔥 GERAR CÓDIGO: CURSO-ANO-SEQ (ex: INF-2026-001)
            $prefixo = strtoupper(trim($data->curso_id)); // ex: INF
            $codigo = null;

            // tentativa com retry (evita duplicação em concorrência)
            for ($tentativa = 0; $tentativa < 5; $tentativa++) {

              // pega o maior sequencial do ano+curso
              $like = "{$prefixo}-{$ano_letivo}-%";
              $q = "SELECT codigo FROM turmas
                    WHERE codigo LIKE :like
                    ORDER BY id DESC
                    LIMIT 1";
              $st = $db->prepare($q);
              $st->bindParam(':like', $like);
              $st->execute();
              $last = $st->fetchColumn();

              $seq = 1;
              if ($last) {
                $parts = explode('-', $last);
                $n = intval(end($parts));
                $seq = $n + 1;
              }

              $codigo = sprintf("%s-%s-%03d", $prefixo, $ano_letivo, $seq);

              // tenta inserir
              try {
                $query = "INSERT INTO turmas
                          (codigo, nome, curso_id, professor_id, semestre, ano_letivo,
                           duracao_meses, capacidade_maxima, sala, dias_semana,
                           horario_inicio, horario_fim, data_inicio, data_fim,
                           carga_horaria, creditos, observacoes, status,
                           data_criacao, data_atualizacao)
                          VALUES
                          (:codigo, :nome, :curso_id, :professor_id, :semestre, :ano_letivo,
                           :duracao_meses, :capacidade_maxima, :sala, :dias_semana,
                           :horario_inicio, :horario_fim, :data_inicio, :data_fim,
                           :carga_horaria, :creditos, :observacoes, :status,
                           NOW(), NOW())";

                $stmt = $db->prepare($query);

                $stmt->bindParam(':codigo', $codigo);
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':curso_id', $data->curso_id);

                $professor_id = $data->professor_id ?? null;
                $stmt->bindParam(':professor_id', $professor_id);

                $semestre = $data->semestre ?? null;
                $stmt->bindParam(':semestre', $semestre);

                $stmt->bindParam(':ano_letivo', $ano_letivo);

                $duracao_meses = $data->duracao_meses ?? 6;
                $stmt->bindParam(':duracao_meses', $duracao_meses);

                $capacidade_maxima = $data->capacidade_maxima ?? 30;
                $stmt->bindParam(':capacidade_maxima', $capacidade_maxima);

                $sala = $data->sala ?? null;
                $stmt->bindParam(':sala', $sala);

                $dias_semana = $data->dias_semana ?? '';
                $stmt->bindParam(':dias_semana', $dias_semana);

                $horario_inicio = $data->horario_inicio ?? '00:00:00';
                $stmt->bindParam(':horario_inicio', $horario_inicio);

                $horario_fim = $data->horario_fim ?? '00:00:00';
                $stmt->bindParam(':horario_fim', $horario_fim);

                $data_inicio = $data->data_inicio ?? null;
                $stmt->bindParam(':data_inicio', $data_inicio);

                $data_fim = $data->data_fim ?? null;
                $stmt->bindParam(':data_fim', $data_fim);

                $carga_horaria = $data->carga_horaria ?? null;
                $stmt->bindParam(':carga_horaria', $carga_horaria);

                $creditos = $data->creditos ?? null;
                $stmt->bindParam(':creditos', $creditos);

                $observacoes = $data->observacoes ?? null;
                $stmt->bindParam(':observacoes', $observacoes);

                $status = $data->status ?? 'ativo';
                $stmt->bindParam(':status', $status);

                $stmt->execute();

                $id = $db->lastInsertId();

                // ✅ retornar turma criada
                $st2 = $db->prepare("SELECT * FROM turmas WHERE id = :id");
                $st2->bindParam(':id', $id);
                $st2->execute();

                http_response_code(201);
                echo json_encode([
                  "success" => true,
                  "message" => "Turma criada com sucesso.",
                  "data" => $st2->fetch(PDO::FETCH_ASSOC)
                ]);
                exit();

              } catch (PDOException $e) {
                // se der duplicação de código, tenta de novo
                if ((int)$e->errorInfo[1] === 1062) {
                  continue;
                }
                throw $e;
              }
            }

            http_response_code(500);
            echo json_encode([
              "success" => false,
              "message" => "Falha ao gerar código único da turma. Tente novamente."
            ]);

          } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
              "success" => false,
              "message" => "Erro ao criar turma: " . $e->getMessage()
            ]);
          }

        } else {
          http_response_code(400);
          echo json_encode([
            "success" => false,
            "message" => "Dados incompletos. Nome e curso_id são obrigatórios."
          ]);
        }
        break;

    // ========== PUT - ATUALIZAR TURMA ==========
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
                $query = "UPDATE turmas SET 
                          codigo = :codigo,
                          nome = :nome,
                          disciplina = :disciplina,
                          professor_id = :professor_id,
                          semestre = :semestre,
                          ano_letivo = :ano_letivo,
                          duracao_meses = :duracao_meses,
                          capacidade_maxima = :capacidade_maxima,
                          sala = :sala,
                          dias_semana = :dias_semana,
                          horario_inicio = :horario_inicio,
                          horario_fim = :horario_fim,
                          data_inicio = :data_inicio,
                          data_fim = :data_fim,
                          carga_horaria = :carga_horaria,
                          creditos = :creditos,
                          observacoes = :observacoes,
                          status = :status,
                          data_atualizacao = NOW()
                          WHERE id = :id";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':id', $data->id);
                $stmt->bindParam(':codigo', $data->codigo);
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':disciplina', $data->disciplina);
                $stmt->bindParam(':professor_id', $data->professor_id);
                $stmt->bindParam(':semestre', $data->semestre);
                $stmt->bindParam(':ano_letivo', $data->ano_letivo);
                $stmt->bindParam(':duracao_meses', $data->duracao_meses);
                $stmt->bindParam(':capacidade_maxima', $data->capacidade_maxima);
                $stmt->bindParam(':sala', $data->sala);
                $stmt->bindParam(':dias_semana', $data->dias_semana);
                $stmt->bindParam(':horario_inicio', $data->horario_inicio);
                $stmt->bindParam(':horario_fim', $data->horario_fim);
                $stmt->bindParam(':data_inicio', $data->data_inicio);
                $stmt->bindParam(':data_fim', $data->data_fim);
                $stmt->bindParam(':carga_horaria', $data->carga_horaria);
                $stmt->bindParam(':creditos', $data->creditos);
                $stmt->bindParam(':observacoes', $data->observacoes);
                $stmt->bindParam(':status', $data->status);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Turma atualizada com sucesso."
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao atualizar turma: " . $e->getMessage()
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

    // ========== DELETE - DELETAR TURMA OU REMOVER ESTUDANTE ==========
    case 'DELETE':
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }

        // 🆕 ENDPOINT 4: Remover estudante da turma
        if (isset($_GET['action']) && $_GET['action'] === 'remove_student') {
            $data = json_decode(file_get_contents("php://input"));
            
            if (!empty($data->turma_id) && !empty($data->estudante_id)) {
                try {
                    // Soft delete - mudar status para 'inativo'
                    $query = "UPDATE turma_estudantes 
                             SET status = 'inativo', data_atualizacao = NOW()
                             WHERE turma_id = :turma_id 
                               AND estudante_id = :estudante_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':turma_id', $data->turma_id);
                    $stmt->bindParam(':estudante_id', $data->estudante_id);
                    
                    if ($stmt->execute()) {
                        // Atualizar contador de vagas_ocupadas
                        $update_query = "UPDATE turmas SET 
                                        vagas_ocupadas = (
                                            SELECT COUNT(*) FROM turma_estudantes 
                                            WHERE turma_id = :turma_id AND status = 'ativo'
                                        )
                                        WHERE id = :turma_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':turma_id', $data->turma_id);
                        $update_stmt->execute();

                        http_response_code(200);
                        echo json_encode([
                            "success" => true,
                            "message" => "Estudante removido da turma com sucesso."
                        ]);
                    }

                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "message" => "Erro ao remover estudante: " . $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "turma_id e estudante_id são obrigatórios"
                ]);
            }
            exit();
        }
        
        // DELETE PADRÃO - Arquivar turma
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->id)) {
            try {
                $query = "UPDATE turmas SET status = 'cancelado', data_atualizacao = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Turma arquivada com sucesso."
                    ]);
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao deletar turma: " . $e->getMessage()
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