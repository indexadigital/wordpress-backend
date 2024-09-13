<?php
/**
 * Plugin Name: Expositor de ACF
 * Plugin URI: http://seusite.com/plugins/expositor-acf
 * Description: Expõe todos os campos personalizados avançados (ACF) de todos os tipos de post.
 * Version: 1.0
 * Author: Jardiel Valadão
 */

// Certifique-se de que o WordPress está rodando
defined('ABSPATH') or die('Acesso direto ao arquivo não permitido!');

// Função para registrar o endpoint da API REST
function registrar_endpoint_acf() {
    register_rest_route('expositor-acf/v1', '/campos', array(
        'methods' => 'GET',
        'callback' => 'obter_campos_acf',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'registrar_endpoint_acf');

// Função para obter todos os campos ACF de todos os tipos de post
function obter_campos_acf() {
    if (!function_exists('acf_get_field_groups')) {
        return new WP_Error('acf_not_active', 'O plugin Advanced Custom Fields não está ativo.', array('status' => 404));
    }

    $todos_campos = array();

    $tipos_post = get_post_types(array('public' => true), 'names');

    foreach ($tipos_post as $tipo_post) {
        $grupos_campo = acf_get_field_groups(array('post_type' => $tipo_post));

        foreach ($grupos_campo as $grupo) {
            $campos = acf_get_fields($grupo['key']);

            foreach ($campos as $campo) {
                $todos_campos[$tipo_post][] = array(
                    'nome' => $campo['name'],
                    'rotulo' => $campo['label'],
                    'tipo' => $campo['type']
                );
            }
        }
    }

    return new WP_REST_Response($todos_campos, 200);
}

// Adicionar página de administração
function adicionar_pagina_admin() {
    add_menu_page(
        'Expositor de ACF',
        'Expositor de ACF',
        'manage_options',
        'expositor-acf',
        'renderizar_pagina_admin',
        'dashicons-schedule',
        30
    );
}
add_action('admin_menu', 'adicionar_pagina_admin');

// Renderizar a página de administração
function renderizar_pagina_admin() {
    ?>
    <div class="wrap">
        <h1>Expositor de ACF</h1>
        <p>Este plugin expõe todos os campos personalizados avançados (ACF) de todos os tipos de post através de um endpoint da API REST.</p>
        <p>Endpoint: <code><?php echo rest_url('expositor-acf/v1/campos'); ?></code></p>
        <p>Nota: Apenas usuários com permissão para editar posts podem acessar este endpoint.</p>
    </div>
    <?php
}