<?php
// 1. Custom Post Type
function registrar_cpt_produto() {
    $labels = array(
        'name'               => 'Produtos',
        'singular_name'      => 'Produto',
        'menu_name'          => 'Produtos',
        'add_new'            => 'Adicionar Novo',
        'add_new_item'       => 'Adicionar Novo Produto',
        'edit_item'          => 'Editar Produto',
        'new_item'           => 'Novo Produto',
        'view_item'          => 'Ver Produto',
        'search_items'       => 'Buscar Produtos',
        'not_found'          => 'Nenhum produto encontrado',
        'not_found_in_trash' => 'Nenhum produto encontrado na lixeira',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'produto'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
    );

    register_post_type('produto', $args);
}
add_action('init', 'registrar_cpt_produto');

// 2. Shortcode personalizado
function shortcode_lista_produtos($atts) {
    $atts = shortcode_atts(array(
        'limite' => 5,
        'categoria' => '',
    ), $atts, 'lista_produtos');

    $args = array(
        'post_type' => 'produto',
        'posts_per_page' => $atts['limite'],
        'tax_query' => array(),
    );

    if (!empty($atts['categoria'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'categoria_produto',
            'field'    => 'slug',
            'terms'    => $atts['categoria'],
        );
    }

    $query = new WP_Query($args);

    $output = '<ul class="lista-produtos">';
    while ($query->have_posts()) {
        $query->the_post();
        $output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
    }
    $output .= '</ul>';

    wp_reset_postdata();

    return $output;
}
add_shortcode('lista_produtos', 'shortcode_lista_produtos');

// 3. Hooks personalizados
function meu_hook_personalizado($nome, $idade) {
    do_action('antes_saudacao', $nome);
    
    echo "Olá, {$nome}! Você tem {$idade} anos.";
    
    do_action('depois_saudacao', $nome, $idade);
}

function adicionar_titulo($nome) {
    echo "<h2>Saudação para {$nome}</h2>";
}
add_action('antes_saudacao', 'adicionar_titulo');

function adicionar_rodape($nome, $idade) {
    echo "<p>Esta mensagem foi gerada em " . current_time('mysql') . "</p>";
}
add_action('depois_saudacao', 'adicionar_rodape', 10, 2);

// Uso: meu_hook_personalizado('João', 30);

// 4. API REST personalizada
function registrar_rota_api_produtos() {
    register_rest_route('meu-plugin/v1', '/produtos', array(
        'methods' => 'GET',
        'callback' => 'obter_produtos',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'registrar_rota_api_produtos');

function obter_produtos($request) {
    $args = array(
        'post_type' => 'produto',
        'posts_per_page' => 10,
    );

    $produtos = get_posts($args);

    if (empty($produtos)) {
        return new WP_Error('sem_produtos', 'Nenhum produto encontrado', array('status' => 404));
    }

    $data = array();

    foreach ($produtos as $produto) {
        $data[] = array(
            'id' => $produto->ID,
            'titulo' => $produto->post_title,
            'conteudo' => $produto->post_content,
            'preco' => get_post_meta($produto->ID, 'preco', true),
        );
    }

    return new WP_REST_Response($data, 200);
}

// 5. Classe para Widget personalizado
class Widget_Produtos_Recentes extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'widget_produtos_recentes',
            'Produtos Recentes',
            array('description' => 'Exibe os produtos mais recentes.')
        );
    }

    public function widget($args, $instance) {
        $titulo = !empty($instance['titulo']) ? $instance['titulo'] : 'Produtos Recentes';
        $numero = !empty($instance['numero']) ? absint($instance['numero']) : 5;

        echo $args['before_widget'];
        echo $args['before_title'] . apply_filters('widget_title', $titulo) . $args['after_title'];

        $query_args = array(
            'post_type' => 'produto',
            'posts_per_page' => $numero,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $produtos = new WP_Query($query_args);

        if ($produtos->have_posts()) {
            echo '<ul>';
            while ($produtos->have_posts()) {
                $produtos->the_post();
                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo 'Nenhum produto encontrado.';
        }

        wp_reset_postdata();

        echo $args['after_widget'];
    }

    public function form($instance) {
        $titulo = !empty($instance['titulo']) ? $instance['titulo'] : 'Produtos Recentes';
        $numero = !empty($instance['numero']) ? absint($instance['numero']) : 5;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('titulo'); ?>">Título:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('titulo'); ?>" name="<?php echo $this->get_field_name('titulo'); ?>" type="text" value="<?php echo esc_attr($titulo); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('numero'); ?>">Número de produtos a exibir:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('numero'); ?>" name="<?php echo $this->get_field_name('numero'); ?>" type="number" step="1" min="1" value="<?php echo $numero; ?>" size="3">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['titulo'] = (!empty($new_instance['titulo'])) ? sanitize_text_field($new_instance['titulo']) : '';
        $instance['numero'] = (!empty($new_instance['numero'])) ? absint($new_instance['numero']) : 5;
        return $instance;
    }
}

function registrar_widget_produtos_recentes() {
    register_widget('Widget_Produtos_Recentes');
}
add_action('widgets_init', 'registrar_widget_produtos_recentes');