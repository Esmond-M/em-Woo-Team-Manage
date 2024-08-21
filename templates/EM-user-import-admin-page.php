<form id="em-user-import-form" action="" method="post" enctype="multipart/form-data">

    <input class="em-sv-btn"  name="emcsv"  type="file" accept=".csv" />
    <input type="submit" value="Submit">

</form>
<?php 

  require_once(WP_PLUGIN_DIR . '/em-user-import/includes/classes/emUserImport.php');

  use emUserImport\emUserImport;

  $obj = new emUserImport;

  $obj->user_import_submission();
             