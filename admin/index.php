<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

requireLogin();

$posts = readJson(POSTS_FILE);

$posts = array_values(array_filter($posts, 'is_array'));

usort($posts, static function (array $a, array $b): int {
    return strcmp(
        (string) ($b['updated_at'] ?? $b['created_at'] ?? ''),
        (string) ($a['updated_at'] ?? $a['created_at'] ?? '')
    );
});

$totalPosts = count($posts);
$publishedPosts = 0;
$draftPosts = 0;
$categories = [];

foreach ($posts as $post) {
    $status = strtolower((string) ($post['status'] ?? 'draft'));

    if ($status === 'published') {
        $publishedPosts++;
    } else {
        $draftPosts++;
    }

    $category = trim((string) ($post['category'] ?? ''));

    if ($category !== '') {
        $categories[strtolower($category)] = true;
    }
}

$totalCategories = count($categories);
$recentPosts = array_slice($posts, 0, 10);

$username = (string) ($_SESSION['admin_username'] ?? 'Administrator');
$role = (string) ($_SESSION['admin_role'] ?? 'editor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | DeepshiTech Blog Admin</title>

    <link rel="stylesheet"
          href="<?= e(BASE_URL) ?>/assets/admin.css">
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
            <a href="<?= e(BASE_URL) ?>/admin/index.php"
               class="nav-link active">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="<?= e(BASE_URL) ?>/admin/posts.php"
               class="nav-link">
                <span class="nav-icon">▤</span>
                Manage Blogs
            </a>

            <a href="<?= e(BASE_URL) ?>/admin/post-form.php"
               class="nav-link">
                <span class="nav-icon">＋</span>
                Create New Blog
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="admin-avatar">
                <?= e(strtoupper(substr($username, 0, 1))) ?>
            </div>

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
            <button class="menu-toggle" id="menuToggle"
                    type="button"
                    aria-label="Toggle sidebar"
                    aria-expanded="false">☰</button>

            <div class="breadcrumb">
                Workspace / <strong>Dashboard</strong>
            </div>

            <div class="topbar-right">
                <span class="status-indicator"></span>
                <span class="admin-label">Admin Panel</span>

                <div class="topbar-avatar">
                    <?= e(strtoupper(substr($username, 0, 1))) ?>
                </div>

                <a class="logout-link"
                   href="<?= e(BASE_URL) ?>/admin/logout.php">
                    Logout
                </a>
            </div>
        </header>

        <section class="page-content">

            <div class="page-heading">
                <div>
                    <p class="eyebrow">CONTENT MANAGEMENT</p>
                    <h1>Dashboard</h1>
                    <p class="muted">
                        Manage your DeepshiTech blog content in one place.
                    </p>
                </div>

                <a class="primary-button"
                   href="<?= e(BASE_URL) ?>/admin/post-form.php">
                    <span>＋</span> Create New Blog
                </a>
            </div>

            <div class="notice">
                <span class="notice-icon">i</span>
                <p>
                    Your dashboard is connected to local JSON storage.
                    Statistics and recent blogs update using your saved posts.
                </p>
            </div>

            <div class="stats-grid">

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Total Blogs</span>
                        <span class="stat-icon gold">▤</span>
                    </div>
                    <h2><?= $totalPosts ?></h2>
                    <p class="stat-caption">All blog posts</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Published</span>
                        <span class="stat-icon green">✓</span>
                    </div>
                    <h2><?= $publishedPosts ?></h2>
                    <p class="stat-caption">Visible to readers</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Drafts</span>
                        <span class="stat-icon blue">✎</span>
                    </div>
                    <h2><?= $draftPosts ?></h2>
                    <p class="stat-caption">Not yet published</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Categories</span>
                        <span class="stat-icon purple">▦</span>
                    </div>
                    <h2><?= $totalCategories ?></h2>
                    <p class="stat-caption">Content categories</p>
                </article>

            </div>

            <section class="content-card">

                <div class="card-heading">
                    <div>
                        <h2>Recent Blogs</h2>
                        <p class="muted">
                            A quick overview of your latest content.
                        </p>
                    </div>

                    <a class="secondary-button"
                       href="<?= e(BASE_URL) ?>/admin/posts.php">
                        View All Blogs →
                    </a>
                </div>

                <div class="table-toolbar">
                    <input type="search"
                           id="blogSearch"
                           placeholder="Search blog titles..."
                           aria-label="Search blog titles">

                    <select id="statusFilter"
                            aria-label="Filter by status">
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
                                <th>ACTION</th>
                            </tr>
                        </thead>

                        <tbody id="blogsTableBody">
                        <?php foreach ($recentPosts as $post): ?>
                            <?php
                            $id = (string) ($post['id'] ?? '');
                            $title = (string) ($post['title'] ?? 'Untitled');
                            $category = (string) ($post['category'] ?? 'Uncategorized');
                            $status = strtolower((string) ($post['status'] ?? 'draft'));
                            $updatedAt = (string) (
                                $post['updated_at']
                                ?? $post['created_at']
                                ?? ''
                            );

                            $displayDate = '—';

                            if ($updatedAt !== '') {
                                $timestamp = strtotime($updatedAt);

                                if ($timestamp !== false) {
                                    $displayDate = date('d M Y', $timestamp);
                                }
                            }

                            $isPublished = $status === 'published';
                            ?>

                            <tr data-title="<?= e(strtolower($title)) ?>"
                                data-status="<?= e($isPublished ? 'published' : 'draft') ?>">

                                <td class="blog-title">
                                    <?= e($title) ?>
                                </td>

                                <td><?= e($category) ?></td>

                                <td>
                                    <span class="status-badge <?= $isPublished ? 'published' : 'draft' ?>">
                                        <?= e(ucfirst($isPublished ? 'Published' : 'Draft')) ?>
                                    </span>
                                </td>

                                <td><?= e($displayDate) ?></td>

                                <td>
                                    <a class="edit-button"
                                       href="<?= e(BASE_URL) ?>/admin/post-form.php?id=<?= rawurlencode($id) ?>">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="empty-state"
                     id="emptyState"
                     <?= $recentPosts === [] ? '' : 'hidden' ?>>
                    <?= $recentPosts === []
                        ? 'No blog posts yet. Create your first blog to get started.'
                        : 'No blogs match your search.' ?>
                </div>

            </section>

            <footer class="dashboard-footer">
                <span>DeepshiTech Blog Admin</span>
                <span>Content Management System</span>
            </footer>

        </section>
    </main>
</div>

<script src="<?= e(BASE_URL) ?>/assets/admin.js ?>"></script>
</body>
</html>
