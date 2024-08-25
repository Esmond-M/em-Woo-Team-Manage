<h2>Import Users from CSV</h2>
<form id="em-user-import-form" action="" method="post" enctype="multipart/form-data">
<label>CSV file  <input class="em-sv-btn"  name="emcsv"  type="file" accept=".csv" /></label>
  
    <input type="submit" value="Import">

</form>
<?php 

  require_once(WP_PLUGIN_DIR . '/em-user-import/includes/classes/emUserImport.php');

  use emUserImport\emUserImport;

  $obj = new emUserImport;

  $obj->user_import_submission();
             