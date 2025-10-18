<template>
    <FrontendLayout>
        <Hero />
        <CryptoPrice :cryptoData="cryptoData" :marketStats="marketStats" />
        <Service />
        <AdvancedFeature />
        <Mining />
        <Network />
        <Blog :blogs="blogs" />
    </FrontendLayout>
</template>

<script setup>
import { defineAsyncComponent } from 'vue'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue"

const Hero = defineAsyncComponent(() => import('@/Components/Frontend/Hero.vue'))
const CryptoPrice = defineAsyncComponent(() => import('@/Components/Frontend/CryptoPrice.vue'))
const Service = defineAsyncComponent(() => import('@/Components/Frontend/Service.vue'))
const AdvancedFeature = defineAsyncComponent(() => import('@/Components/Frontend/AdvancedFeature.vue'))
const Mining = defineAsyncComponent(() => import('@/Components/Frontend/Mining.vue'))
const Network = defineAsyncComponent(() => import('@/Components/Frontend/Network.vue'))
const Blog = defineAsyncComponent(() => import('@/Components/Frontend/Blog.vue'))

const props = defineProps({
    blogs: {
        type: Array,
        default: () => []
    },
    cryptoData: {
        type: Array,
        default: () => []
    },
    marketStats: {
        type: Array,
        default: () => []
    },

})

defineOptions({
    layout: false
})
</script>
