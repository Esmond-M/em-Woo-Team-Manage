<?php
/**
 * My Content (WooCommerce My Account endpoint)
 *
 * Shows the logged-in user every product they can access through a team,
 * with a direct download link for each file.
 */
if (!is_user_logged_in()) {
    return;
}

use emWooTeamManage\init_plugin\Classes\TeamContentAccess;

$current_user_id = get_current_user_id();
$ledger          = new TeamContentAccess();
$user_products   = $ledger->get_user_products($current_user_id);

$downloads_by_product = [];
if (function_exists('wc_get_customer_available_downloads')) {
    foreach (wc_get_customer_available_downloads($current_user_id) as $download) {
        $downloads_by_product[(int) $download['product_id']][] = $download;
    }
}
?>
<div class="emwtm-my-content">
    <?php if (empty($user_products)) : ?>
        <p>You don't have access to any team content yet. Your team leader will appear here once content is shared with your team.</p>
    <?php else : ?>
        <p>Content shared with you through your team. Use the download links below to open each item.</p>
        <table class="emwtm-my-content-table">
            <thead>
                <tr>
                    <th>Content</th>
                    <th>Shared By</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_products as $entry) : ?>
                    <?php
                    $product_id = (int) $entry['product_id'];
                    $leader_id  = (int) $entry['leader_id'];
                    $product    = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
                    $leader     = get_user_by('id', $leader_id);
                    $is_own     = $leader_id === $current_user_id;
                    ?>
                    <tr>
                        <td><?php echo esc_html($product ? $product->get_name() : 'Product #' . $product_id); ?></td>
                        <td>
                            <?php if ($is_own) : ?>
                                Your own purchase
                            <?php else : ?>
                                <?php echo esc_html($leader ? $leader->display_name : 'Your team leader'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($downloads_by_product[$product_id])) : ?>
                                <?php foreach ($downloads_by_product[$product_id] as $download) : ?>
                                    <a class="emwtm-my-content-download" href="<?php echo esc_url($download['download_url']); ?>">
                                        <?php echo esc_html($download['download_name']); ?>
                                    </a><br />
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span class="emwtm-my-content-pending">Download link unavailable — contact your team leader.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
