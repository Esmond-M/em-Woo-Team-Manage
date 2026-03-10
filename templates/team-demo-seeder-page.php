<?php
/**
 * Demo Seeder Admin Page
 */
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

use emWooTeamManage\init_plugin\Classes\TeamDemoSeeder;

$seeder        = new TeamDemoSeeder();
$status        = $seeder->get_status();
$has_demo_data = ( $status['leaders'] + $status['subordinates'] ) > 0;
?>
<div class="wrap">
    <h1>Demo Data Seeder</h1>
    <p class="description">
        Generate demo team leaders and subordinates for presentations or testing.
        All accounts use <code>@example.com</code> addresses and are tagged for clean removal.
        Seeding is <strong>idempotent</strong> — running it twice won't create duplicates.
    </p>

    <!-- ── Status ──────────────────────────────────────────────────────────── -->
    <div class="card" style="max-width:600px;margin-top:20px;padding:16px 20px;">
        <h2 style="margin-top:4px;">Current Demo State</h2>
        <table class="form-table" role="presentation" style="margin:0;">
            <tr>
                <th scope="row">Demo Team Leaders</th>
                <td>
                    <strong id="emwtm-count-leaders"><?php echo esc_html( $status['leaders'] ); ?></strong>
                    <span style="color:#888;">/ <?php echo TeamDemoSeeder::MAX_LEADERS; ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">Demo Subordinates</th>
                <td><strong id="emwtm-count-subs"><?php echo esc_html( $status['subordinates'] ); ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- ── Seed ────────────────────────────────────────────────────────────── -->
    <div class="card" style="max-width:600px;margin-top:16px;padding:16px 20px;">
        <h2 style="margin-top:4px;">Seed Demo Data</h2>
        <p>
            Creates up to <strong><?php echo TeamDemoSeeder::MAX_LEADERS; ?> team leaders</strong>
            (<code>demo.leader.1@example.com</code>, <code>demo.leader.2@example.com</code>)
            and the chosen number of subordinates for each
            (<code>demo.sub.1.001@example.com</code> … <code>demo.sub.2.050@example.com</code>).
        </p>
        <table class="form-table" role="presentation" style="margin:0 0 12px;">
            <tr>
                <th scope="row"><label for="emwtm-subs-input">Subordinates per leader</label></th>
                <td>
                    <input
                        type="number"
                        id="emwtm-subs-input"
                        value="<?php echo TeamDemoSeeder::MAX_SUBORDINATES; ?>"
                        min="1"
                        max="<?php echo TeamDemoSeeder::MAX_SUBORDINATES; ?>"
                        class="small-text"
                    />
                    <span class="description">max <?php echo TeamDemoSeeder::MAX_SUBORDINATES; ?></span>
                </td>
            </tr>
        </table>
        <button type="button" class="button button-primary" id="emwtm-seed-btn">
            &#9654;&nbsp;Seed Demo Data
        </button>
        <div id="emwtm-seed-result" style="display:none;margin-top:12px;"></div>
    </div>

    <!-- ── Clear ───────────────────────────────────────────────────────────── -->
    <div class="card" style="max-width:600px;margin-top:16px;padding:16px 20px;">
        <h2 style="margin-top:4px;">Clear Demo Data</h2>
        <p>
            Permanently <strong>deletes all demo users</strong> and removes their team
            relationships from the database. This action cannot be undone.
        </p>
        <button
            type="button"
            class="button button-secondary"
            id="emwtm-clear-btn"
            <?php echo ! $has_demo_data ? 'disabled' : ''; ?>
        >
            🗑&nbsp;Clear Demo Data
        </button>
        <div id="emwtm-clear-result" style="display:none;margin-top:12px;"></div>
    </div>
</div>

<script>
(function ($) {
    var ajaxurl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var seedNonce  = <?php echo wp_json_encode( wp_create_nonce( 'emwtm_demo_seed' ) ); ?>;
    var clearNonce = <?php echo wp_json_encode( wp_create_nonce( 'emwtm_demo_clear' ) ); ?>;

    function showMsg(selector, html, isError) {
        $(selector)
            .removeClass('notice-success notice-error')
            .addClass('notice inline ' + (isError ? 'notice-error' : 'notice-success'))
            .html('<p>' + html + '</p>')
            .show();
    }

    function updateCounters(status) {
        if (!status) return;
        $('#emwtm-count-leaders').text(status.leaders);
        $('#emwtm-count-subs').text(status.subordinates);
        var hasData = (status.leaders + status.subordinates) > 0;
        $('#emwtm-clear-btn').prop('disabled', !hasData);
    }

    $('#emwtm-seed-btn').on('click', function () {
        var $btn = $(this);
        var subs = parseInt($('#emwtm-subs-input').val(), 10) || <?php echo TeamDemoSeeder::MAX_SUBORDINATES; ?>;
        $btn.prop('disabled', true).text('Seeding…');
        $('#emwtm-seed-result').hide();

        $.post(ajaxurl, {
            action:          'emwtm_seed_demo',
            _nonce:          seedNonce,
            subs_per_leader: subs,
        }, function (res) {
            $btn.prop('disabled', false).html('&#9654;&nbsp;Seed Demo Data');
            if (res.success) {
                var d = res.data;
                var msg = 'Done. '
                    + 'Leaders created: <strong>' + d.leaders_created + '</strong>, '
                    + 'Subordinates created: <strong>' + d.subs_created + '</strong>'
                    + (d.skipped > 0 ? ', Skipped (already exist): <strong>' + d.skipped + '</strong>' : '')
                    + '.';
                showMsg('#emwtm-seed-result', msg, false);
                updateCounters(d.status);
            } else {
                showMsg('#emwtm-seed-result', res.data.message || 'An error occurred.', true);
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('&#9654;&nbsp;Seed Demo Data');
            showMsg('#emwtm-seed-result', 'Request failed. Please try again.', true);
        });
    });

    $('#emwtm-clear-btn').on('click', function () {
        if (!confirm('Delete all demo users and their team relationships? This cannot be undone.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Clearing…');
        $('#emwtm-clear-result').hide();

        $.post(ajaxurl, {
            action: 'emwtm_clear_demo',
            _nonce: clearNonce,
        }, function (res) {
            $btn.prop('disabled', false).html('🗑&nbsp;Clear Demo Data');
            if (res.success) {
                showMsg('#emwtm-clear-result', 'Deleted <strong>' + res.data.deleted + '</strong> demo user(s).', false);
                updateCounters(res.data.status);
            } else {
                showMsg('#emwtm-clear-result', res.data.message || 'An error occurred.', true);
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('🗑&nbsp;Clear Demo Data');
            showMsg('#emwtm-clear-result', 'Request failed. Please try again.', true);
        });
    });
}(jQuery));
</script>
