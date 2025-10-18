<template>
    <div class="min-h-screen bg-gray-900 text-white overflow-x-hidden">
        <Navbar />
        <slot />
        <Footer />
        <ToastContainer />
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import Navbar from "@/Components/Frontend/Navbar.vue"
import Footer from "@/Components/Frontend/Footer.vue"
import ToastContainer from "@/Components/Toast/ToastContainer.vue";
onMounted(() => {
    document.addEventListener('click', (e) => {
        const target = e.target.closest('a[href^="#"]')
        if (target) {
            e.preventDefault()
            const sectionId = target.getAttribute('href').substring(1)
            const element = document.getElementById(sectionId)
            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                })
            }
        }
    })
})
</script>

<style>
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background-color: #111827;
}

::-webkit-scrollbar-thumb {
    background-color: #1f2937;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background-color: #1f2937;
}

html {
    scrollbar-width: thin;
    scrollbar-color: #1f2937 #111827;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

@media (max-width: 768px) and (prefers-reduced-motion: no-preference) {
    .animate-blob {
        animation-duration: 15s;
    }

    .hover\:scale-110:hover {
        transform: scale(1.05);
    }
}
</style>
