<h2>Team leader admin view</h2>

<?php 
if ( current_user_can( 'manage_options' ) ) {
    wp_die('<div class="site-admin-msg">This page is not useful for admins</div>');
} 
$teamLeaderArgs = array(  
    'role__in' => array( 'team_subordinate' ),
    'meta_key'     => 'teamLeaderEmail',
    'meta_value'   => $user->user_email,    
);
$teamLeaderUsers = get_users( $teamLeaderArgs );
// Array of WP_User objects.
?>
<form id="team-leader-form" method="POST" action="">
    <table>
  <tr>
    <th>Number of Subordinates</th>
  </tr>
  <?php
    $number_of_users = count($teamLeaderUsers);
 ?>
  <tr>
    <td><?php echo '<span>' . esc_html( $number_of_users) . '</span>'; ?></td>
  </tr>  

    <?php
?>
</table> 
    <table>
  <tr>
    <th>Subordinate email</th>
    <th>Subordinate name</th>
    <th>Resend password</th>
    <th>Remove user</th>
  </tr>
  <?php
foreach ( $teamLeaderUsers as $user ) {

    $number_of_users = count($teamLeaderUsers);
 ?>

  <tr>
    <td><?php echo '<span>' . esc_html( $user->user_email ) . '</span>'; ?></td>
    <td><?php echo '<span>' . esc_html( $user->display_name ) . '</span>'; ?></td>
    <td><?php echo '<span>TBD</span>'; ?></td>
    <td><input type="checkbox" name="userID[]" value="<?php echo $user->ID;  ?>"/></td>
  </tr>

  
    
    <?php
}
?>
</table>
<?php wp_nonce_field( 'team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field' ); ?>
<input type="hidden" name="action" value="team_Leader_Form_Submission" />
<input type="submit" value="submit">
</form> 

<?php
