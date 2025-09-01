<?php
/**
 * Allows site admins to emulate team leaders and view their subordinates.
 * Allows team leaders to view and manage their own subordinates (delete or resend password).
 */
?>

<?php
// If user is a Website admin, allow emulation
if ( current_user_can( 'manage_options' ) ) {
    $teamLeaderArgs = array(
        'role__in' => array( 'team_leader' ),
    );
    $teamLeaderUsers = get_users( $teamLeaderArgs );
    if ( count( $teamLeaderUsers ) === 0 ) {
        wp_die( '<p>No Team leader user to emulate</p>' );
    }
    ?>
    <div class="emulation-form">
        <h2>Emulate User to View Subordinates</h2>
        <form id="emulate-team-leader-form" method="POST" action="">
            <label for="teamLeaderSelectOption">Select Team Leader:</label>
            <select name="teamLeaderSelectOption" id="teamLeaderSelectOption" form="emulate-team-leader-form">
                <?php foreach ( $teamLeaderUsers as $user ) : ?>
                    <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->user_login ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="action" value="emulate_Team_Leader_Form_Submission" />
            <input type="submit" value="Emulate">
        </form>
    </div>
    <?php
}

// If user has Team Leader role
if ( ! current_user_can( 'manage_options' ) && current_user_can( 'team_leader' ) ) {
    $teamLeaderID = get_current_user_id();
    $teamSubordinateArgs = array(
        'role__in'   => array( 'team_subordinate' ),
        'meta_key'   => 'teamID',
        'meta_value' => $teamLeaderID,
    );
    $teamSubordinates = get_users( $teamSubordinateArgs );
    $number_of_users = count( $teamSubordinates );
    ?>
    <h2>View Subordinates</h2>
    <form id="team-leader-form" method="POST" action="">
        <table>
            <tr>
                <th>Number of Subordinates</th>
                <th>Action</th>
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
                <th>Subordinate email</th>
                <th>Subordinate name</th>
                <th>Select Subordinate</th>
            </tr>
            <?php foreach ( $teamSubordinates as $user ) : ?>
                <tr>
                    <td><span><?php echo esc_html( $user->user_email ); ?></span></td>
                    <td><span><?php echo esc_html( $user->display_name ); ?></span></td>
                    <td><input type="checkbox" name="userID[]" value="<?php echo esc_attr( $user->ID ); ?>" /></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php wp_nonce_field( 'team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field' ); ?>
        <input type="hidden" name="action" value="team_Leader_Form_Submission" />
        <input type="submit" value="Submit">
    </form>
    <?php
}
?>

