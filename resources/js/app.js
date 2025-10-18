// app.js
import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

const showInitialPreloader = () => {
    const hasLoadedBefore = sessionStorage.getItem('app_first_load_complete')
    const isHardRefresh = performance.navigation.type === 1

    if (hasLoadedBefore && !isHardRefresh) {
        return false
    }

    const preloader = document.createElement('div')
    preloader.id = 'initial-preloader'
    preloader.innerHTML = `
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.5s ease;
        ">
            <div style="
                width: 60px;
                height: 60px;
                border: 4px solid rgba(31, 41, 55, 0.3);
                border-top: 4px solid #fff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            "></div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `

    document.body.appendChild(preloader)
    document.body.style.overflow = 'hidden'
    return true
}

const hideInitialPreloader = () => {
    const preloader = document.getElementById('initial-preloader')
    if (preloader) {
        preloader.style.opacity = '0'
        setTimeout(() => {
            preloader.remove()
            document.body.style.overflow = 'auto'
            sessionStorage.setItem('app_first_load_complete', 'true')
        }, 500)
    }
}

const shouldShowPreloader = showInitialPreloader()

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h(App, props),
        })
        app.use(plugin)
        app.mount(el)

        if (shouldShowPreloader) {
            setTimeout(() => {
                hideInitialPreloader()
            }, 1500)
        }
    },
    progress: {
        color: '#1f2937',
        includeCSS: true,
        showSpinner: false,
    }
})
