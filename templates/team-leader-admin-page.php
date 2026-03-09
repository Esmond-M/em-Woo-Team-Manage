<?php
/**
 * Team Leader Admin Page
 */
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
        <h2>Manage Your Subordinates</h2>
        <?php if ($is_admin): ?>
        <div class="emulation-form" style="margin-bottom:20px;padding:12px;background:#f8f8f8;border:1px solid #ddd;">
            <form method="GET" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr(isset($_GET['page']) ? sanitize_key($_GET['page']) : ''); ?>" />
                <label for="admin-leader-select"><strong>Managing Team Leader:</strong></label>
                <select id="admin-leader-select" name="leader_id" onchange="this.form.submit()" style="margin-left:8px;">
                    <?php foreach ($all_leaders as $l): ?>
                        <option value="<?php echo esc_attr($l->ID); ?>" <?php selected($l->ID, $active_leader_id); ?>>
                            <?php echo esc_html($l->display_name . ' (' . $l->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
        <ol class="leader-steps">
            <li>Review your list of subordinates below.</li>
            <li>Select one or more users to delete or send a password reset.</li>
            <li>Choose an action and click <strong>Submit</strong>.</li>
        </ol>

        <!-- Export button -->
        <form id="export-team-csv-form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" style="display:inline-block;margin-bottom:16px;">
            <input type="hidden" name="action" value="export_team_csv" />
            <input type="hidden" name="_export_nonce" value="<?php echo esc_attr(wp_create_nonce('export_team_csv')); ?>" />
            <input type="hidden" name="leaderID" value="<?php echo esc_attr($teamLeaderID); ?>" />
            <button type="submit" class="button">⬇ Export Team as CSV</button>
        </form>
        <form id="team-leader-form" method="POST" action="">
            <fieldset>
                <legend>Subordinate Actions</legend>
                <table>
                    <tr>
                        <th>Number of Subordinates</th>
                        <th>Action <span title="Delete removes user. Resend sends password reset email." style="cursor:help;">&#9432;</span></th>
                    </tr>
                    <tr>
                        <td><span><?php echo esc_html( $number_of_users ); ?></span></td>
                        <td>
                            <select name="teamLeaderSelectOption" form="team-leader-form">
                                <option value="delete">Delete</option>
                                <option value="resend">Send Password Reset Link</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th>Subordinate Email</th>
                        <th>Subordinate Name</th>
                        <th>Select</th>
                        <th>Edit</th>
                    </tr>
                    <?php foreach ( $teamSubordinates as $user ) : ?>
                        <tr>
                            <td><span><?php echo esc_html( $user->user_email ); ?></span></td>
                            <td><span><?php echo esc_html( $user->display_name ); ?></span></td>
                            <td><input type="checkbox" name="userID[]" value="<?php echo esc_attr( $user->ID ); ?>" /></td>
                            <td>
                                <button type="button" class="edit-subordinate-btn" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-user-email="<?php echo esc_attr( $user->user_email ); ?>" data-user-name="<?php echo esc_attr( $user->display_name ); ?>">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </fieldset>
            <?php wp_nonce_field( 'team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field' ); ?>
            <input type="hidden" name="action" value="team_Leader_Form_Submission" />
            <input type="hidden" name="leaderID" value="<?php echo esc_attr($teamLeaderID); ?>" />
            <input type="submit" value="Submit" class="button button-primary">
        </form>
    </div>
    <!-- Modal for editing subordinate -->
    <div id="editSubordinateModal" style="display:none;">
        <form id="editSubordinateForm" method="POST" >
            <input type="hidden" name="edit_user_id" id="edit_user_id" value="" />
            <label for="edit_user_email">Email:</label>
            <input type="email" name="edit_user_email" id="edit_user_email" value="" required />
            <label for="edit_user_name">Name:</label>
            <input type="text" name="edit_user_name" id="edit_user_name" value="" required />
            <?php wp_nonce_field( 'edit_subordinate_action', 'edit_subordinate_nonce' ); ?>
            <input type="hidden" name="action" value="edit_subordinate" />
            <input type="submit" value="Save" class="button button-primary">
            <button type="button" id="closeEditModal" class="button">Cancel</button>
        </form>
    </div>
    <?php
