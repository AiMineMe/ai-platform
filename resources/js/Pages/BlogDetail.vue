<template>
    <FrontendLayout>
        <section
            class="relative py-16 lg:py-24 overflow-hidden"
            id="contact-us"
            :style="sectionStyle"
        >
            <div class="absolute inset-0 opacity-20">
                <div class="absolute inset-0" :style="gridStyle"></div>
            </div>

            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute top-1/4 left-1/6 w-96 h-96 rounded-full blur-3xl animate-blob" :style="blob1Style"></div>
                <div class="absolute bottom-1/4 right-1/6 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-1000" :style="blob2Style"></div>
            </div>

            <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="mb-8 animate-fade-in-up">
                    <button
                        @click="$inertia.visit('/')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl transition-all duration-300 hover:scale-105"
                        :style="badgeStyle"
                    >
                        <i class="fas fa-arrow-left text-sm"></i>
                        <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">Back to Home</span>
                    </button>
                </div>

                <div class="mb-12 animate-fade-in-up animation-delay-300">
                    <div class="flex items-center gap-4 text-sm mb-6" :style="{ color: derivedColors.textMuted }">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar text-xs"></i>
                            <span>{{ formatDate(blog.date) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-clock text-xs"></i>
                            <span>{{ blog.readTime }} min read</span>
                        </div>
                    </div>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ blog.title }}
                        </span>
                    </h1>

                    <p class="text-lg lg:text-xl leading-relaxed mb-8" :style="{ color: derivedColors.textSecondary }">
                        {{ blog.excerpt }}
                    </p>

                    <div v-if="blog.image" class="relative rounded-3xl overflow-hidden mb-8">
                        <img :src="`${blog.image}`" :alt="blog.title" class="w-full h-64 md:h-96 object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                    </div>

                    <div v-else class="flex justify-center mb-8">
                        <div class="w-24 h-24 rounded-full flex items-center justify-center" :style="{ background: `linear-gradient(135deg, ${blog.color}, ${adjustColor(blog.color, 30)})` }">
                            <i :class="blog.icon" class="text-3xl text-white/80"></i>
                        </div>
                    </div>
                </div>

                <div class="prose prose-lg max-w-none mb-16 animate-fade-in-up animation-delay-600" :style="proseStyle">
                    <div v-html="blog.content"></div>
                </div>

                <div v-if="relatedBlogs.length > 0" class="animate-fade-in-up animation-delay-900">
                    <h2 class="text-3xl font-bold mb-8" :style="{ color: derivedColors.textPrimary }">
                        Related Articles
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <article
                            v-for="(post, index) in relatedBlogs"
                            :key="post.id"
                            :style="{ animationDelay: `${index * 0.1}s` }"
                            class="group blog-card backdrop-blur-xl border rounded-3xl overflow-hidden transition-all duration-500 hover:-translate-y-3 hover:scale-105 animate-fade-in-up cursor-pointer relative h-64"
                            @click="selectPost(post)"
                        >
                            <div v-if="post.image" class="absolute inset-0">
                                <img :src="`${post.image}`" :alt="post.title" class="w-full h-full object-cover">
                            </div>
                            <div v-else class="absolute inset-0 flex items-center justify-center" :style="{ background: `linear-gradient(135deg, ${post.color}, ${adjustColor(post.color, 30)})` }">
                                <i :class="post.icon" class="text-4xl text-white/80"></i>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>

                            <div class="absolute top-3 right-3 backdrop-blur-sm px-2 py-1 rounded-full text-white text-xs" :style="{ backgroundColor: derivedColors.surface + '60' }">
                                {{ post.readTime }}m
                            </div>

                            <div class="absolute bottom-0 left-0 right-0 p-4">
                                <div class="flex items-center gap-2 text-xs mb-3" :style="{ color: derivedColors.textMuted }">
                                    <i class="fas fa-calendar text-xs"></i>
                                    <span>{{ formatDate(post.date) }}</span>
                                </div>

                                <h3 class="text-lg font-bold mb-2 transition-colors duration-300 line-clamp-2" :style="{ color: derivedColors.textPrimary }">
                                    {{ post.title }}
                                </h3>

                                <p class="text-sm leading-relaxed transition-colors duration-300 line-clamp-2" :style="{ color: derivedColors.textSecondary }">
                                    {{ post.excerpt }}
                                </p>
                            </div>

                            <div class="absolute top-0 left-0 w-full h-1 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left" :style="{ background: `linear-gradient(90deg, ${post.color}, ${adjustColor(post.color, 40)})` }"></div>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue"

const props = defineProps({
    blog: {
        type: Object,
        required: true
    },
    relatedBlogs: {
        type: Array,
        default: () => []
    }
})

const page = usePage()

const primaryColor = computed(() => page.props.primaryColor || '#1f2937')

const adjustColor = (color, amount) => {
    const hex = color.replace('#', '')
    const r = parseInt(hex.substr(0, 2), 16)
    const g = parseInt(hex.substr(2, 2), 16)
    const b = parseInt(hex.substr(4, 2), 16)

    const newR = Math.min(255, Math.max(0, r + amount))
    const newG = Math.min(255, Math.max(0, g + amount))
    const newB = Math.min(255, Math.max(0, b + amount))

    return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`
}

const derivedColors = computed(() => {
    const primary = primaryColor.value

    return {
        primary: primary,
        secondary: adjustColor(primary, 40),
        background: adjustColor(primary, -30),
        surface: adjustColor(primary, -10),
        textPrimary: '#ffffff',
        textSecondary: '#e2e8f0',
        textMuted: '#94a3b8',
        border: adjustColor(primary, 60),
        accent: adjustColor(primary, 120),
        success: '#10b981',
        warning: '#f59e0b',
        error: '#ef4444'
    }
})

const sectionStyle = computed(() => ({
    background: `radial-gradient(ellipse at top, ${derivedColors.value.primary}30 0%, ${derivedColors.value.background} 50%, ${derivedColors.value.background} 100%)`
}))

const gridStyle = computed(() => ({
    backgroundImage: `linear-gradient(${derivedColors.value.border}15 1px, transparent 1px), linear-gradient(90deg, ${derivedColors.value.border}15 1px, transparent 1px)`,
    backgroundSize: '50px 50px'
}))

const blob1Style = computed(() => ({
    background: `linear-gradient(45deg, ${derivedColors.value.accent}20, ${derivedColors.value.secondary}15)`
}))

const blob2Style = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.secondary}20, ${derivedColors.value.accent}15)`
}))

const badgeStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '80',
    borderColor: derivedColors.value.border + '30'
}))

const gradientText = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.textPrimary} 0%, ${derivedColors.value.textSecondary} 50%, ${derivedColors.value.accent} 100%)`
)

const proseStyle = computed(() => ({
    '--tw-prose-body': derivedColors.value.textSecondary,
    '--tw-prose-headings': derivedColors.value.textPrimary,
    '--tw-prose-lead': derivedColors.value.textSecondary,
    '--tw-prose-links': derivedColors.value.accent,
    '--tw-prose-bold': derivedColors.value.textPrimary,
    '--tw-prose-counters': derivedColors.value.textMuted,
    '--tw-prose-bullets': derivedColors.value.textMuted,
    '--tw-prose-hr': derivedColors.value.border + '40',
    '--tw-prose-quotes': derivedColors.value.textPrimary,
    '--tw-prose-quote-borders': derivedColors.value.border + '40',
    '--tw-prose-captions': derivedColors.value.textMuted,
    '--tw-prose-code': derivedColors.value.textPrimary,
    '--tw-prose-pre-code': derivedColors.value.textSecondary,
    '--tw-prose-pre-bg': derivedColors.value.surface + '80',
    '--tw-prose-th-borders': derivedColors.value.border + '40',
    '--tw-prose-td-borders': derivedColors.value.border + '20'
}))

const selectPost = (post) => {
    router.visit(`/blog/${post.slug}`)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Enhanced animations */
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes blob {
    0%, 100% {
        transform: translate(0px, 0px) scale(1);
    }
    33% {
        transform: translate(30px, -50px) scale(1.1);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-900 { animation-delay: 0.9s; }
.animation-delay-1000 { animation-delay: 1s; }

/* Blog card hover effects */
.blog-card {
    background-color: var(--surface-color);
    border-color: var(--border-color);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.blog-card:hover {
    box-shadow: 0 25px 50px -12px var(--hover-shadow);
    background-color: var(--surface-hover-color);
    border-color: var(--border-hover-color);
}

/* Smooth transitions */
.blog-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Prose styling */
.prose {
    max-width: none;
}

.prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
    color: var(--tw-prose-headings);
}

.prose p {
    color: var(--tw-prose-body);
}

.prose a {
    color: var(--tw-prose-links);
    text-decoration: none;
}

.prose a:hover {
    text-decoration: underline;
}

.prose strong {
    color: var(--tw-prose-bold);
}

.prose code {
    color: var(--tw-prose-code);
    background-color: var(--tw-prose-pre-bg);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.prose pre {
    background-color: var(--tw-prose-pre-bg);
    border-radius: 0.5rem;
    padding: 1rem;
    overflow-x: auto;
}

.prose pre code {
    background-color: transparent;
    padding: 0;
    color: var(--tw-prose-pre-code);
}

.prose blockquote {
    border-left: 4px solid var(--tw-prose-quote-borders);
    padding-left: 1rem;
    color: var(--tw-prose-quotes);
    font-style: italic;
}

.prose ul, .prose ol {
    color: var(--tw-prose-body);
}

.prose li::marker {
    color: var(--tw-prose-bullets);
}
</style>
