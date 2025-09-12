<?php
/**
 * Site Admin Team Leader Summary Page
 * Displays total team leaders and lists all team leaders with their team details.
 */
?>
<div class="site-admin-team-summary">
    <h1 class="site-admin-title">Team Leaders &amp; Team Details Overview</h1>
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
                    <th>Subordinate Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamLeaderUsers as $teamLeader): ?>
                    <?php
                    global $wpdb;
                    $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
                    $subordinate_ids = $wpdb->get_col( $wpdb->prepare( "SELECT subordinate_id FROM $table WHERE leader_id = %d", $teamLeader->ID ) );
                    $teamSubordinates = [];
                    if ( !empty($subordinate_ids) ) {
                        $teamSubordinates = get_users([
                            'include' => $subordinate_ids,
                            'role__in' => ['team_subordinate']
                        ]);
                    }
                    $subCount = count($teamSubordinates);
                    ?>
                    <tr<?php if ($subCount === 0) echo ' class="no-subordinates"'; ?>>
                        <td><span><?php echo esc_html($teamLeader->user_email); ?></span></td>
                        <td><span><?php echo esc_html($teamLeader->display_name); ?></span></td>
                        <td><span class="sub-count<?php echo $subCount ? ' has-sub' : ' no-sub'; ?>"><?php echo esc_html($subCount); ?></span></td>
                        <td>
                            <button class="view-subs-btn" data-leader-id="<?php echo esc_attr($teamLeader->ID); ?>">
                                View Subordinates
                            </button>
                            <div class="sub-details-container" id="sub-details-<?php echo esc_attr($teamLeader->ID); ?>"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
