<?php
/**
 * Allows site admins to emulate team leaders for CSV import.
 * Allows team leaders to import subordinate users via CSV.
 * Shows instructional info and validates file size.
 */
?>
<?php
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
        <h2>Emulate User for CSV import</h2>
        <form id="emulate-team-leader-form" method="POST" action="">
            <label for="teamLeaderSelectOption">Select Team Leader:</label>
            <select name="teamLeaderSelectOption" id="teamLeaderSelectOption" form="emulate-team-leader-form">
                <?php foreach ( $teamLeaderUsers as $user ) : ?>
                    <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->user_login ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="action" value="emulate_Team_subordinate_Form_Submission" />
            <input type="submit" value="Emulate">
        </form>
    </div>
    <?php
} else {
    $siteURL = esc_url( get_site_url() );
    ?>
    <h2>Import Users from CSV</h2>
    <form id="subordinate-import-form" action="" method="post" enctype="multipart/form-data">
        <label for="csvUpload">
            CSV file limit 5MB
            <input id="csvUpload" type="file" name="csvUpload" accept=".csv" />
        </label>
        <input name="teamLeaderID" type="hidden" value="<?php echo esc_attr( get_current_user_id() ); ?>">
        <input type="submit" value="Import">
    </form>

    <div class="instructional-container">
        <p>
            Import up to 50 users at once. CSV requires first row fields be <strong>email_address, first_name, last_name</strong>.
            Those are the three pieces of info needed for each user.
        </p>
        <img
            alt="user import example"
            title="user import example"
            src="<?php echo $siteURL . '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/img/user-import-screenshot.png'; ?>"
            style="max-width:100%;height:auto;"
        />
    </div>
    <script>
        document.getElementById("csvUpload").onchange = function() {
            if (this.files[0].size > 5242880) {
                alert("File is too big!");
                this.value = "";
            }
        };
    </script>
    <?php
}

