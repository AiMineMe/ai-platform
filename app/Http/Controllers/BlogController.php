<?php

namespace App\Http\Controllers;

use App\Concerns\UploadedFile;
use App\Models\Blog;
use App\Services\DefaultImageService;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    use UploadedFile;
    /**
     * @param Blog $blog
     * @return Response
     */
    public function show(Blog $blog): Response
    {
        if (!$blog->is_published) {
            abort(404);
        }

        $relatedBlogs = Blog::published()
            ->where('id', '!=', $blog->id)
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($relatedBlog) {
                return [
                    'id' => $relatedBlog->id,
                    'title' => $relatedBlog->title,
                    'excerpt' => $relatedBlog->excerpt,
                    'slug' => $relatedBlog->slug,
                    'date' => $relatedBlog->created_at,
                    'readTime' => $relatedBlog->read_time,
                    'icon' => $relatedBlog->icon,
                    'color' => $relatedBlog->color,
                    'image' => $relatedBlog->image ?: DefaultImageService::getImageUrl(null, 'blog', 1200, 630)
                ];
            });

        return Inertia::render('BlogDetail', [
            'blog' => [
                'id' => $blog->id,
                'title' => $blog->title,
                'excerpt' => $blog->excerpt,
                'content' => $blog->content,
                'slug' => $blog->slug,
                'date' => $blog->created_at,
                'readTime' => $blog->read_time,
                'icon' => $blog->icon,
                'color' => $blog->color,
                'image' => $blog->image ?: DefaultImageService::getImageUrl(null, 'blog', 1200, 630)
            ],
            'relatedBlogs' => $relatedBlogs
        ]);
    }
}
