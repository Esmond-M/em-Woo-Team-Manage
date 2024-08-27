<h2>Import Users from CSV</h2>
<?php
if ( current_user_can( 'manage_options' ) ) {
  $teamLeaderArgs = array(  
    'role__in' => array( 'team_leader' ),  
);
$teamLeaderUsers = get_users( $teamLeaderArgs );
?>
<form id="emulate-team-leader-form" method="POST" action="">
<select name="teamLeaderSelectOption" form="emulate-team-leader-form">

  <?php
foreach ( $teamLeaderUsers as $user ) {
  ?>
  <option value="<?php echo $user->ID; ?>"><?php echo $user->user_login; ?></option>
<?php  
}
?>
    </select>
    <input type="hidden" name="action" value="emulate_Team_subordinate_Form_Submission" />
    <input type="submit" value="submit">    
</form>

<?php

} 

if ( !current_user_can( 'manage_options' ) ) {
  ?>
  <form id="em-user-import-form" action="" method="post" enctype="multipart/form-data">
    <label>CSV file  <input id="csvUpload" type="file" name="csvUpload"  type="file" accept=".csv" /></label>  
      <input name="teamLeaderID"  type="hidden" value="<?php echo get_current_user_id();?>">
      <input type="submit" value="Import">
  
  </form>
  <?php 
  
}

             