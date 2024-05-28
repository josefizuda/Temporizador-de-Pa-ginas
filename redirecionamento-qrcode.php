<?php
/*
Plugin Name: Redirecionamento QR Code
Description: Redireciona os usuários para uma nova página após um determinado período de tempo, se acessarem a página via link com parâmetro ?acesso=qrcode.
Version: 3.0.2
Author: josef.com.br
Plugin URI: https://josef.com.br
*/

// Adicionar página de configurações ao menu do WordPress
function adicionar_pagina_configuracoes() {
    add_options_page('Configurações do Redirecionamento QR Code', 'Redirecionamento QR Code', 'manage_options', 'redirecionamento-qrcode-config', 'pagina_configuracoes');
}
add_action('admin_menu', 'adicionar_pagina_configuracoes');

// Callback para a página de configurações
function pagina_configuracoes() {
    // Verificar se o usuário atual tem permissão para acessar a página de configurações
    if (!current_user_can('manage_options')) {
        wp_die('Você não tem permissão para acessar esta página.');
    }

    // Salvar as configurações se o formulário foi enviado
    if (isset($_POST['submit'])) {
        $tempo_vida_pagina = intval($_POST['tempo_vida_pagina']); // Converte para inteiro
        update_option('tempo_vida_pagina', $tempo_vida_pagina);

        $tempo_bloqueio_ip = intval($_POST['tempo_bloqueio_ip']); // Converte para inteiro
        update_option('tempo_bloqueio_ip', $tempo_bloqueio_ip);

        $bloqueio_ativado = isset($_POST['bloqueio_ativado']) ? 1 : 0; // Verifica se o checkbox está marcado
        update_option('bloqueio_ativado', $bloqueio_ativado);

        echo '<div class="updated"><p>Configurações salvas com sucesso!</p></div>';
    }

    // Obter o valor atual do tempo de vida da página
    $tempo_vida_pagina = get_option('tempo_vida_pagina', 10); // Valor padrão é 10 minutos

    // Obter o valor atual do tempo de bloqueio de IP
    $tempo_bloqueio_ip = get_option('tempo_bloqueio_ip', 1440); // Valor padrão é 1440 minutos (1 dia)

    // Verificar se o bloqueio de IP está ativado
    $bloqueio_ativado = get_option('bloqueio_ativado', 0);

    // Exibir o formulário de configurações
    ?>
    <div class="wrap">
        <h2>Configurações do Redirecionamento QR Code</h2>
        <form method="post">
            <label for="tempo_vida_pagina">Tempo de vida da página (minutos):</label>
            <input type="number" id="tempo_vida_pagina" name="tempo_vida_pagina" value="<?php echo esc_attr($tempo_vida_pagina); ?>" min="1" step="1" required>
            <p class="description">Defina o tempo de vida da página em minutos.</p>

            <label for="tempo_bloqueio_ip">Tempo de bloqueio do IP (minutos):</label>
            <input type="number" id="tempo_bloqueio_ip" name="tempo_bloqueio_ip" value="<?php echo esc_attr($tempo_bloqueio_ip); ?>" min="1" step="1" required>
            <p class="description">Defina o tempo de bloqueio do IP em minutos após o acesso.</p>

            <label for="bloqueio_ativado">Ativar Bloqueio de IP:</label>
            <input type="checkbox" id="bloqueio_ativado" name="bloqueio_ativado" <?php checked($bloqueio_ativado, 1); ?>>
            <p class="description">Ativar ou desativar o bloqueio de IP.</p>

            <input type="submit" name="submit" class="button button-primary" value="Salvar Configurações">
        </form>

        <h2>Desbloquear IP</h2>
        <form method="post">
            <label for="ip_desbloquear">IP a ser desbloqueado:</label>
            <input type="text" id="ip_desbloquear" name="ip_desbloquear" required>
            <input type="submit" name="desbloquear_ip" class="button button-primary" value="Desbloquear IP">
        </form>

        <h2>IPs Bloqueados</h2>
        <?php
        // Listar os IPs bloqueados
        global $wpdb;
        $query = "SELECT DISTINCT SUBSTRING_INDEX(option_name, '_', -2) AS ip FROM $wpdb->options WHERE option_name LIKE 'ultima_visita_ip_%'";
        $ips_bloqueados = $wpdb->get_results($query);
        if ($ips_bloqueados) {
            echo '<ul>';
            foreach ($ips_bloqueados as $ip) {
                echo '<li>' . $ip->ip . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Nenhum IP bloqueado encontrado.</p>';
        }
        ?>

        <h2>Limpar IPs Bloqueados</h2>
        <form method="post">
            <input type="submit" name="limpar_ips_bloqueados" class="button button-primary" value="Limpar Todos os IPs Bloqueados">
        </form>
    </div>
    <?php
}

// Função para redirecionar os usuários após um determinado período de tempo se acessarem via link com parâmetro ?acesso=qrcode
function redirecionar_apos_tempo_qrcode() {
    // Verificar se é uma página
    if (!is_admin() && is_page()) {
        // Verificar se o parâmetro ?acesso=qrcode está presente na URL
        if (isset($_GET['acesso']) && $_GET['acesso'] === 'qrcode') {
            // Definir o tempo de vida da página
            $tempo_vida_pagina = get_option('tempo_vida_pagina', 10); // Obter o valor do tempo de vida da página (padrão: 10 minutos)

            // Verificar se a página foi acessada dentro do tempo de vida
            if (!isset($_COOKIE['pagina_acessada_qrcode'])) {
                // Configurar um cookie para rastrear a última vez que a página foi acessada
                setcookie('pagina_acessada_qrcode', 'true', time() + $tempo_vida_pagina * 60, '/');

                // Redirecionar para a nova página após o tempo de vida expirar
                add_action('shutdown', function() {
                    wp_redirect(get_site_url() . '/leiaqrcodenovamente/');
                    exit;
                });
            }
        }
    }
}
add_action('template_redirect', 'redirecionar_apos_tempo_qrcode');


// Função para desbloquear um IP específico
function desbloquear_ip() {
    if (isset($_POST['desbloquear_ip'])) {
        // Obter o IP a ser desbloqueado
        $ip_desbloquear = $_POST['ip_desbloquear'];

        // Excluir todas as opções relacionadas ao IP bloqueado
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'ultima_visita_ip_" . esc_sql($ip_desbloquear) . "_%'");

        echo '<div class="updated"><p>O IP ' . esc_html($ip_desbloquear) . ' foi desbloqueado com sucesso!</p></div>';
    }
}
add_action('admin_init', 'desbloquear_ip');

// Função para limpar todos os IPs bloqueados
function limpar_ips_bloqueados() {
    if (isset($_POST['limpar_ips_bloqueados'])) {
        // Excluir todas as opções relacionadas aos IPs bloqueados
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'ultima_visita_ip_%'");

        echo '<div class="updated"><p>Todos os IPs bloqueados foram removidos com sucesso!</p></div>';
    }
}
add_action('admin_init', 'limpar_ips_bloqueados');
?>
