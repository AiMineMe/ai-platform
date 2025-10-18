<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BlogController extends Controller
{
    use UploadedFile;

    /**
     * @return Response
     */
    public function index(): Response
    {
        $blogs = Blog::latest()->paginate(20);
        return Inertia::render('Admin/Blog/Index', [
            'blogs' => $blogs,
        ]);
    }

    /**
     * @return Response
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Blog/Form');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'excerpt' => 'required|string|max:500',
                'content' => 'required|string|min:10',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'read_time' => 'required|integer|min:1|max:60',
                'is_published' => 'boolean'
            ], [
                'title.required' => 'Blog title is required.',
                'title.max' => 'Blog title cannot exceed 255 characters.',
                'excerpt.required' => 'Blog excerpt is required.',
                'excerpt.max' => 'Blog excerpt cannot exceed 500 characters.',
                'content.required' => 'Blog content is required.',
                'content.min' => 'Blog content must be at least 10 characters.',
                'image.image' => 'The file must be an image.',
                'image.mimes' => 'Image must be a file of type: jpeg, png, jpg, gif, webp.',
                'image.max' => 'Image size cannot exceed 2MB.',
                'read_time.required' => 'Read time is required.',
                'read_time.min' => 'Read time must be at least 1 minute.',
                'read_time.max' => 'Read time cannot exceed 60 minutes.',
            ]);

            if ($request->hasFile('image')) {
                try {
                    $validated['image'] = $this->move($request->file('image'));
                } catch (\Exception $e) {
                    throw ValidationException::withMessages([
                        'image' => 'Failed to upload image. Please try again.'
                    ]);
                }
            }

            $validated['is_published'] = $request->boolean('is_published', true);
            $blog = Blog::create($validated);

            return redirect()->route('admin.blogs.index')->with('success', "Blog post '{$blog->title}' created successfully!");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please correct the errors below and try again.');
        }
    }

    /**
     * @param Blog $blog
     * @return Response
     */
    public function edit(Blog $blog): Response
    {
        return Inertia::render('Admin/Blog/Form', [
            'blog' => $blog
        ]);
    }


    /**
     * @param Request $request
     * @param Blog $blog
     * @return RedirectResponse
     */
    public function update(Request $request, Blog $blog): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'excerpt' => 'required|string|max:500',
                'content' => 'required|string|min:10',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'read_time' => 'required|integer|min:1|max:60',
                'is_published' => 'boolean'
            ], [
                'title.required' => 'Blog title is required.',
                'title.max' => 'Blog title cannot exceed 255 characters.',
                'excerpt.required' => 'Blog excerpt is required.',
                'excerpt.max' => 'Blog excerpt cannot exceed 500 characters.',
                'content.required' => 'Blog content is required.',
                'content.min' => 'Blog content must be at least 10 characters.',
                'image.image' => 'The file must be an image.',
                'image.mimes' => 'Image must be a file of type: jpeg, png, jpg, gif, webp.',
                'image.max' => 'Image size cannot exceed 2MB.',
                'read_time.required' => 'Read time is required.',
                'read_time.min' => 'Read time must be at least 1 minute.',
                'read_time.max' => 'Read time cannot exceed 60 minutes.',
            ]);

            if ($request->hasFile('image')) {
                try {
                    $validated['image'] = $this->move($request->file('image'), $blog->image);
                } catch (\Exception $e) {
                    throw ValidationException::withMessages([
                        'image' => 'Failed to upload image. Please try again.'
                    ]);
                }
            }

            $validated['is_published'] = $request->boolean('is_published', false);
            $wasPublished = $blog->is_published;
            $willBePublished = $validated['is_published'];

            $blog->update($validated);
            $message = "Blog post '{$blog->title}' updated successfully!";
            if (!$wasPublished && $willBePublished) {
                $message = "Blog post '{$blog->title}' updated and published successfully!";
            } elseif ($wasPublished && !$willBePublished) {
                $message = "Blog post '{$blog->title}' updated and moved to draft.";
            }

            return redirect()->route('admin.blogs.index')->with('success', $message);

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please correct the errors below and try again.');
        }
    }

    /**
     * @param Blog $blog
     * @return RedirectResponse
     */
    public function destroy(Blog $blog): RedirectResponse
    {
        try {
            $blogTitle = $blog->title;
            $directory = 'assets/files';
            if (file_exists($directory . '/' . $blog->image) && is_file($directory . '/' . $blog->image)) {
                @unlink($directory . '/' . $blog->image);
            }

            $blog->delete();
            return redirect()->route('admin.blogs.index')->with('success', "Blog post '{$blogTitle}' deleted successfully!");
        } catch (\Exception $e) {
            Log::error('Blog deletion failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete blog post. Please try again.');
        }
    }
}
