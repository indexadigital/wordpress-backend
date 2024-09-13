<?php
/*
Plugin Name: Planos de Saúde API
Description: Plugin para registrar planos de saúde e criar um endpoint customizado na REST API e GraphQL do WordPress.
Version: 1.0
Author: Jardiel Valadão
*/

function registrar_planos_saude_cpt() {
    $args = [
        'label' => __('Planos de Saúde', 'textdomain'),
        'public' => true,
        'show_in_rest' => true,
        'show_in_graphql' => true,
        'graphql_single_name' => 'planoDeSaude',
        'graphql_plural_name' => 'planosDeSaude',
        'supports' => ['title', 'editor', 'custom-fields'],
        'rewrite' => ['slug' => 'planos-saude'],
        ];
    register_post_type('planos_saude', $args);
}
add_action('init', 'registrar_planos_saude_cpt');

function endpoint_planos_saude_disponibilidade($data) {
    $disponivel = $data['disponivel'] ?? true;
    $query = new WP_Query([
        'post_type' => 'planos_saude',
        'meta_query' => [
            [
                'key' => 'disponibilidade',
                'value' => $disponivel,
                'compare' => '=',
            ],
        ],
    ]);
    if ($query->have_posts()) {
        $planos = [];
        while ($query->have_posts()) {
            $query->the_post();
            $planos[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'content' => get_the_content(),
                'disponibilidade' => get_post_meta(get_the_ID(), 'disponibilidade', true),
            ];
        }
        return rest_ensure_response($planos);
    }
    return new WP_Error('sem_planos', 'Nenhum plano encontrado', ['status' => 404]);
}

function registrar_endpoint_customizado_planos_saude() {
    register_rest_route('custom/v1', '/planos-saude', [
        'methods' => 'GET',
        'callback' => 'endpoint_planos_saude_disponibilidade',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}
add_action('rest_api_init', 'registrar_endpoint_customizado_planos_saude');
