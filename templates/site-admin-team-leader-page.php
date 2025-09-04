
<?php
/**
 * Site Admin Team Leader Summary Page
 * Improved markup and styling for clarity and modern look.
 */
?>
<div class="site-admin-team-summary">
    <h1 class="site-admin-title">Team Leaders &amp; Subordinates Overview</h1>
    <?php
    $teamLeaderArgs = array(
        'role__in' => array('team_leader'),
    );
    $teamLeaderUsers = get_users($teamLeaderArgs);
    $teamLeaderCount = count($teamLeaderUsers);
    ?>
    <div class="summary-card">
        <span class="summary-label">Total Team Leaders:</span>
        <span class="summary-value"><?php echo esc_html($teamLeaderCount); ?></span>
    </div>
    <div class="responsive-table-container">
        <table class="site-admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Subordinates</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamLeaderUsers as $teamLeader): ?>
                    <?php
                    $teamSubordinateArgs = array(
                        'role__in'   => array('team_subordinate'),
                        'meta_key'   => 'teamID',
                        'meta_value' => $teamLeader->ID,
                    );
                    $teamSubordinates = get_users($teamSubordinateArgs);
                    $subCount = count($teamSubordinates);
                    ?>
                    <tr<?php if ($subCount === 0) echo ' class="no-subordinates"'; ?>>
                        <td><span><?php echo esc_html($teamLeader->user_email); ?></span></td>
                        <td><span><?php echo esc_html($teamLeader->display_name); ?></span></td>
                        <td><span class="sub-count<?php echo $subCount ? ' has-sub' : ' no-sub'; ?>"><?php echo esc_html($subCount); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

