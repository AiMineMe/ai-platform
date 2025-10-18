<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    theme: {
        type: String,
        default: 'auto',
        validator: value => ['admin', 'trading', 'auto'].includes(value)
    }
})

const { toasts, hideToast } = useToast()
const currentTheme = ref(props.theme)

onMounted(() => {
    if (props.theme === 'auto') {
        const isAdmin = window.location.pathname.includes('admin') ||
            document.body.classList.contains('admin-theme')
        currentTheme.value = isAdmin ? 'admin' : 'trading'
    } else {
        currentTheme.value = props.theme
    }
})

const visibleToasts = computed(() => {
    return toasts.value.filter(toast => toast.visible)
})

const getThemeClass = () => {
    return currentTheme.value === 'admin' ? 'toast-admin-theme' : 'toast-trading-theme'
}

const getToastClass = (type) => {
    const theme = currentTheme.value
    return `toast-${type} toast-${theme}`
}

const getGlowClass = (type) => {
    const theme = currentTheme.value
    return `glow-${type}-${theme}`
}

const getIconClass = (type) => {
    const theme = currentTheme.value
    return `icon-${type}-${theme}`
}

const getIconPulseClass = (type) => {
    const theme = currentTheme.value
    return `icon-pulse-${type}-${theme}`
}

const getProgressClass = (type) => {
    const theme = currentTheme.value
    return `progress-${type}-${theme}`
}

const getAccentClass = (type) => {
    const theme = currentTheme.value
    return `accent-${type}-${theme}`
}

const getBorderGlowClass = (type) => {
    const theme = currentTheme.value
    return `border-glow-${type}-${theme}`
}
</script>

<template>
    <div class="fixed top-6 right-6 z-[9999] space-y-3 pointer-events-none">
        <transition-group
            name="toast"
            tag="div"
            class="space-y-3"
        >
            <div
                v-for="toast in visibleToasts"
                :key="toast.id"
                class="toast-container pointer-events-auto"
                :class="[getToastClass(toast.type), getThemeClass()]"
            >
                <div
                    class="toast-glow"
                    :class="getGlowClass(toast.type)"
                />

                <div class="toast-content">
                    <div
                        class="toast-icon"
                        :class="getIconClass(toast.type)"
                    >
                        <div
                            class="icon-pulse"
                            :class="getIconPulseClass(toast.type)"
                        />
                        <svg
                            v-if="toast.type === 'success'"
                            class="w-5 h-5 relative z-10"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        <svg
                            v-else-if="toast.type === 'error'"
                            class="w-5 h-5 relative z-10"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                        <svg
                            v-else-if="toast.type === 'warning'"
                            class="w-5 h-5 relative z-10"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                            />
                        </svg>
                        <svg
                            v-else
                            class="w-5 h-5 relative z-10"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>

                    <div class="toast-message">
                        <div class="message-text">
                            {{ toast.message }}
                        </div>
                        <div
                            v-if="toast.subtitle"
                            class="message-subtitle"
                        >
                            {{ toast.subtitle }}
                        </div>
                    </div>

                    <button
                        class="toast-close-btn"
                        @click="hideToast(toast.id)"
                    >
                        <div class="close-ripple" />
                        <svg
                            class="w-4 h-4 relative z-10"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div
                    v-if="toast.duration > 0"
                    class="toast-progress-container"
                >
                    <div
                        class="toast-progress-bar"
                        :class="getProgressClass(toast.type)"
                        :style="{
              animationDuration: toast.duration + 'ms',
              animationPlayState: toast.paused ? 'paused' : 'running'
            }"
                    >
                        <div class="progress-shine" />
                    </div>
                </div>

                <div
                    class="toast-accent"
                    :class="getAccentClass(toast.type)"
                />
                <div
                    class="toast-border-glow"
                    :class="getBorderGlowClass(toast.type)"
                />
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-container {
    position: relative;
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 20px;
    max-width: 400px;
    min-width: 320px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.6),
        0 0 0 1px rgba(255, 255, 255, 0.05);
    overflow: hidden;
    transform: translateZ(0);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-admin-theme {
    background: linear-gradient(135deg,
    rgba(30, 41, 59, 0.95) 0%,
    rgba(51, 65, 85, 0.9) 100%);
}

.toast-trading-theme {
    background: linear-gradient(135deg,
    rgba(15, 23, 42, 0.95) 0%,
    rgba(30, 41, 59, 0.9) 100%);
}

.toast-glow {
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: 20px;
    opacity: 0;
    z-index: -1;
    filter: blur(12px);
    animation: glow-pulse 3s infinite ease-in-out;
}

.glow-success-admin { background: linear-gradient(45deg, #22c55e, #16a34a, #15803d); }
.glow-error-admin { background: linear-gradient(45deg, #ef4444, #dc2626, #b91c1c); }
.glow-warning-admin { background: linear-gradient(45deg, #f59e0b, #d97706, #b45309); }
.glow-info-admin { background: linear-gradient(45deg, #3b82f6, #2563eb, #1d4ed8); }

.glow-success-trading { background: linear-gradient(45deg, #10b981, #059669, #047857); }
.glow-error-trading { background: linear-gradient(45deg, #f43f5e, #e11d48, #be123c); }
.glow-warning-trading { background: linear-gradient(45deg, #eab308, #ca8a04, #a16207); }
.glow-info-trading { background: linear-gradient(45deg, #06b6d4, #0891b2, #0e7490); }

.toast-content {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    position: relative;
    z-index: 1;
}

.toast-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
    overflow: hidden;
    animation: icon-entrance 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.icon-pulse {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 12px;
    animation: pulse-wave 2s infinite;
}

.icon-success-admin {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
}
.icon-pulse-success-admin { background: rgba(34, 197, 94, 0.3); }

.icon-error-admin {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}
.icon-pulse-error-admin { background: rgba(239, 68, 68, 0.3); }

.icon-warning-admin {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
}
.icon-pulse-warning-admin { background: rgba(245, 158, 11, 0.3); }

.icon-info-admin {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
}
.icon-pulse-info-admin { background: rgba(59, 130, 246, 0.3); }

.icon-success-trading {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}
.icon-pulse-success-trading { background: rgba(16, 185, 129, 0.3); }

.icon-error-trading {
    background: linear-gradient(135deg, #f43f5e, #e11d48);
    color: white;
    box-shadow: 0 8px 25px rgba(244, 63, 94, 0.4);
}
.icon-pulse-error-trading { background: rgba(244, 63, 94, 0.3); }

.icon-warning-trading {
    background: linear-gradient(135deg, #eab308, #ca8a04);
    color: white;
    box-shadow: 0 8px 25px rgba(234, 179, 8, 0.4);
}
.icon-pulse-warning-trading { background: rgba(234, 179, 8, 0.3); }

.icon-info-trading {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    box-shadow: 0 8px 25px rgba(6, 182, 212, 0.4);
}
.icon-pulse-info-trading { background: rgba(6, 182, 212, 0.3); }

.toast-message {
    flex: 1;
    margin-top: 2px;
}

.message-text {
    color: #f8fafc;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.4;
    margin-bottom: 2px;
}

.message-subtitle {
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.3;
}

.toast-close-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
    border: none;
    cursor: pointer;
    flex-shrink: 0;
    overflow: hidden;
    transition: all 0.2s ease;
}

.toast-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #f8fafc;
    transform: scale(1.05);
}

.close-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: all 0.3s ease;
}

.toast-close-btn:active .close-ripple {
    width: 40px;
    height: 40px;
}

.toast-progress-container {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
    border-radius: 0 0 16px 16px;
}

.toast-progress-bar {
    height: 100%;
    width: 0%;
    position: relative;
    overflow: hidden;
    animation: progress-fill linear forwards;
}

.progress-shine {
    position: absolute;
    top: 0;
    right: -20px;
    bottom: 0;
    width: 20px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
    animation: progress-shine 2s infinite;
}

.progress-success-admin { background: linear-gradient(90deg, #22c55e, #10b981); }
.progress-error-admin { background: linear-gradient(90deg, #ef4444, #f87171); }
.progress-warning-admin { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.progress-info-admin { background: linear-gradient(90deg, #3b82f6, #60a5fa); }

.progress-success-trading { background: linear-gradient(90deg, #10b981, #34d399); }
.progress-error-trading { background: linear-gradient(90deg, #f43f5e, #fb7185); }
.progress-warning-trading { background: linear-gradient(90deg, #eab308, #facc15); }
.progress-info-trading { background: linear-gradient(90deg, #06b6d4, #22d3ee); }

.toast-accent {
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    border-radius: 16px 0 0 16px;
}

.accent-success-admin { background: linear-gradient(180deg, #22c55e, #16a34a); }
.accent-error-admin { background: linear-gradient(180deg, #ef4444, #dc2626); }
.accent-warning-admin { background: linear-gradient(180deg, #f59e0b, #d97706); }
.accent-info-admin { background: linear-gradient(180deg, #3b82f6, #2563eb); }

.accent-success-trading { background: linear-gradient(180deg, #10b981, #059669); }
.accent-error-trading { background: linear-gradient(180deg, #f43f5e, #e11d48); }
.accent-warning-trading { background: linear-gradient(180deg, #eab308, #ca8a04); }
.accent-info-trading { background: linear-gradient(180deg, #06b6d4, #0891b2); }

.toast-border-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 16px;
    pointer-events: none;
    opacity: 0.5;
    animation: border-glow 3s infinite ease-in-out;
}

@keyframes progress-fill {
    from { width: 0%; }
    to { width: 100%; }
}

@keyframes glow-pulse {
    0%, 100% { opacity: 0.4; transform: scale(0.95); }
    50% { opacity: 0.8; transform: scale(1.02); }
}

@keyframes icon-entrance {
    0% {
        transform: scale(0) rotate(-180deg);
        opacity: 0;
    }
    50% {
        transform: scale(1.2) rotate(-90deg);
        opacity: 0.8;
    }
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

@keyframes pulse-wave {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.1); opacity: 0.4; }
    100% { transform: scale(1); opacity: 0.8; }
}

@keyframes progress-shine {
    0% { transform: translateX(-40px); }
    100% { transform: translateX(420px); }
}

@keyframes border-glow {
    0%, 100% { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1); }
    50% { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3); }
}

.toast-enter-active {
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(120%) scale(0.7) rotate(15deg);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(120%) scale(0.7) rotate(-15deg);
}

.toast-move {
    transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.toast-container:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow:
        0 35px 60px -12px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(255, 255, 255, 0.1);
}

.toast-container:hover .toast-glow {
    opacity: 0.9;
    animation-duration: 1.5s;
}

@media (max-width: 640px) {
    .toast-container {
        max-width: 350px;
        min-width: 300px;
        margin: 0 16px;
        padding: 16px;
    }

    .toast-icon {
        width: 36px;
        height: 36px;
    }

    .message-text {
        font-size: 14px;
    }
}
</style>
