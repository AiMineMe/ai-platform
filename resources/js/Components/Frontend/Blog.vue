<template>
    <section
        class="relative py-16 lg:py-24 overflow-hidden"
        id="blog"
        :style="sectionStyle"
    >
        <div class="absolute inset-0 opacity-20">
            <div
                class="absolute inset-0"
                :style="gridStyle"
            ></div>
        </div>

        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute top-1/3 left-1/6 w-96 h-96 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-1/3 right-1/6 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-1000"
                :style="blob2Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('blogBadge') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('blogTitle') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('blogDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <div v-if="displayBlogs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 mb-16 animate-fade-in-up animation-delay-300">
                <article
                    v-for="(post, index) in displayBlogs"
                    :key="post.id"
                    :style="{ animationDelay: `${index * 0.1}s` }"
                    class="group blog-card backdrop-blur-xl border rounded-3xl overflow-hidden transition-all duration-500 hover:-translate-y-3 hover:scale-105 animate-fade-in-up cursor-pointer"
                    @click="selectPost(post)"
                >
                    <div class="relative h-48 overflow-hidden" :style="{ background: `linear-gradient(135deg, ${post.color}, ${adjustColor(post.color, 30)})` }">
                        <div v-if="post.image" class="absolute inset-0">
                            <img :src="`${post.image}`" :alt="post.title" class="w-full h-full object-cover">
                        </div>
                        <div v-else class="absolute inset-0 flex items-center justify-center">
                            <i :class="post.icon" class="text-6xl text-white/80"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                        <div class="absolute top-4 right-4 backdrop-blur-sm px-3 py-1 rounded-full text-white text-sm" :style="{ backgroundColor: derivedColors.surface + '60' }">
                            <i class="fas fa-clock mr-1"></i>
                            {{ post.read_time || post.readTime }} {{ t('minRead') }}
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="flex items-center gap-2 text-sm mb-4" :style="{ color: derivedColors.textMuted }">
                            <i class="fas fa-calendar text-xs"></i>
                            <span>{{ formatDate(post.created_at || post.date) }}</span>
                        </div>
                        <h3 class="text-xl font-bold mb-3 transition-colors duration-300 line-clamp-2" :style="{ color: derivedColors.textPrimary }">
                            {{ post.title }}
                        </h3>
                        <p class="text-sm leading-relaxed mb-6 transition-colors duration-300 line-clamp-3" :style="{ color: derivedColors.textSecondary }">
                            {{ post.excerpt }}
                        </p>
                        <div class="flex items-center justify-end pt-4 border-t" :style="{ borderColor: derivedColors.border + '30' }">
                            <div class="flex items-center transition-colors duration-300" :style="{ color: derivedColors.accent }">
                                <span class="text-sm font-medium mr-2">{{ t('readMore') }}</span>
                                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform duration-300"></i>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-0 left-0 w-full h-1 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left" :style="{ background: `linear-gradient(90deg, ${post.color}, ${adjustColor(post.color, 40)})` }"></div>
                </article>
            </div>

            <div v-else class="text-center py-16 animate-fade-in-up">
                <i class="fas fa-blog text-6xl mb-6" :style="{ color: derivedColors.textMuted }"></i>
                <h3 class="text-2xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('noBlogsTitle') }}
                </h3>
                <p :style="{ color: derivedColors.textSecondary }">
                    {{ t('noBlogsMessage') }}
                </p>
            </div>

            <div class="backdrop-blur-xl border rounded-3xl p-8 lg:p-12 text-center animate-fade-in-up animation-delay-600" :style="newsletterStyle">
                <div class="max-w-2xl mx-auto">
                    <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                        {{ t('newsletterTitle') }}
                    </h3>
                    <p class="mb-8" :style="{ color: derivedColors.textSecondary }">
                        {{ t('newsletterDescription') }}
                    </p>

                    <form @submit.prevent="handleNewsletterSubmit" class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
                        <input
                            type="email"
                            v-model="newsletterEmail"
                            :placeholder="t('emailPlaceholder')"
                            required
                            class="flex-1 px-6 py-4 border rounded-full text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                            :style="inputStyle"
                        >
                        <button
                            type="submit"
                            class="px-8 py-4 font-semibold rounded-full transition-all duration-300 hover:scale-105 whitespace-nowrap"
                            :style="subscribeButtonStyle"
                            :disabled="isSubscribing"
                        >
                            <span v-if="!isSubscribing">
                                <i class="fas fa-paper-plane mr-2"></i>
                                {{ t('subscribeButton') }}
                            </span>
                            <span v-else>
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                {{ t('subscribingText') }}
                            </span>
                        </button>
                    </form>

                    <p class="text-sm mt-4" :style="{ color: derivedColors.textMuted }">
                        {{ t('privacyNote') }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'
import { useToast } from '@/composables/useToast'
const { showToast } = useToast()
const { t } = useTranslation()
const props = defineProps({
    blogs: {
        type: Array,
        default: () => []
    }
})

const page = usePage()
const primaryColor = computed(() => page.props.primaryColor || '#1f2937')

const derivedColors = computed(() => {
    const primary = primaryColor.value

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

const underlineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`
}))

const newsletterStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const inputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '40',
    focusRingColor: derivedColors.value.accent + '50'
}))

const subscribeButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

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

// Reactive data
const newsletterEmail = ref('')
const isSubscribing = ref(false)

const displayBlogs = computed(() => {
    if (props.blogs && props.blogs.length > 0) {
        return props.blogs
    }

    return []
})

const selectPost = (post) => {
    window.location.href = `/blog/${post.slug || post.id}`
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const handleNewsletterSubmit = async () => {
    if (!newsletterEmail.value) return

    isSubscribing.value = true

    try {
        const response = await fetch('/newsletter/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                email: newsletterEmail.value
            })
        })

        const data = await response.json()

        if (data.success) {
            showToast(data.message, 'success')
            newsletterEmail.value = ''
        } else {
            showToast(data.message, 'error')
        }

    } catch (error) {
        showToast('Something went wrong. Please try again later.', 'error')
    } finally {
        isSubscribing.value = false
    }
}
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
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

.blog-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
