<?php
/**
 * Content Access Admin Page
 *
 * Assigns downloadable products to a team leader's whole team and lists
 * every active assignment across the site.
 */
if (!current_user_can('manage_options')) {
    return;
}

use emWooTeamManage\init_plugin\Classes\TeamContentAccess;

if (!function_exists('wc_get_products')) {
    echo '<div class="wrap"><h1>Content Access</h1><div class="notice notice-warning"><p>WooCommerce must be active to assign content.</p></div></div>';
    return;
}

$ledger        = new TeamContentAccess();
$team_leaders  = get_users(['role__in' => ['team_leader']]);
$selected_leader = isset($_GET['leader_id']) ? (int) $_GET['leader_id'] : 0;
$assignments   = $ledger->get_all_team_products();

$downloadable_products = wc_get_products([
    'limit'        => -1,
    'downloadable' => true,
    'status'       => 'publish',
    'orderby'      => 'title',
    'order'        => 'ASC',
]);
?>
<div class="wrap">
    <h1>Content Access</h1>
    <p class="description">
        Give a team leader access to downloadable products without a purchase. Everyone currently on
        their team gets access immediately, and anyone added later is granted access automatically.
    </p>

    <?php if (empty($team_leaders)) : ?>
        <div class="notice notice-warning"><p>No team leaders exist yet.</p></div>
    <?php else : ?>
        <div class="card" style="max-width:720px;margin-top:20px;padding:16px 20px;">
            <h2 style="margin-top:4px;">Assign Content</h2>
            <?php if (empty($downloadable_products)) : ?>
                <p class="description">No downloadable products found. Create a downloadable WooCommerce product first.</p>
            <?php else : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="emwtm-assign-leader">Team Leader</label></th>
                        <td>
                            <select id="emwtm-assign-leader">
                                <?php foreach ($team_leaders as $leader) : ?>
                                    <option value="<?php echo esc_attr($leader->ID); ?>" <?php selected($leader->ID, $selected_leader); ?>>
                                        <?php echo esc_html($leader->display_name . ' (' . $leader->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="emwtm-assign-products">Products</label></th>
                        <td>
                            <select id="emwtm-assign-products" multiple size="8" style="min-width:320px;">
                                <?php foreach ($downloadable_products as $product) : ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Hold Ctrl (Cmd on Mac) to select more than one.</p>
                        </td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" id="emwtm-assign-btn">Assign Content</button>
                <div id="emwtm-assign-result" style="display:none;margin-top:12px;"></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:28px;">Current Assignments</h2>
    <?php if (empty($assignments)) : ?>
        <p class="description">Nothing is assigned yet.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Team Leader</th>
                    <th>Product</th>
                    <th>Source</th>
                    <th>Users With Access</th>
                    <th>Granted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $row) : ?>
                    <?php
                    $leader  = get_user_by('id', (int) $row['leader_id']);
                    $product = wc_get_product((int) $row['product_id']);
                    $is_assignment = $row['source'] === TeamContentAccess::SOURCE_ASSIGNMENT;
                    ?>
                    <tr>
                        <td><?php echo esc_html($leader ? $leader->display_name : 'Unknown user #' . (int) $row['leader_id']); ?></td>
                        <td><?php echo esc_html($product ? $product->get_name() : 'Deleted product #' . (int) $row['product_id']); ?></td>
                        <td><?php echo $is_assignment ? 'Assigned by admin' : 'Purchased'; ?></td>
                        <td><?php echo esc_html((int) $row['user_count']); ?></td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format'), $row['granted_at'])); ?></td>
                        <td>
                            <button
                                type="button"
                                class="button-link-delete emwtm-unassign-btn"
                                data-leader-id="<?php echo esc_attr($row['leader_id']); ?>"
                                data-product-id="<?php echo esc_attr($row['product_id']); ?>"
                            >Remove Access</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function ($) {
    var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce   = <?php echo wp_json_encode(wp_create_nonce('emwtm_content_assignment')); ?>;

    function showMsg(selector, html, isError) {
        $(selector)
            .removeClass('notice-success notice-error')
            .addClass('notice inline ' + (isError ? 'notice-error' : 'notice-success'))
            .html('<p>' + html + '</p>')
            .show();
    }

    $('#emwtm-assign-btn').on('click', function () {
        var $btn = $(this);
        var productIds = $('#emwtm-assign-products').val() || [];
        if (!productIds.length) {
            showMsg('#emwtm-assign-result', 'Select at least one product.', true);
            return;
        }

        $btn.prop('disabled', true).text('Assigning…');
        $('#emwtm-assign-result').hide();

        $.post(ajaxurl, {
            action: 'emwtm_assign_content',
            _nonce: nonce,
            leader_id: $('#emwtm-assign-leader').val(),
            product_ids: productIds,
        }, function (res) {
            $btn.prop('disabled', false).text('Assign Content');
            if (res.success) {
                showMsg('#emwtm-assign-result', res.data.message + ' Reloading…', false);
                window.location.reload();
            } else {
                showMsg('#emwtm-assign-result', res.data.message || 'An error occurred.', true);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Assign Content');
            showMsg('#emwtm-assign-result', 'Request failed. Please try again.', true);
        });
    });

    $('.emwtm-unassign-btn').on('click', function () {
        if (!confirm('Remove access to this product for the whole team?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Removing…');

        $.post(ajaxurl, {
            action: 'emwtm_unassign_content',
            _nonce: nonce,
            leader_id: $btn.data('leader-id'),
            product_id: $btn.data('product-id'),
        }, function (res) {
            if (res.success) {
                window.location.reload();
            } else {
                $btn.prop('disabled', false).text('Remove Access');
                alert(res.data.message || 'An error occurred.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Remove Access');
            alert('Request failed. Please try again.');
        });
    });
}(jQuery));
</script>
