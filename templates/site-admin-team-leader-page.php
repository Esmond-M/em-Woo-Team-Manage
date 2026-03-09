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
    <?php
    global $wpdb;
    $rel_table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    $leader_ids = array_column($teamLeaderUsers, 'ID');
    $sub_counts = [];
    if (!empty($leader_ids)) {
        $placeholders = implode(',', array_fill(0, count($leader_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT leader_id, COUNT(*) AS sub_count FROM {$rel_table} WHERE leader_id IN ({$placeholders}) GROUP BY leader_id",
                ...$leader_ids
            )
        );
        foreach ($rows as $row) {
            $sub_counts[(int) $row->leader_id] = (int) $row->sub_count;
        }
    }
    ?>
    <div class="responsive-table-container">
        <table class="site-admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Subordinates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamLeaderUsers as $teamLeader): ?>
                    <?php $subCount = $sub_counts[$teamLeader->ID] ?? 0; ?>
                    <tr<?php if ($subCount === 0) echo ' class="no-subordinates"'; ?>>
                        <td><?php echo esc_html($teamLeader->user_email); ?></td>
                        <td><?php echo esc_html($teamLeader->display_name); ?></td>
                        <td><span class="sub-count<?php echo $subCount ? ' has-sub' : ' no-sub'; ?>"><?php echo esc_html($subCount); ?></span></td>
                        <td class="admin-action-links">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=team-leader-admin&leader_id=' . $teamLeader->ID)); ?>" class="admin-action-link manage">Manage Team</a>
                            <a href="<?php echo esc_url(get_edit_user_link($teamLeader->ID)); ?>" class="admin-action-link edit">Edit User</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
