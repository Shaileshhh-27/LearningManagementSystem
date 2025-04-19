<?php if (!defined('INCLUDED')) { exit; } ?>
<div class="section">
    <h2>Pending Approvals</h2>
    <?php if (empty($unverifiedUsers)): ?>
        <p>No pending verifications.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registration Date</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($unverifiedUsers as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($user['created_at'] ?? 'now')); ?></td>
                    <td class="actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="verify_user" class="btn btn-small">Approve</button>
                        </form>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Are you sure you want to reject this user?');">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="reject_user" 
                                    class="btn btn-small" style="background: #f44336;">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div> 