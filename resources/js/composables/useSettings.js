import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useSettings() {
    const page = usePage()

    const settings = computed(() => page.props.settings || {})
    const general = computed(() => settings.value.general || {})

    const siteName = computed(() => general.value.site_name || 'TokenHive')
    const siteDescription = computed(() => general.value.site_description || '')
    const contactEmail = computed(() => general.value.contact_email || '')
    const contactPhone = computed(() => general.value.contact_phone || '')
    const defaultCurrency = computed(() => general.value.default_currency || 'USD')
    const currencySymbol = computed(() => general.value.currency_symbol || '$')
    const defaultTimezone = computed(() => general.value.default_timezone || 'America/New_York')
    const maintenanceMode = computed(() => general.value.maintenance_mode === '1')
    const userRegistration = computed(() => general.value.user_registration === '1')

    return {
        settings,
        general,
        siteName,
        siteDescription,
        contactEmail,
        contactPhone,
        defaultCurrency,
        currencySymbol,
        maintenanceMode,
        userRegistration,
        defaultTimezone
    }
}
