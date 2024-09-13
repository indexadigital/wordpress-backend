<?php 

// Necessário para exibir uma galeria do MetaSlider em um iframe num projeto React. Mantendo todas as configurações de responsividade do MetaSlider.

add_filter( 'register_post_type_args', 'customize_metaslider_post_type', 10, 2 );
function customize_metaslider_post_type( $args, $post_type ) {
    
    if ( in_array($post_type, ['ml-slide', 'ml-slider'] ) ) {
        $args['show_in_rest'] = true;
    }
    return $args;
}


add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/metaslider_shortcode', [
        'methods' => 'GET',
        'callback' => 'generate_metaslider_html',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ],
        ],
    ]);
});
function generate_metaslider_html($request) {
    // Obtém o parâmetro 'id' da solicitação
    $id = intval($request->get_param('id'));

    // Gera o shortcode com o ID fornecido
    $shortcode = '[metaslider id="' . $id . '"]';

    // Inicia o buffer de saída para capturar os scripts enfileirados
    ob_start();

    // Processa o shortcode para obter o HTML
    $html_output = do_shortcode($shortcode);

    // Garante que os scripts e estilos sejam enfileirados
    wp_enqueue_scripts();

    // Adiciona qualquer script ou estilo adicional que pode ter sido enfileirado pelo shortcode
    do_action('wp_footer');

    // Captura os scripts e estilos enfileirados
    $output = ob_get_clean();

	// Definir o cabeçalho para permitir acesso de qualquer origem
    header("Access-Control-Allow-Origin: *");
    // Definir o tipo de conteúdo para text/html
    header("Content-Type: text/html; charset=UTF-8");

    if (!empty($html_output)) {
        echo $html_output . $output;
    } else {
        echo '<p>Não foi possível gerar o HTML para o ID fornecido.</p>';
    }

    exit;
}
