<h2>Site admin view</h2>

<?php 


$teamLeaderUsers = get_users( array( 'role__in' => array( 'team_leader' ) ) );
// Array of WP_User objects.
?>
    <table>
  <tr>
    <th>Team Leader email</th>
    <th>Team leader name</th>
    <th>Subordinates</th>
  </tr>
  <?php
foreach ( $teamLeaderUsers as $user ) {

    $subordinateArgs = array(  
            'meta_key'     => 'teamLeaderEmail',
            'meta_value'   => $user->user_email,
    );
    $subordinateUsers = get_users($subordinateArgs);
    $number_of_users = count($subordinateUsers);
 ?>

  <tr>
    <td><?php echo '<span>' . esc_html( $user->user_email ) . '</span>'; ?></td>
    <td><?php echo '<span>' . esc_html( $user->display_name ) . '</span>'; ?></td>
    <td><?php echo '<span>' . esc_html( $number_of_users ) . '</span>'; ?></td>
  </tr>

  
    
    <?php
}
?>
</table> 