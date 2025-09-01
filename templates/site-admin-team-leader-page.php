<?php
/**
 * Displays a summary table for site admins showing all team leaders and the number of subordinates each has.
 */
?>
<h2>Site Admin view</h2>
<?php
$teamLeaderArgs = array(
    'role__in' => array('team_leader'),
);
$teamLeaderUsers = get_users($teamLeaderArgs);
$teamLeaderCount = count($teamLeaderUsers);
?>
<table>
    <tr>
        <th colspan="3">Number of Team Leaders: <?php echo esc_html($teamLeaderCount); ?></th>
    </tr>
</table>
<table>
    <tr>
        <th>Team Leader Email</th>
        <th>Team Leader Name</th>
        <th>Number of Subordinates</th>
    </tr>
    <?php foreach ($teamLeaderUsers as $teamLeader): ?>
        <tr>
            <td><span><?php echo esc_html($teamLeader->user_email); ?></span></td>
            <td><span><?php echo esc_html($teamLeader->display_name); ?></span></td>
            <td>
                <?php
                $teamSubordinateArgs = array(
                    'role__in'   => array('team_subordinate'),
                    'meta_key'   => 'teamID',
                    'meta_value' => $teamLeader->ID,
                );
                $teamSubordinates = get_users($teamSubordinateArgs);
                echo esc_html(count($teamSubordinates));
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

