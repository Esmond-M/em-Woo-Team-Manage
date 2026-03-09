<?php
/**
 * Team Leader User Import Page
 * Improved markup for user-friendliness and clarity.
 */
?>
    <div class="import-container">
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
        <h2>Emulate Team Leader for CSV Import</h2>
        <ol class="import-steps">
            <li>Select a team leader to emulate.</li>
            <li>Click <strong>Emulate</strong> to view the CSV import form as that leader.</li>
        </ol>
        <form id="emulate-team-leader-form" method="POST" action="">
            <fieldset>
                <legend>Select Team Leader</legend>
                <label for="teamLeaderSelectOption">Team Leader:</label>
                <select name="teamLeaderSelectOption" id="teamLeaderSelectOption" form="emulate-team-leader-form">
                    <?php foreach ( $teamLeaderUsers as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->user_login ); ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset>
            <input type="hidden" name="_emulate_nonce" value="<?php echo esc_attr(wp_create_nonce('emulate_team_subordinate')); ?>" />
            <input type="hidden" name="action" value="emulate_Team_subordinate_Form_Submission" />
            <input type="submit" value="Emulate" class="button button-primary">
        </form>
    </div>
    <?php
} else {
    $siteURL = esc_url( get_site_url() );
    ?>

        <h2>Import Subordinate Users from CSV</h2>
        <ol class="import-steps">
            <li>Download the <a href="<?php echo $siteURL . '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/sample-user-import.csv'; ?>" target="_blank">sample CSV file</a>.</li>
            <li>Fill in user data (max 50 users per import).</li>
            <li>Drag and drop or select your CSV file below.</li>
            <li>Click <strong>Import</strong> to upload and create users.</li>
        </ol>
        <form id="subordinate-import-form" action="" method="post" enctype="multipart/form-data">
            <fieldset>
                <legend>CSV Upload <span title="Max 5MB. First row: email_address, first_name, last_name." style="cursor:help;">&#9432;</span></legend>
                <div id="csv-drop-area" style="border:2px dashed #ccc;padding:20px;text-align:center;margin-bottom:10px;">
                    <p>Drag &amp; drop your CSV file here, or click to select.</p>
                    <input id="csvUpload" type="file" name="csvUpload" accept=".csv" style="display:inline-block;" />
                </div>
            </fieldset>
            <input name="teamLeaderID" type="hidden" value="<?php echo esc_attr( get_current_user_id() ); ?>">
            <?php wp_nonce_field('user_import_submission', '_import_nonce'); ?>
            <input type="submit" value="Import" class="button button-primary">
        </form>

        <div class="instructional-container">
            <p>
                <strong>CSV Requirements:</strong><br>
                - Max 50 users per import.<br>
                - First row must be: <strong>email_address, first_name, last_name</strong>.<br>
                - File size limit: 5MB.<br>
            </p>
            <img
                alt="user import example"
                title="user import example"
                src="<?php echo $siteURL . '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/img/user-import-screenshot.png'; ?>"
                style="max-width:100%;height:auto;"
            />
        </div>

    <script>
        // Drag & drop CSV upload
        var dropArea = document.getElementById('csv-drop-area');
        var fileInput = document.getElementById('csvUpload');
        dropArea.addEventListener('click', function() { fileInput.click(); });
        dropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropArea.style.background = '#f0f8ff';
        });
        dropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropArea.style.background = '';
        });
        dropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            dropArea.style.background = '';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
            }
        });
        fileInput.onchange = function() {
            if (this.files[0] && this.files[0].size > 5242880) {
                alert("File is too big! Max 5MB.");
                this.value = "";
            }
        };
    </script>
        </div>
    <?php
}

