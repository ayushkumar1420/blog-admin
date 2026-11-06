<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

requireLogin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $posts = readJson(POSTS_FILE);

        $updatedPosts = [];
        $found = false;

        foreach ($posts as $post) {
            if (!is_array($post)) {
                continue;
            }

            if ((string) ($post['id'] ?? '') === $id) {
                $found = true;
                continue;
            }

            $updatedPosts[] = $post;
        }

        if (!$found) {
            $error = 'Blog post not found.';
        } elseif (writeJson(POSTS_FILE, $updatedPosts)) {
            header('Location: ' . BASE_URL . '/admin/posts.php?deleted=1');
            exit;
        } else {
            $error = 'Unable to delete the post. Check file permissions.';
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'Blog created successfully.';
} elseif (isset($_GET['deleted'])) {
    $message = 'Blog deleted successfully.';
}

$posts = array_values(array_filter(
    readJson(POSTS_FILE),
    'is_array'
));

usort($posts, static function (array $a, array $b): int {
    return strcmp(
        (string) ($b['updated_at'] ?? $b['created_at'] ?? ''),
        (string) ($a['updated_at'] ?? $a['created_at'] ?? '')
    );
});

$username = (string) ($_SESSION['admin_username'] ?? 'Admin');
$role = (string) ($_SESSION['admin_role'] ?? 'editor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blogs | Deepshitech</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/admin.css">
    <style>
        .notice-success {
            padding: 13px 16px;
            margin-bottom: 20px;
            border: 1px solid #b8e9d0;
            border-radius: 8px;
            background: #e9f9f0;
            color: #167349;
        }
        .notice-error {
            padding: 13px 16px;
            margin-bottom: 20px;
            border: 1px solid #f0c6c6;
            border-radius: 8px;
            background: #fff0f0;
            color: #a32626;
        }
        .posts-toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .posts-toolbar input,
        .posts-toolbar select {
            min-height: 43px;
            padding: 0 12px;
            border: 1px solid #e2e6ef;
            border-radius: 7px;
            background: #fff;
        }
        .posts-toolbar input { flex: 1; min-width: 200px; }
        .delete-form { display: inline; }
        .delete-button {
            padding: 7px 10px;
            border: 1px solid #f0caca;
            border-radius: 6px;
            background: #fff;
            color: #b42318;
            cursor: pointer;
        }
        .post-actions { display: flex; align-items: center; gap: 8px; }
        .empty-state { padding: 30px; text-align: center; color: #7b879b; }
        @media (max-width: 600px) {
            .posts-toolbar { flex-direction: column; }
            .posts-toolbar input, .posts-toolbar select { width: 100%; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
        <a href="<?= e(BASE_URL) ?>/admin/index.php" class="brand">
    <img
        src="<?= e(BASE_URL) ?>/img/logo2.png"
        alt="DeepshiTech Logo"
        class="brand-logo"
    >
</a>

        <p class="menu-label">WORKSPACE</p>
        <nav class="sidebar-nav">
            <a href="<?= e(BASE_URL) ?>/admin/index.php" class="nav-link">
                <span class="nav-icon">▦</span> Dashboard
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/posts.php" class="nav-link active">
                <span class="nav-icon">▤</span> Manage Blogs
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/post-form.php" class="nav-link">
                <span class="nav-icon">＋</span> Create New Blog
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="admin-avatar"><?= e(strtoupper(substr($username, 0, 1))) ?></div>
            <div class="admin-details">
                <strong><?= e($username) ?></strong>
                <small><?= e(ucfirst($role)) ?></small>
            </div>
            <span class="online-dot"></span>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle" type="button"
                    aria-label="Toggle sidebar" aria-expanded="false">☰</button>
            <div class="breadcrumb">Workspace / <strong>Manage Blogs</strong></div>
            <div class="topbar-right">
                <span class="status-indicator"></span>
                <span class="admin-label">Admin Panel</span>
                <div class="topbar-avatar"><?= e(strtoupper(substr($username, 0, 1))) ?></div>
                <a class="logout-link" href="<?= e(BASE_URL) ?>/admin/logout.php">Logout</a>
            </div>
        </header>

        <section class="page-content">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">CONTENT MANAGEMENT</p>
                    <h1>Manage Blogs</h1>
                    <p class="muted">Create, edit, search, and manage your blog posts.</p>
                </div>
                <a class="primary-button" href="<?= e(BASE_URL) ?>/admin/post-form.php">
                    ＋ Create New Blog
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="notice-success"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="notice-error"><?= e($error) ?></div>
            <?php endif; ?>

            <section class="content-card">
                <div class="card-heading">
                    <div>
                        <h2>All Blog Posts</h2>
                        <p class="muted"><?= count($posts) ?> posts in total</p>
                    </div>
                </div>

                <div class="posts-toolbar" style="padding: 0 24px 20px;">
                    <input type="search" id="blogSearch"
                           placeholder="Search blog titles..."
                           aria-label="Search blog titles">
                    <select id="statusFilter" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>BLOG TITLE</th>
                                <th>CATEGORY</th>
                                <th>STATUS</th>
                                <th>LAST UPDATED</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="blogsTableBody">
                        <?php foreach ($posts as $post): ?>
                            <?php
                            $id = (string) ($post['id'] ?? '');
                            $title = (string) ($post['title'] ?? 'Untitled');
                            $category = (string) ($post['category'] ?? 'Uncategorized');
                            $status = strtolower((string) ($post['status'] ?? 'draft'));
                            $date = (string) ($post['updated_at'] ?? $post['created_at'] ?? '');
                            $timestamp = $date !== '' ? strtotime($date) : false;
                            $displayDate = $timestamp !== false ? date('d M Y', $timestamp) : '—';
                            $published = $status === 'published';
                            ?>
                            <tr data-title="<?= e(strtolower($title)) ?>"
                                data-status="<?= $published ? 'published' : 'draft' ?>">
                                <td class="blog-title"><?= e($title) ?></td>
                                <td><?= e($category) ?></td>
                                <td>
                                    <span class="status-badge <?= $published ? 'published' : 'draft' ?>">
                                        <?= $published ? 'Published' : 'Draft' ?>
                                    </span>
                                </td>
                                <td><?= e($displayDate) ?></td>
                                <td>
                                    <div class="post-actions">
                                        <a class="edit-button"
                                           href="<?= e(BASE_URL) ?>/admin/post-form.php?id=<?= rawurlencode($id) ?>">
                                            Edit
                                        </a>

                                        <form class="delete-form" method="post"
                                              onsubmit="return confirm('Are you sure you want to delete this blog? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($id) ?>">
                                            <button class="delete-button" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="empty-state" id="emptyState" <?= $posts === [] ? '' : 'hidden' ?>>
                    <?= $posts === [] ? 'No blog posts yet. Create your first blog.' : 'No blogs match your search.' ?>
                </div>
            </section>
        </section>
    </main>
</div>

<script src="<?= e(BASE_URL) ?>/assets/admin.js"></script>
</body>
</html>
