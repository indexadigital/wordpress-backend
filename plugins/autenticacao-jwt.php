<?php
/**
 * Plugin Name: Autenticação JWT
 * Plugin URI: http://seusite.com/plugins/jwt-auth
 * Description: Implementa autenticação JWT com validação de Authorization Bearer para a API REST do WordPress.
 * Version: 1.1
 * Author: Jardiel Valadão
 */

// Dependência : composer require firebase/php-jwt

/*
    // TESTE DA AUTENTICAÇÃO

    REQUEST: 
    curl -X POST http://seusite.com/wp-json/jwt-auth/v1/token \
    -H "Content-Type: application/json" \
    -d '{"username": "seu-usuario", "password": "sua-senha"}'

    RESPONSE: 
    {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "email_usuario": "seuemail@exemplo.com",
        "nome_usuario": "seu-usuario",
        "nome_exibicao": "Seu Nome"
    }

    // DADOS PROTEGIDOS

    REQUEST: 
    curl -X GET http://seusite.com/wp-json/jwt-auth/v1/dados-protegidos \
    -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."


*/

defined('ABSPATH') or die('Acesso direto ao arquivo não permitido!');

// Inclui a biblioteca JWT
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class JWT_Auth {
    private $chave_secreta;

    public function __construct() {
        $this->chave_secreta = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'seu-segredo-muito-secreto';

        // Registra as rotas na API REST
        add_action('rest_api_init', [$this, 'registrar_rotas_rest']);

        // Autenticação e autorização
        add_filter('determine_current_user', [$this, 'determinar_usuario_atual'], 10);
        add_filter('rest_authentication_errors', [$this, 'erros_autenticacao_rest']);
    }

    public function registrar_rotas_rest() {
        // Rota para gerar o token
        register_rest_route('jwt-auth/v1', '/token', [
            'methods' => 'POST',
            'callback' => [$this, 'gerar_token'],
            'permission_callback' => '__return_true'
        ]);

        // Rota protegida de exemplo que retorna settings e options
        register_rest_route('jwt-auth/v1', '/dados-protegidos', [
            'methods' => 'GET',
            'callback' => [$this, 'dados_protegidos'],
            'permission_callback' => [$this, 'validar_token']
        ]);
    }

    // Função para gerar o token JWT
    public function gerar_token($requisicao) {
        $nome_usuario = $requisicao->get_param('username');
        $senha = $requisicao->get_param('password');

        $usuario = wp_authenticate($nome_usuario, $senha);

        if (is_wp_error($usuario)) {
            return new WP_Error(
                'jwt_auth_falhou',
                'Credenciais inválidas',
                ['status' => 401]
            );
        }

        $emitido_em = time();
        $expiracao = $emitido_em + (DAY_IN_SECONDS * 7); // Token válido por 7 dias

        $token = [
            'iss' => get_bloginfo('url'),
            'iat' => $emitido_em,
            'exp' => $expiracao,
            'data' => [
                'usuario' => [
                    'id' => $usuario->ID,
                ]
            ]
        ];

        $jwt = JWT::encode($token, $this->chave_secreta, 'HS256');

        return [
            'token' => $jwt,
            'email_usuario' => $usuario->user_email,
            'nome_usuario' => $usuario->user_nicename,
            'nome_exibicao' => $usuario->display_name,
        ];
    }

    // Validação do token e retorno do usuário atual
    public function determinar_usuario_atual($usuario) {
        $erro_auth_rest = $this->validar_token(true);

        if (is_wp_error($erro_auth_rest)) {
            return null;
        }

        return $usuario;
    }

    public function erros_autenticacao_rest($erro) {
        if ($erro) {
            return $erro;
        }

        $erro_auth_rest = $this->validar_token();

        if (is_wp_error($erro_auth_rest)) {
            return $erro_auth_rest;
        }

        return null;
    }

    // Função para validar o token JWT
    public function validar_token($retornar_resposta = false) {
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

        if (!$auth) {
            return new WP_Error(
                'jwt_auth_sem_cabecalho_auth',
                'Cabeçalho de Autorização não encontrado.',
                ['status' => 403]
            );
        }

        list($token) = sscanf($auth, 'Bearer %s');

        if (!$token) {
            return new WP_Error(
                'jwt_auth_cabecalho_auth_ruim',
                'Token não encontrado no Authorization Bearer.',
                ['status' => 403]
            );
        }

        try {
            $token_decodificado = JWT::decode($token, new Key($this->chave_secreta, 'HS256'));
        } catch (Exception $e) {
            return new WP_Error(
                'jwt_auth_token_invalido',
                'Token inválido: ' . $e->getMessage(),
                ['status' => 403]
            );
        }

        if ($retornar_resposta) {
            return $token_decodificado;
        }

        return true;
    }

    // Função para retornar dados protegidos
    public function dados_protegidos() {
        // Exemplo de dados de options e settings do WordPress
        $site_title = get_option('blogname'); // Obtém o nome do site
        $admin_email = get_option('admin_email'); // Obtém o email do admin
        $timezone = get_option('timezone_string'); // Fuso horário

        // Dados protegidos retornados pela API
        return [
            'site_title' => $site_title,
            'admin_email' => $admin_email,
            'timezone' => $timezone,
            'mensagem' => 'Esses são dados protegidos da API!'
        ];
    }
}

// Instancia a classe para inicializar o plugin
new JWT_Auth();
