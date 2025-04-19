<?php
/**
 * Get HTML for user avatar
 * 
 * @param array $user User data array (must contain 'username' key and optionally 'avatar' key)
 * @param int $size Size of the avatar in pixels
 * @param string $class Additional CSS classes
 * @return string HTML for user avatar
 */
function getUserAvatar($user, $size = 40, $class = '') {
    $username = $user['username'] ?? 'User';
    
    if (!empty($user['avatar'])) {
        $avatarUrl = "uploads/avatars/" . htmlspecialchars($user['avatar']);
    } else {
        $avatarUrl = "assets/images/user-avatar.png"; // Default static image
    }
    
    return '<img src="' . $avatarUrl . '" alt="' . htmlspecialchars($username) . 
        '" class="user-avatar ' . $class . '" style="width: ' . $size . 'px; height: ' . $size . 
        'px; border-radius: 50%; object-fit: cover;">';
} 