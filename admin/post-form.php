<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

requireLogin();

$error = '';
$isEditing = false;
$editId = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));

$posts = array_values(array_filter(readJson(POSTS_FILE), 'is_array'));

$existingIndex = null;

if ($editId !== '') {
    foreach ($posts as $index => $post) {
        if ((string) ($post['id'] ?? '') === $editId) {
            $existingIndex = $index;
            break;
        }
    }

    if ($existingIndex === null) {
        http_response_code(404);
        exit('Blog post not found.');
    }

    $isEditing = true;
}

function makeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

/**
 * Quill's editor styles links, but the stored HTML may also be rendered by a
 * separate public site. Keep the link colour with the content itself so it
 * remains visible there too.
 */
function makeContentLinksBlue(string $html): string
{
    return preg_replace_callback(
        '/<a\b([^>]*)>/i',
        static function (array $match): string {
            $attributes = $match[1];

            if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $attributes, $styleMatch)) {
                $style = preg_replace(
                    '/(^|;)\s*color\s*:\s*[^;]*(;|$)/i',
                    '$1 color: #06c$2',
                    $styleMatch[2]
                ) ?? $styleMatch[2];

                if (!preg_match('/(^|;)\s*color\s*:/i', $style)) {
                    $style = rtrim($style, "; \t\r\n") . '; color: #06c;';
                }
                $attributes = str_replace(
                    $styleMatch[0],
                    'style=' . $styleMatch[1] . $style . $styleMatch[1],
                    $attributes
                );
            } else {
                $attributes .= ' style="color: #06c;"';
            }

            return '<a' . $attributes . '>';
        },
        $html
    ) ?? $html;
}

$currentPost = $isEditing ? $posts[$existingIndex] : [];

$title = (string) ($currentPost['title'] ?? '');
$slug = (string) ($currentPost['slug'] ?? '');
$category = (string) ($currentPost['category'] ?? '');
$excerpt = (string) ($currentPost['excerpt'] ?? '');
$content = (string) ($currentPost['content'] ?? '');
$status = (string) ($currentPost['status'] ?? 'draft');
$featuredImage = (string) ($currentPost['featured_image'] ?? '');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = makeSlug((string) ($_POST['slug'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = makeContentLinksBlue(trim((string) ($_POST['content'] ?? '')));
    $status = (string) ($_POST['status'] ?? 'draft');

    if (!verifyCsrfToken()) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif ($title === '' || $category === '' || $content === '') {
        $error = 'Title, category, and content are required.';
    } elseif (!in_array($status, ['draft', 'published'], true)) {
        $error = 'Invalid publication status.';
    } else {
        if ($slug === '') {
            $slug = makeSlug($title);
        }

        if ($slug === '') {
            $error = 'Please enter a valid title or URL slug.';
        } else {
            $slugExists = false;

            foreach ($posts as $post) {
                if (
                    strtolower((string) ($post['slug'] ?? '')) === strtolower($slug)
                    && (string) ($post['id'] ?? '') !== $editId
                ) {
                    $slugExists = true;
                    break;
                }
            }

            if ($slugExists) {
                $error = 'This URL slug already exists. Please choose another.';
            } else {
                $newImagePath = $featuredImage;

                if (
                    isset($_FILES['featured_image'])
                    && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    $file = $_FILES['featured_image'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Image upload failed. Please try again.';
                    } elseif ($file['size'] > 3 * 1024 * 1024) {
                        $error = 'Image must be 3 MB or smaller.';
                    } else {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->file($file['tmp_name']);

                        $allowedTypes = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                        ];

                        if (!isset($allowedTypes[$mimeType])) {
                            $error = 'Only JPG, PNG, and WEBP images are allowed.';
                        } elseif (@getimagesize($file['tmp_name']) === false) {
                            $error = 'Please upload a valid image file.';
                        } else {
                            if (!is_dir(UPLOAD_PATH)) {
                                if (!mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
                                    $error = 'Could not create the image folder.';
                                }
                            }

                            if ($error === '') {
                                $filename = bin2hex(random_bytes(16))
                                    . '.' . $allowedTypes[$mimeType];

                                if (move_uploaded_file(
                                    $file['tmp_name'],
                                    UPLOAD_PATH . '/' . $filename
                                )) {
                                    $newImagePath = 'images/blog/' . $filename;
                                } else {
                                    $error = 'Could not save the uploaded image.';
                                }
                            }
                        }
                    }
                }

                if ($error === '') {
                    $now = date('c');

                    if ($isEditing) {
                        $post = $posts[$existingIndex];

                        $post['title'] = $title;
                        $post['slug'] = $slug;
                        $post['category'] = $category;
                        $post['excerpt'] = $excerpt;
                        $post['content'] = $content;
                        $post['status'] = $status;
                        $post['featured_image'] = $newImagePath;
                        $post['updated_at'] = $now;

                        $posts[$existingIndex] = $post;
                    } else {
                        $post = [
                            'id' => bin2hex(random_bytes(16)),
                            'title' => $title,
                            'slug' => $slug,
                            'category' => $category,
                            'excerpt' => $excerpt,
                            'content' => $content,
                            'status' => $status,
                            'featured_image' => $newImagePath,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $posts[] = $post;
                    }

                    if (writeJson(POSTS_FILE, $posts)) {
                        header(
                            'Location: ' . BASE_URL . '/admin/posts.php?'
                            . ($isEditing ? 'updated=1' : 'created=1')
                        );
                        exit;
                    }

                    $error = 'Unable to save the blog. Check permissions for data/posts.json.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEditing ? 'Edit Blog' : 'Create Blog' ?> | Deepshitech</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill-better-table@1.2.10/dist/quill-better-table.css" rel="stylesheet">
    <style>
        .form-card {
            max-width: 950px;
            padding: 26px;
            border: 1px solid #e6e9f1;
            border-radius: 12px;
            background: #fff;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .field { margin-bottom: 20px; }
        .field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .field input, .field select, .field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe4ed;
            border-radius: 7px;
            background: #fff;
            color: #192538;
            font: inherit;
        }
        .field textarea { min-height: 100px; resize: vertical; }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: 2px solid #f2d9a4;
            border-color: #ce901c;
        }
        .field small { display: block; margin-top: 7px; color: #7b879b; }
        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .error-message {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a32626;
        }
        .secondary-action {
            display: inline-flex;
            align-items: center;
            padding: 0 18px;
            border: 1px solid #e6e9f1;
            border-radius: 8px;
        }
        @media (max-width: 650px) {
            .form-grid { grid-template-columns: 1fr; gap: 0; }
            .form-card { padding: 18px; }
        }

        #editor {
            min-height: 280px;
            background: #fff;
        }
        
        #editor .ql-editor {
            min-height: 280px;
            font-size: 15px;
            line-height: 1.7;
        }
        
        .ql-toolbar.ql-snow,
        .ql-container.ql-snow {
            border-color: #dfe4ed;
        }

        #editor .ql-editor a,
        .ql-snow .ql-editor a {
            color: #06c;
            text-decoration: underline;
        }

        .ql-toolbar button.ql-table::before {
            content: '▦';
            display: block;
            font-size: 18px;
            line-height: 1;
        }

        .ql-toolbar button.ql-table {
            width: 28px;
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
            <a href="<?= e(BASE_URL) ?>/admin/posts.php" class="nav-link">
                <span class="nav-icon">▤</span> Manage Blogs
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/post-form.php"
               class="nav-link <?= $isEditing ? '' : 'active' ?>">
                <span class="nav-icon">＋</span> Create New Blog
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="admin-avatar">
                <?= e(strtoupper(substr((string) ($_SESSION['admin_username'] ?? 'A'), 0, 1))) ?>
            </div>
            <div class="admin-details">
                <strong><?= e((string) ($_SESSION['admin_username'] ?? 'Admin')) ?></strong>
                <small><?= e(ucfirst((string) ($_SESSION['admin_role'] ?? 'editor'))) ?></small>
            </div>
            <span class="online-dot"></span>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle" type="button"
                    aria-label="Toggle sidebar" aria-expanded="false">☰</button>
            <div class="breadcrumb">
                Workspace / <strong><?= $isEditing ? 'Edit Blog' : 'Create New Blog' ?></strong>
            </div>
            <div class="topbar-right">
                <span class="status-indicator"></span>
                <span class="admin-label">Admin Panel</span>
                <div class="topbar-avatar">
                    <?= e(strtoupper(substr((string) ($_SESSION['admin_username'] ?? 'A'), 0, 1))) ?>
                </div>
                <a class="logout-link" href="<?= e(BASE_URL) ?>/admin/logout.php">Logout</a>
            </div>
        </header>

        <section class="page-content">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">CONTENT MANAGEMENT</p>
                    <h1><?= $isEditing ? 'Edit Blog' : 'Create New Blog' ?></h1>
                    <p class="muted">
                        <?= $isEditing ? 'Update your existing blog post.' : 'Write and publish content for your website.' ?>
                    </p>
                </div>
            </div>

            <section class="form-card">
                <?php if ($error !== ''): ?>
                    <div class="error-message"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= e($editId) ?>">
                    <?php endif; ?>

                    <div class="field">
                        <label for="title">Blog Title *</label>
                        <input id="title" name="title" required maxlength="180"
                               value="<?= e($title) ?>"
                               placeholder="Enter your blog title">
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="slug">URL Slug</label>
                            <input id="slug" name="slug" maxlength="200"
                                   value="<?= e($slug) ?>"
                                   placeholder="auto-generated-from-title">
                            <small>Used to create a clean URL for the blog.</small>
                        </div>

                        <div class="field">
                            <label for="category">Category *</label>
                            <input id="category" name="category" required maxlength="100"
                                   value="<?= e($category) ?>"
                                   placeholder="e.g. Digital Marketing">
                        </div>
                        

                    </div>

                    <div class="field">
                        <label for="excerpt">Short Excerpt</label>
                        <textarea id="excerpt" name="excerpt" maxlength="500"
                                  placeholder="Write a short summary"><?= e($excerpt) ?></textarea>
                    </div>
                    
                    <div class="field">
                        <label for="content">Blog Content *</label>
                        <div id="editor"><?= $content ?></div>
                        <textarea id="content" name="content" hidden><?= e($content) ?></textarea>
                        <small>Use the toolbar to format content, add links, or insert a table.</small>
                    </div>

                    
<div class="field">
    <label for="featured_image">Featured Image</label>

    <?php if ($featuredImage !== ''): ?>
        <div style="margin-bottom: 12px;">
            <img
                src="<?= e(BASE_URL . '/' . $featuredImage) ?>"
                alt="<?= e($featuredImageAlt !== '' ? $featuredImageAlt : $title) ?>"
                style="width:180px;max-height:140px;object-fit:cover;border-radius:8px;"
            >
        </div>
    <?php endif; ?>

    <input
        type="file"
        id="featured_image"
        name="featured_image"
        accept="image/jpeg,image/png,image/webp"
    >

    <small>JPG, PNG, or WebP. Maximum size: 3 MB.</small>

    <!-- //alt text input -->
    <div style="margin-top: 15px;">
    <label for="image_alt">Image Alt Text</label>

    <input
        type="text"
        id="image_alt"
        name="image_alt"
        value="<?= e($featuredImageAlt ?? '') ?>"
        maxlength="250"
        placeholder="Describe the featured image for SEO and accessibility"
    >

    <small>
        Describe what is visible in the image. Keep it short and meaningful.
    </small>
</div>
</div>

                    
                    <div class="field">
                        <label for="status">Publication Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Save as Draft</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publish Now</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="primary-button">
                            <?= $isEditing ? 'Save Changes' : 'Save Blog' ?>
                        </button>
                        <a href="<?= e(BASE_URL) ?>/admin/posts.php" class="secondary-action">Cancel</a>
                    </div>
                </form>
            </section>
        </section>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill-better-table@1.2.10/dist/quill-better-table.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editorElement = document.getElementById('editor');
    const contentField = document.getElementById('content');
    const form = contentField?.closest('form');

    if (!editorElement || !contentField || !form || typeof Quill === 'undefined') {
        return;
    }

    if (typeof QuillBetterTable !== 'undefined') {
        Quill.register({ 'modules/better-table': QuillBetterTable }, true);
    }

    const toolbarOptions = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link', 'table'],
        ['clean']
    ];

    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Write your blog content here...',
        modules: {
            toolbar: toolbarOptions,
            ...(typeof QuillBetterTable !== 'undefined' ? {
                'better-table': {
                    operationMenu: {
                        items: {
                            unmergeCells: { text: 'Unmerge cells' }
                        }
                    }
                },
                keyboard: {
                    bindings: QuillBetterTable.keyboardBindings
                }
            } : {})
        }
    });

    const toolbar = quill.getModule('toolbar');
    toolbar?.addHandler('table', function () {
        const tableModule = quill.getModule('better-table');

        if (!tableModule) {
            alert('Table support could not be loaded. Please refresh and try again.');
            return;
        }

        const rows = Number.parseInt(prompt('Number of rows:', '3') ?? '', 10);
        const columns = Number.parseInt(prompt('Number of columns:', '3') ?? '', 10);

        if (Number.isInteger(rows) && Number.isInteger(columns)
            && rows > 0 && rows <= 20 && columns > 0 && columns <= 10) {
            tableModule.insertTable(rows, columns);
        }
    });

    form.addEventListener('submit', function (event) {
        const plainText = quill.getText().trim();

        if (!plainText) {
            event.preventDefault();
            alert('Please enter your blog content.');
            return;
        }

        contentField.value = quill.root.innerHTML;
    });
});
</script>
<script src="<?= e(BASE_URL) ?>/assets/admin.js"></script>
</body>
</html>
