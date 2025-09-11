<?php
/**
 * Team Leader Admin Page
 * Improved markup for user-friendliness and clarity.
 */
?>

<?php

if ( current_user_can( 'manage_options' )  && !current_user_can( 'team_leader' ) ) {
    ?>
    <div class="notice notice-info">
        <p>This page is for Team Leaders only. Site admins do not have subordinate management on this screen.</p>
    </div>
    <?php
    return;
}

// If user has Team Leader role
if ( current_user_can( 'team_leader' ) ) {
    global $wpdb;
    $teamLeaderID = get_current_user_id();
    $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    $subordinate_ids = $wpdb->get_col( $wpdb->prepare( "SELECT subordinate_id FROM $table WHERE leader_id = %d", $teamLeaderID ) );
    $teamSubordinates = [];
    if ( !empty($subordinate_ids) ) {
        $teamSubordinates = get_users([
            'include' => $subordinate_ids,
            'role__in' => ['team_subordinate']
        ]);
    }
    $number_of_users = count( $teamSubordinates );
    ?>
    <div class="subordinate-container">
        <h2>Manage Your Subordinates</h2>
        <ol class="leader-steps">
            <li>Review your list of subordinates below.</li>
            <li>Select one or more users to delete or send a password reset.</li>
            <li>Choose an action and click <strong>Submit</strong>.</li>
        </ol>
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
}
