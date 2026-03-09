<?php
/**
 * Team Leader / Admin Import & Add Page
 */

$is_admin = current_user_can('manage_options');

if ($is_admin) {
    $teamLeaderUsers = get_users(['role__in' => ['team_leader']]);
    if (empty($teamLeaderUsers)) {
        echo '<div class="import-container"><p>No team leader accounts exist yet.</p></div>';
        return;
    }
    $active_leader_id = $teamLeaderUsers[0]->ID;
} else {
    $active_leader_id = get_current_user_id();
}
?>
<div class="import-container">

<?php if ($is_admin): ?>
    <div class="emulation-form" style="margin-bottom:20px;padding:12px;background:#f8f8f8;border:1px solid #ddd;">
        <label for="admin-leader-select"><strong>Acting as Team Leader:</strong></label>
        <select id="admin-leader-select" style="margin-left:8px;">
            <?php foreach ($teamLeaderUsers as $user): ?>
                <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <hr style="margin-bottom:24px;">
<?php endif; ?>

        <h2>Import Subordinate Users from CSV</h2>
        <ol class="import-steps">
            <li>Download the <a href="<?php echo esc_url(add_query_arg(['action' => 'emwtm_sample_csv', '_nonce' => wp_create_nonce('emwtm_sample_csv')], admin_url('admin-ajax.php'))); ?>">sample CSV file</a>.</li>
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
            <input name="teamLeaderID" id="csv-leader-id" type="hidden" value="<?php echo esc_attr($active_leader_id); ?>">
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
                src="<?php echo esc_url(plugins_url('admin/assets/img/user-import-screenshot.png', EMWTM_PLUGIN_FILE)); ?>"
                style="max-width:100%;height:auto;"
            />
        </div>

        <hr style="margin:30px 0;">

        <h2>Add a Single Subordinate</h2>
        <form id="add-single-subordinate-form" method="POST" action="">
            <table class="form-table">
                <tr>
                    <th><label for="single_first_name">First Name</label></th>
                    <td><input type="text" id="single_first_name" name="single_first_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="single_last_name">Last Name</label></th>
                    <td><input type="text" id="single_last_name" name="single_last_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="single_email">Email Address</label></th>
                    <td><input type="email" id="single_email" name="single_email" class="regular-text" required /></td>
                </tr>
            </table>
            <input type="hidden" name="action" value="add_single_subordinate" />
            <input type="hidden" name="teamLeaderID" id="single-leader-id" value="<?php echo esc_attr($active_leader_id); ?>" />
            <?php wp_nonce_field('add_single_subordinate', '_single_subordinate_nonce'); ?>
            <input type="submit" value="Add Subordinate" class="button button-primary">
        </form>
        <div id="add-single-subordinate-result"></div>

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

        <?php if ($is_admin): ?>
        // Admin: sync all teamLeaderID fields when dropdown changes
        document.getElementById('admin-leader-select').addEventListener('change', function() {
            var id = this.value;
            document.getElementById('csv-leader-id').value = id;
            document.getElementById('single-leader-id').value = id;
        });
        <?php endif; ?>

        // Single subordinate add via AJAX
        (function($) {
            $('#add-single-subordinate-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn  = $form.find('input[type="submit"]').prop('disabled', true);
                var $result = $('#add-single-subordinate-result');
                $result.html('');
                $.post(add_single_subordinate.ajaxurl, $form.serialize(), function(data) {
                    $result.html(data);
                    if (data.indexOf('newpost-success') !== -1) { $form[0].reset(); }
                }).fail(function() {
                    $result.html('<p style="color:red;">Connection error.</p>');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        })(jQuery);
    </script>
</div>

