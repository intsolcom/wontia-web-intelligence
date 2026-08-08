<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class BlogController
{
    public function index(Request $req): void
    {
        $db = Database::instance();
        $page = max(1, (int)($req->get('page', 1)));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = $req->get('search');
        $status = $req->get('status');
        $params = [];
        $where = "WHERE p.site_id = 1";
        if ($search) { $where .= " AND (p.title LIKE :search OR p.excerpt LIKE :search2)"; $params['search'] = "%$search%"; $params['search2'] = "%$search%"; }
        if ($status && in_array($status, ['draft', 'published'])) { $where .= " AND p.status = :status"; $params['status'] = $status; }
        $count = $db->prepare("SELECT COUNT(*) FROM blog_posts p $where")->execute($params)->fetchColumn();
        $posts = $db->prepare("SELECT p.*, c.name as category_name FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset")->execute($params)->fetchAll();
        Response::json(['ok' => true, 'data' => $posts, 'total' => (int)$count, 'page' => $page]);
    }

    public function show(Request $req, string $id): void
    {
        $db = Database::instance();
        $post = $db->prepare("SELECT p.*, c.name as category_name FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id WHERE p.id = :id AND p.site_id = 1");
        $post->execute(['id' => $id]);
        $p = $post->fetch();
        if (!$p) Response::error('Post not found', 404);
        $tags = $db->prepare("SELECT t.id, t.name, t.slug FROM blog_tags t JOIN blog_post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = :pid");
        $tags->execute(['pid' => $id]);
        $p['tags'] = $tags->fetchAll();
        $p['tag_ids'] = array_column($p['tags'], 'id');
        Response::json(['ok' => true, 'data' => $p]);
    }

    public function store(Request $req): void
    {
        $title = $req->input('title');
        if (!$title) Response::error('Title is required', 400);

        $content = $req->input('content', '');
        $wordCount = str_word_count(strip_tags($content));
        $readTime = max(1, ceil($wordCount / 200));

        $db = Database::instance();
        $slug = $req->input('slug') ?: \App\Controllers\Admin\PageController::slugify($title);
        $status = $req->input('status', 'draft');
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

        $db->prepare("INSERT INTO blog_posts (site_id, title, slug, excerpt, content, cover_image, cover_alt, category_id, author_name, author_role, read_time, status, featured, meta_title, meta_description, meta_keywords, lang, published_at) VALUES (1, :title, :slug, :excerpt, :content, :cover, :cover_alt, :cat, :author, :role, :read, :status, :featured, :meta_title, :meta_desc, :meta_key, :lang, :pub)")
            ->execute([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $req->input('excerpt', ''),
                'content' => $content,
                'cover' => $req->input('cover_image', ''),
                'cover_alt' => $req->input('cover_alt', ''),
                'cat' => $req->input('category_id') ?: null,
                'author' => $req->input('author_name', 'Wontia'),
                'role' => $req->input('author_role', ''),
                'read' => $readTime,
                'status' => $status,
                'featured' => (int)$req->input('featured', 0),
                'meta_title' => $req->input('meta_title', $title),
                'meta_desc' => $req->input('meta_description', $req->input('excerpt', '')),
                'meta_key' => $req->input('meta_keywords', ''),
                'lang' => $req->input('lang', 'es'),
                'pub' => $publishedAt,
            ]);
        $id = $db->lastInsertId();
        $this->syncTags($id, $req->input('tag_ids', []));
        Response::json(['ok' => true, 'data' => ['id' => $id]], 201);
    }

    public function update(Request $req, string $id): void
    {
        $db = Database::instance();
        $existing = $db->prepare("SELECT id FROM blog_posts WHERE id = :id AND site_id = 1");
        $existing->execute(['id' => $id]);
        if (!$existing->fetch()) Response::error('Post not found', 404);

        $content = $req->input('content');
        $readTime = $content ? max(1, ceil(str_word_count(strip_tags($content)) / 200)) : null;

        $fields = ['title', 'slug', 'excerpt', 'content', 'cover_image', 'cover_alt', 'category_id', 'author_name', 'author_role', 'status', 'featured', 'meta_title', 'meta_description', 'meta_keywords', 'lang'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $f) {
            $val = $req->input($f);
            if ($val !== null) {
                $sets[] = "$f = :$f";
                $params[$f] = $f === 'featured' || $f === 'category_id' ? (int)$val : $val;
            }
        }
        if ($readTime !== null) { $sets[] = "read_time = :rt"; $params['rt'] = $readTime; }
        if (!empty($sets)) {
            $db->prepare("UPDATE blog_posts SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
        }
        $this->syncTags($id, $req->input('tag_ids', []));
        Response::json(['ok' => true]);
    }

    public function destroy(Request $req, string $id): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM blog_posts WHERE id = :id AND site_id = 1")->execute(['id' => $id]);
        Response::json(['ok' => true]);
    }

    public function toggleStatus(Request $req, string $id): void
    {
        $db = Database::instance();
        $post = $db->prepare("SELECT id, status FROM blog_posts WHERE id = :id AND site_id = 1");
        $post->execute(['id' => $id]);
        $p = $post->fetch();
        if (!$p) Response::error('Post not found', 404);
        $newStatus = $p['status'] === 'published' ? 'draft' : 'published';
        $publishedAt = $newStatus === 'published' ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE blog_posts SET status = :s, published_at = :pa WHERE id = :id")->execute(['s' => $newStatus, 'pa' => $publishedAt, 'id' => $id]);
        Response::json(['ok' => true, 'status' => $newStatus]);
    }

    public function polish(Request $req): void
    {
        $content = $req->input('content', '');
        if (!$content) Response::error('Content required', 400);
        $service = new \App\Services\AiContentService();
        $result = $service->polishContent($content);
        Response::json(['ok' => true, 'data' => ['content' => $result]]);
    }

    private function syncTags(int $postId, array $tagIds): void
    {
        $db = Database::instance();
        $db->prepare("DELETE FROM blog_post_tags WHERE post_id = :pid")->execute(['pid' => $postId]);
        if (!empty($tagIds)) {
            $stmt = $db->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (:pid, :tid)");
            foreach ($tagIds as $tid) {
                $stmt->execute(['pid' => $postId, 'tid' => (int)$tid]);
            }
        }
    }
}
