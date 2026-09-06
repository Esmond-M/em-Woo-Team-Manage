<?php
/**
 * Team Leader Admin Page
 */
if (!emWooTeamManage\init_plugin\Classes\TeamManageCore::can_manage_team_pages()) {
    return;
}

global $wpdb;
$table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
$is_admin = current_user_can('manage_options');

if ($is_admin) {
    $all_leaders = get_users(['role__in' => ['team_leader']]);
    if (empty($all_leaders)) {
        echo '<div class="notice notice-warning"><p>No team leaders exist yet.</p></div>';
        return;
    }
    $active_leader_id = isset($_GET['leader_id']) ? (int) $_GET['leader_id'] : $all_leaders[0]->ID;
    $valid_ids = array_column($all_leaders, 'ID');
    if (!in_array($active_leader_id, $valid_ids)) {
        $active_leader_id = $all_leaders[0]->ID;
    }
    $teamLeaderID = $active_leader_id;
} elseif (current_user_can('team_leader')) {
    $teamLeaderID = get_current_user_id();
} else {
    return;
}

$subordinate_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT subordinate_id FROM $table WHERE leader_id = %d", $teamLeaderID
));
$teamSubordinates = [];
if (!empty($subordinate_ids)) {
    $teamSubordinates = get_users([
        'include' => $subordinate_ids,
        'role__in' => ['team_subordinate'],
    ]);
}
$number_of_users = count($teamSubordinates);
?>
    <div class="subordinate-container">
        <div class="team-page-hero">
            <div>
                <span class="team-page-kicker">Team Management</span>
                <h2>Manage Your Subordinates</h2>
                <p>Review team members, update details, and handle account actions from one admin view.</p>
            </div>
            <div class="team-stat-card" aria-label="Number of subordinates">
                <span class="team-stat-value"><?php echo esc_html( $number_of_users ); ?></span>
                <span class="team-stat-label">Subordinates</span>
            </div>
        </div>
        <div class="team-toolbar">
            <?php if ($is_admin): ?>
            <form class="emulation-form" method="GET" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr(isset($_GET['page']) ? sanitize_key($_GET['page']) : ''); ?>" />
                <label for="admin-leader-select"><strong>Managing Team Leader:</strong></label>
                <select id="admin-leader-select" name="leader_id" onchange="this.form.submit()">
                    <?php foreach ($all_leaders as $l): ?>
                        <option value="<?php echo esc_attr($l->ID); ?>" <?php selected($l->ID, $active_leader_id); ?>>
                            <?php echo esc_html($l->display_name . ' (' . $l->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <form id="export-team-csv-form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                <input type="hidden" name="action" value="export_team_csv" />
                <input type="hidden" name="_export_nonce" value="<?php echo esc_attr(wp_create_nonce('export_team_csv')); ?>" />
                <input type="hidden" name="leaderID" value="<?php echo esc_attr($teamLeaderID); ?>" />
                <button type="submit" class="button">Export Team CSV</button>
            </form>
        </div>
        <form id="team-leader-form" method="POST" action="">
            <fieldset>
                <legend>Subordinate Actions</legend>
                <div class="team-bulk-action-panel">
                    <div>
                        <span class="team-panel-label">Bulk Action</span>
                        <select name="teamLeaderSelectOption" form="team-leader-form" aria-describedby="team-bulk-action-help">
                            <option value="delete">Remove from Team</option>
                            <option value="resend">Send Password Reset Link</option>
                        </select>
                        <p id="team-bulk-action-help">Select team members below, choose an action, then submit.</p>
                    </div>
                </div>
                <table id="team-subordinates-table" data-leader-id="<?php echo esc_attr( $teamLeaderID ); ?>" data-edit-nonce="<?php echo esc_attr( wp_create_nonce( 'edit_subordinate_action' ) ); ?>">
                    <tr>
                        <th>Subordinate Email</th>
                        <th>Subordinate Name</th>
                        <th>Select</th>
                        <th>Edit</th>
                    </tr>
                    <?php foreach ( $teamSubordinates as $user ) : ?>
                        <tr class="subordinate-row" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
                            <td>
                                <span class="subordinate-value subordinate-email-value"><?php echo esc_html( $user->user_email ); ?></span>
                                <input class="subordinate-edit-field subordinate-email-input" type="email" value="<?php echo esc_attr( $user->user_email ); ?>" required disabled hidden />
                            </td>
                            <td>
                                <span class="subordinate-value subordinate-name-value"><?php echo esc_html( $user->display_name ); ?></span>
                                <input class="subordinate-edit-field subordinate-name-input" type="text" value="<?php echo esc_attr( $user->display_name ); ?>" required disabled hidden />
                            </td>
                            <td><input type="checkbox" name="userID[]" value="<?php echo esc_attr( $user->ID ); ?>" /></td>
                            <td>
                                <div class="subordinate-row-actions">
                                    <button type="button" class="edit-subordinate-btn">Edit</button>
                                    <button type="button" class="save-subordinate-btn button button-primary" hidden>Save</button>
                                    <button type="button" class="cancel-subordinate-edit-btn button" hidden>Cancel</button>
                                </div>
                                <span class="subordinate-edit-message" role="status" hidden></span>
                                <?php if ($is_admin && in_array('team_subordinate', (array) $user->roles, true) && !in_array('team_leader', (array) $user->roles, true)): ?>
                                    <button type="button" class="button-link-delete emwtm-delete-user-btn" data-user-id="<?php echo esc_attr($user->ID); ?>">Permanently Delete Account</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </fieldset>
            <?php wp_nonce_field( 'team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field' ); ?>
            <input type="hidden" name="action" value="team_Leader_Form_Submission" />
            <input type="hidden" name="leaderID" value="<?php echo esc_attr($teamLeaderID); ?>" />
            <label><input type="checkbox" name="confirm_removal" value="1" /> Confirm removing selected users from this team</label>
            <input type="submit" value="Submit" class="button button-primary">
        </form>
    </div>
    <?php
