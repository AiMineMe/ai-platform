<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blogs = [
            [
                'title' => 'The Future of Digital Marketing',
                'excerpt' => 'Explore the latest trends and technologies that are shaping the future of digital marketing.',
                'content' => '<h2>Introduction</h2><p>Digital marketing is evolving at an unprecedented pace. With new technologies emerging every day, businesses need to stay ahead of the curve to remain competitive.</p><h2>Key Trends</h2><p>Some of the most significant trends include artificial intelligence, voice search optimization, and personalized customer experiences.</p><h2>Conclusion</h2><p>The future belongs to those who can adapt and embrace these emerging technologies.</p>',
                'icon' => 'fas fa-chart-line',
                'color' => '#667eea',
                'read_time' => 8,
                'is_published' => true
            ],
            [
                'title' => 'Building Scalable Web Applications',
                'excerpt' => 'Learn the best practices for creating web applications that can handle millions of users.',
                'content' => '<h2>Scalability Fundamentals</h2><p>Building scalable applications requires careful planning and architecture decisions from the start.</p><h2>Key Principles</h2><p>Focus on loose coupling, microservices architecture, and efficient database design.</p><h2>Performance Optimization</h2><p>Use caching strategies, CDNs, and load balancing to ensure optimal performance.</p>',
                'icon' => 'fas fa-rocket',
                'color' => '#f093fb',
                'read_time' => 12,
                'is_published' => true
            ],
            [
                'title' => 'The Power of Cloud Computing',
                'excerpt' => 'Discover how cloud computing is transforming businesses and enabling innovation.',
                'content' => '<h2>Cloud Advantages</h2><p>Cloud computing offers unprecedented flexibility, scalability, and cost-effectiveness for modern businesses.</p><h2>Migration Strategies</h2><p>Learn how to successfully migrate your applications to the cloud with minimal downtime.</p><h2>Security Considerations</h2><p>Understand the security implications and best practices for cloud deployments.</p>',
                'icon' => 'fas fa-network-wired',
                'color' => '#4facfe',
                'read_time' => 10,
                'is_published' => true
            ],
            [
                'title' => 'Cybersecurity Best Practices',
                'excerpt' => 'Essential security measures every business should implement to protect their digital assets.',
                'content' => '<h2>Security Fundamentals</h2><p>Cybersecurity is no longer optional - it\'s a critical business requirement in today\'s digital landscape.</p><h2>Common Threats</h2><p>Learn about the most common security threats and how to defend against them.</p><h2>Implementation Guide</h2><p>Step-by-step guide to implementing robust security measures in your organization.</p>',
                'icon' => 'fas fa-shield-alt',
                'color' => '#fa709a',
                'read_time' => 6,
                'is_published' => true
            ],
            [
                'title' => 'API Development Made Simple',
                'excerpt' => 'A comprehensive guide to designing and building RESTful APIs that developers love.',
                'content' => '<h2>API Design Principles</h2><p>Good API design is crucial for developer adoption and long-term success.</p><h2>RESTful Best Practices</h2><p>Follow REST conventions to create intuitive and predictable APIs.</p><h2>Documentation and Testing</h2><p>Proper documentation and testing are essential for API success.</p>',
                'icon' => 'fas fa-link',
                'color' => '#a8edea',
                'read_time' => 7,
                'is_published' => true
            ],
            [
                'title' => 'Database Optimization Strategies',
                'excerpt' => 'Advanced techniques for optimizing database performance and managing large datasets.',
                'content' => '<h2>Performance Bottlenecks</h2><p>Identify and resolve common database performance issues before they impact your users.</p><h2>Indexing Strategies</h2><p>Learn how to use indexes effectively to speed up query performance.</p><h2>Scaling Solutions</h2><p>Explore horizontal and vertical scaling options for growing databases.</p>',
                'icon' => 'fas fa-server',
                'color' => '#ffecd2',
                'read_time' => 15,
                'is_published' => true
            ]
        ];

        foreach ($blogs as $blog) {
            Blog::create($blog);
        }
    }
}
