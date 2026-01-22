<?php
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

// RESPONDER PREFLIGHT
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthMiddleware();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Qualquer usuário autenticado pode listar
        $authResult = $auth->verificarAutenticacao();
        
        if (!$authResult['success']) {
            http_response_code(401);
            echo json_encode($authResult);
            exit();
        }
        
        try {
            if(isset($_GET['id'])) {
                // ✅ NÃO RETORNAR SENHA
                $query = "SELECT id, nome, email, username, telefone, especialidade, 
                          data_nascimento, endereco, tipo_contrato, data_inicio, salario, 
                          contato_emergencia, observacoes, status, last_login, created_at 
                          FROM professores WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
            } else {
                $query = "SELECT id, nome, email, username, telefone, especialidade, 
                          data_nascimento, endereco, tipo_contrato, data_inicio, salario, 
                          contato_emergencia, observacoes, status, last_login, created_at 
                          FROM professores ORDER BY nome ASC";
                $stmt = $db->prepare($query);
            }
            
            $stmt->execute();
            $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $professores
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erro ao buscar professores: " . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        // Apenas admin pode criar
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->nome) && !empty($data->email)) {
            try {
                // ✅ HASH PASSWORD IF PROVIDED
                $password_hash = null;
                if(!empty($data->password)) {
                    $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
                }
                
                $query = "INSERT INTO professores 
                          (nome, email, username, password, telefone, especialidade, 
                           data_nascimento, endereco, tipo_contrato, data_inicio, 
                           salario, contato_emergencia, observacoes, status) 
                          VALUES 
                          (:nome, :email, :username, :password, :telefone, :especialidade, 
                           :data_nascimento, :endereco, :tipo_contrato, :data_inicio, 
                           :salario, :contato_emergencia, :observacoes, :status)";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':email', $data->email);
                
                // ✅ NEW: Login credentials
                $username = $data->username ?? null;
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password_hash);
                
                $stmt->bindParam(':telefone', $data->telefone);
                $stmt->bindParam(':especialidade', $data->especialidade);
                $stmt->bindParam(':data_nascimento', $data->data_nascimento);
                $stmt->bindParam(':endereco', $data->endereco);
                
                $tipo_contrato = $data->tipo_contrato ?? 'tempo_integral';
                $stmt->bindParam(':tipo_contrato', $tipo_contrato);
                $stmt->bindParam(':data_inicio', $data->data_inicio);
                $stmt->bindParam(':salario', $data->salario);
                $stmt->bindParam(':contato_emergencia', $data->contato_emergencia);
                $stmt->bindParam(':observacoes', $data->observacoes);
                
                $status = $data->status ?? 'ativo';
                $stmt->bindParam(':status', $status);
                
                if($stmt->execute()) {
                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "Professor criado com sucesso.",
                        "id" => $db->lastInsertId()
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                
                // ✅ Check for duplicate username
                if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    if(strpos($e->getMessage(), 'username') !== false) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Username já existe. Escolha outro."
                        ]);
                    } else {
                        echo json_encode([
                            "success" => false,
                            "message" => "Erro ao criar professor: Registro duplicado"
                        ]);
                    }
                } else {
                    echo json_encode([
                        "success" => false,
                        "message" => "Erro ao criar professor: " . $e->getMessage()
                    ]);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Dados incompletos. Nome e email são obrigatórios."
            ]);
        }
        break;

    case 'PUT':
        // Apenas admin pode atualizar
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->id)) {
            try {
                // ✅ BUILD DYNAMIC UPDATE (only update password if provided)
                $fields = [
                    'nome = :nome',
                    'email = :email',
                    'username = :username',
                    'telefone = :telefone',
                    'especialidade = :especialidade',
                    'data_nascimento = :data_nascimento',
                    'endereco = :endereco',
                    'tipo_contrato = :tipo_contrato',
                    'data_inicio = :data_inicio',
                    'salario = :salario',
                    'contato_emergencia = :contato_emergencia',
                    'observacoes = :observacoes',
                    'status = :status'
                ];
                
                // Add password only if provided
                if(!empty($data->password)) {
                    $fields[] = 'password = :password';
                }
                
                $query = "UPDATE professores SET " . implode(', ', $fields) . " WHERE id = :id";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':id', $data->id);
                $stmt->bindParam(':nome', $data->nome);
                $stmt->bindParam(':email', $data->email);
                $stmt->bindParam(':username', $data->username);
                $stmt->bindParam(':telefone', $data->telefone);
                $stmt->bindParam(':especialidade', $data->especialidade);
                $stmt->bindParam(':data_nascimento', $data->data_nascimento);
                $stmt->bindParam(':endereco', $data->endereco);
                $stmt->bindParam(':tipo_contrato', $data->tipo_contrato);
                $stmt->bindParam(':data_inicio', $data->data_inicio);
                $stmt->bindParam(':salario', $data->salario);
                $stmt->bindParam(':contato_emergencia', $data->contato_emergencia);
                $stmt->bindParam(':observacoes', $data->observacoes);
                $stmt->bindParam(':status', $data->status);
                
                // Bind password only if provided
                if(!empty($data->password)) {
                    $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
                    $stmt->bindParam(':password', $password_hash);
                }
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Professor atualizado com sucesso."
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao atualizar professor: " . $e->getMessage()
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

    case 'DELETE':
        // Apenas admin pode deletar
        $authResult = $auth->verificarAdmin();
        
        if (!$authResult['success']) {
            http_response_code(403);
            echo json_encode($authResult);
            exit();
        }
        
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->id)) {
            try {
                $query = "DELETE FROM professores WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $data->id);
                
                if($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Professor deletado com sucesso."
                    ]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Erro ao deletar professor: " . $e->getMessage()
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