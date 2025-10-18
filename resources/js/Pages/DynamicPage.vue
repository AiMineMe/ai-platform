<template>
    <FrontendLayout>
        <div class="mt-8 sm:mt-6 md:mt-4 lg:mt-2 xl:mt-0">
            <template v-if="menu.components && menu.components.length > 0">
                <component
                    v-for="(componentName, index) in menu.components"
                    :key="`${componentName}-${index}`"
                    :is="getComponent(componentName)"
                    v-bind="getComponentProps(componentName)"
                />
            </template>
        </div>
    </FrontendLayout>
</template>

<script setup>
import { shallowRef, onMounted } from 'vue'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue"

const props = defineProps({
    menu: {
        type: Object,
        required: true
    },
    componentData: {
        type: Object,
        default: () => ({})
    },
    pageTitle: {
        type: String,
        default: ''
    }
})

const componentCache = new Map()
const loadedComponents = shallowRef({})
const componentMap = {
    'Hero': () => import('@/Components/Frontend/Hero.vue'),
    'CryptoPrice': () => import('@/Components/Frontend/CryptoPrice.vue'),
    'Service': () => import('@/Components/Frontend/Service.vue'),
    'AdvancedFeature': () => import('@/Components/Frontend/AdvancedFeature.vue'),
    'Mining': () => import('@/Components/Frontend/Mining.vue'),
    'Network': () => import('@/Components/Frontend/Network.vue'),
    'Blog': () => import('@/Components/Frontend/Blog.vue'),
}

const preloadComponents = async () => {
    if (props.menu.components && Array.isArray(props.menu.components)) {
        const promises = props.menu.components.map(async (componentName) => {
            if (componentMap[componentName] && !componentCache.has(componentName)) {
                try {
                    const component = await componentMap[componentName]()
                    componentCache.set(componentName, component.default || component)
                    return { [componentName]: component.default || component }
                } catch (error) {
                    console.warn(`Failed to load component: ${componentName}`, error)
                    return { [componentName]: null }
                }
            }
            return null
        })

        const results = await Promise.all(promises)
        const components = Object.assign({}, ...results.filter(Boolean))
        loadedComponents.value = { ...loadedComponents.value, ...components }
    }
}

const getComponent = (componentName) => {
    if (componentCache.has(componentName)) {
        return componentCache.get(componentName)
    }

    if (loadedComponents.value[componentName]) {
        return loadedComponents.value[componentName]
    }

    if (componentMap[componentName]) {
        return () => componentMap[componentName]().then(module => {
            const component = module.default || module
            componentCache.set(componentName, component)
            return component
        })
    }

    return null
}

const getComponentProps = (componentName) => {
    const baseProps = {
        ...props.menu.component_props,
        ...props.componentData,
        menuData: props.menu
    }

    switch (componentName) {
        case 'Blog':
            return {
                ...baseProps,
                blogs: props.componentData.blogs || []
            }
        case 'CryptoPrice':
            return {
                ...baseProps,
                prices: props.componentData.prices || []
            }
        case 'Hero':
            return {
                ...baseProps,
                heroData: props.componentData.heroData || {}
            }
        case 'Service':
            return {
                ...baseProps,
                services: props.componentData.services || []
            }
        case 'Mining':
            return {
                ...baseProps,
                miningData: props.componentData.miningData || {}
            }
        case 'Network':
            return {
                ...baseProps,
                networkData: props.componentData.networkData || {}
            }
        case 'AdvancedFeature':
            return {
                ...baseProps,
                features: props.componentData.features || []
            }
        default:
            return baseProps
    }
}

onMounted(() => {
    if (props.pageTitle) {
        document.title = props.pageTitle
    }
    preloadComponents()
})

const updateMetaData = () => {
    if (typeof window !== 'undefined') {
        const metaDescription = document.querySelector('meta[name="description"]')
        if (metaDescription) {
            metaDescription.setAttribute('content', `${props.menu.menu_name} - Dynamic page with interactive components`)
        }

        document.title = `${props.pageTitle || props.menu.menu_name} | Your Crypto Site`
    }
}

onMounted(updateMetaData)
</script>

<style scoped>
.component-enter-active,
.component-leave-active {
    transition: opacity 0.3s ease;
}

.component-enter-from,
.component-leave-to {
    opacity: 0;
}

.component-loading {
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #f8fafc, #e2e8f0);
    border-radius: 12px;
    margin: 20px 0;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.component-error {
    padding: 20px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    color: #dc2626;
    text-align: center;
    margin: 20px 0;
}

@media (max-width: 768px) {
    .component-loading {
        min-height: 150px;
        margin: 15px 0;
    }
}
</style>
