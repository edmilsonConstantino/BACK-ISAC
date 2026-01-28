<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHandler {
    private $secret_key;
    private $issuer;
    private $audience;
    private $token_expiration;
    private $refresh_token_expiration;

    public function __construct() {
        // Configurações (melhor prática: usar variáveis de ambiente)
        $this->secret_key = getenv('JWT_SECRET') ?: "isac_sistema_academico_2026_@#$%SECURE";
        $this->issuer = getenv('JWT_ISSUER') ?: "http://localhost";
        $this->audience = getenv('JWT_AUDIENCE') ?: "http://localhost";
        $this->token_expiration = 3600; // 1 hora
        $this->refresh_token_expiration = 604800; // 7 dias
    }

    /**
     * Gera um Access Token
     * @param int $user_id
     * @param string $email
     * @param string $role (admin, academic_admin, teacher, student)
     * @return string JWT Token
     */
    public function generateToken($user_id, $email, $role, $user_type = null) {
        $issued_at = time();
        $expiration_time = $issued_at + $this->token_expiration;

        $payload = array(
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "nbf" => $issued_at, // Not before
            "data" => array(
                "user_id" => $user_id,
                "email" => $email,
                "role" => $role,
                "user_type" => $user_type ?? $role
            )
        );

        return JWT::encode($payload, $this->secret_key, 'HS256');
    }

    /**
     * Gera um Refresh Token
     * @param int $user_id
     * @param string $user_type (admin, academic_admin, student, teacher)
     * @return string JWT Refresh Token
     */
    public function generateRefreshToken($user_id, $user_type = '') {
        $issued_at = time();
        $expiration_time = $issued_at + $this->refresh_token_expiration;

        $payload = array(
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "data" => array(
                "user_id" => $user_id,
                "user_type" => $user_type,
                "type" => "refresh"
            )
        );

        return JWT::encode($payload, $this->secret_key, 'HS256');
    }

    /**
     * Valida um token JWT
     * @param string $token
     * @return object|false Dados do token ou false se inválido
     */
    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));
            
            // Verificar se o token não expirou
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false;
            }
            
            return $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            error_log("Token expirado: " . $e->getMessage());
            return false;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            error_log("Assinatura inválida: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("Erro ao validar token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extrai os dados do payload do token
     * @param string $token
     * @return object|false
     */
    public function getTokenData($token) {
        $decoded = $this->validateToken($token);
        if ($decoded && isset($decoded->data)) {
            return $decoded->data;
        }
        return false;
    }

    /**
     * Verifica se o usuário é administrador
     * @param string $token
     * @return bool
     */
    public function isAdmin($token) {
        $data = $this->getTokenData($token);
        return $data && isset($data->user_type) && $data->user_type === 'admin';
    }

    /**
     * Verifica se o usuário é professor (teacher)
     * @param string $token
     * @return bool
     */
    public function isProfessor($token) {
        $data = $this->getTokenData($token);
        return $data && isset($data->user_type) && $data->user_type === 'teacher';
    }

    /**
     * Verifica se o usuário é estudante (student)
     * @param string $token
     * @return bool
     */
    public function isEstudante($token) {
        $data = $this->getTokenData($token);
        return $data && isset($data->user_type) && $data->user_type === 'student';
    }

    /**
     * Obtém o ID do usuário do token
     * @param string $token
     * @return int|false
     */
    public function getUserId($token) {
        $data = $this->getTokenData($token);
        return $data && isset($data->user_id) ? $data->user_id : false;
    }

    /**
     * Obtém o email do usuário do token
     * @param string $token
     * @return string|false
     */
    public function getUserEmail($token) {
        $data = $this->getTokenData($token);
        return $data && isset($data->email) ? $data->email : false;
    }

    /**
     * Verifica se o token está expirado
     * @param string $token
     * @return bool
     */
    public function isTokenExpired($token) {
        $decoded = $this->validateToken($token);
        return $decoded === false;
    }
}
?>