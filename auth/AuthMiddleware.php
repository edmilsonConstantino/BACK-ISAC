<?php
require_once __DIR__ . '/JWTHandler.php';

/**
 * Middleware para autenticação e autorização
 */
class AuthMiddleware
{
    private $jwt;
    
    public function __construct()
    {
        $this->jwt = new JWTHandler();
        
        // ✅ CONFIGURAR CORS AUTOMATICAMENTE
        $this->configurarCORS();
    }
    
    /**
     * 🔧 Configura headers CORS
     */
    private function configurarCORS()
    {
        // Permitir requisições do frontend
        header("Access-Control-Allow-Origin: http://localhost:8080");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Content-Type: application/json; charset=UTF-8");
        
        // Lidar com preflight (OPTIONS)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Obtém o token Bearer do header Authorization
     * @return string|null
     */
    public function getBearerToken()
    {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)), 
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Verifica se o usuário está autenticado
     * @return array ['success' => bool, 'data' => object|null, 'message' => string]
     */
    public function verificarAutenticacao()
    {
        $token = $this->getBearerToken();
        
        if (!$token) {
            return [
                'success' => false, 
                'data' => null, 
                'message' => 'Token não fornecido. Use: Authorization: Bearer {token}'
            ];
        }
        
        $decoded = $this->jwt->validateToken($token);
        
        if (!$decoded) {
            return [
                'success' => false, 
                'data' => null, 
                'message' => 'Token inválido ou expirado.'
            ];
        }
        
        return [
            'success' => true, 
            'data' => $decoded->data, 
            'message' => 'Autenticado com sucesso.'
        ];
    }

    /**
     * Verifica se o usuário é admin
     * @return array
     */
    public function verificarAdmin()
    {
        $auth = $this->verificarAutenticacao();
        
        if (!$auth['success']) {
            return $auth;
        }
        
        if (!$this->jwt->isAdmin($this->getBearerToken())) {
            return [
                'success' => false, 
                'data' => null, 
                'message' => 'Acesso negado. Apenas administradores podem realizar esta ação.'
            ];
        }
        
        return $auth;
    }

    /**
     * Verifica se o usuário é professor
     * @return array
     */
    public function verificarProfessor()
    {
        $auth = $this->verificarAutenticacao();
        
        if (!$auth['success']) {
            return $auth;
        }
        
        if (!$this->jwt->isProfessor($this->getBearerToken())) {
            return [
                'success' => false, 
                'data' => null, 
                'message' => 'Acesso negado. Apenas professores podem realizar esta ação.'
            ];
        }
        
        return $auth;
    }

    /**
     * Verifica se o usuário é estudante
     * @return array
     */
    public function verificarEstudante()
    {
        $auth = $this->verificarAutenticacao();
        
        if (!$auth['success']) {
            return $auth;
        }
        
        if (!$this->jwt->isEstudante($this->getBearerToken())) {
            return [
                'success' => false, 
                'data' => null, 
                'message' => 'Acesso negado. Apenas estudantes podem realizar esta ação.'
            ];
        }
        
        return $auth;
    }

    /**
     * Responde com erro de autenticação
     * @param string $message
     * @param int $http_code
     */
    public function respondUnauthorized($message = "Não autorizado", $http_code = 401)
    {
        http_response_code($http_code);
        echo json_encode([
            "success" => false, 
            "message" => $message
        ]);
        exit();
    }
    
    /**
     * 🆕 Método estático para uso rápido (compatibilidade)
     * Valida autenticação e retorna dados do usuário ou termina com erro
     */
    public static function validate()
    {
        $middleware = new self();
        $auth = $middleware->verificarAutenticacao();
        
        if (!$auth['success']) {
            $middleware->respondUnauthorized($auth['message']);
        }
        
        return $auth['data'];
    }
    
    /**
     * 🆕 Valida que o usuário seja Admin
     */
    public static function validateAdmin()
    {
        $middleware = new self();
        $auth = $middleware->verificarAdmin();
        
        if (!$auth['success']) {
            $middleware->respondUnauthorized($auth['message'], 403);
        }
        
        return $auth['data'];
    }
}
?>