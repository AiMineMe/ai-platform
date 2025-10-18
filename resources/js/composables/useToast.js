import { ref } from 'vue'

const toasts = ref([])
let toastId = 0

export function useToast() {
    const showToast = (message, type = 'info', duration = 5000) => {
        const id = ++toastId
        const toast = {
            id,
            message,
            type,
            visible: true,
            duration,
            startTime: Date.now()
        }

        toasts.value.push(toast)

        if (duration > 0) {
            setTimeout(() => {
                hideToast(id)
            }, duration)
        }

        return id
    }

    const hideToast = (id) => {
        const toast = toasts.value.find(t => t.id === id)
        if (toast) {
            toast.visible = false
            setTimeout(() => {
                const index = toasts.value.findIndex(t => t.id === id)
                if (index > -1) {
                    toasts.value.splice(index, 1)
                }
            }, 300)
        }
    }

    const clearAll = () => {
        toasts.value.forEach(toast => {
            toast.visible = false
        })
        setTimeout(() => {
            toasts.value.splice(0)
        }, 300)
    }

    const success = (message, duration = 5000) => showToast(message, 'success', duration)
    const error = (message, duration = 7000) => showToast(message, 'error', duration)
    const warning = (message, duration = 6000) => showToast(message, 'warning', duration)
    const info = (message, duration = 5000) => showToast(message, 'info', duration)

    return {
        toasts,
        showToast,
        hideToast,
        clearAll,
        success,
        error,
        warning,
        info
    }
}
