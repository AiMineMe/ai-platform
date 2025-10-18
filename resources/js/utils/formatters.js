// utils/formatters.js
export class FormatUtils {
    /**
     * Format numbers with abbreviations (K, M, B)
     * @param {number|string|null|undefined} value - The value to format
     * @param {number} decimals - Number of decimal places (default: 2)
     * @returns {string} Formatted number string
     */
    static formatNumber(value, decimals = 2) {
        if (value === null || value === undefined) return '0';

        const num = parseFloat(value) || 0;

        if (num >= 1000000000) {
            return (num / 1000000000).toFixed(decimals) + 'B';
        } else if (num >= 1000000) {
            return (num / 1000000).toFixed(decimals) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(decimals) + 'K';
        } else if (Number.isInteger(num)) {
            return num.toString();
        } else {
            return num.toFixed(decimals);
        }
    }

    /**
     * Format date strings
     * @param {string|Date} dateString - The date to format
     * @param {Object} options - Intl.DateTimeFormat options
     * @param {string} locale - Locale string (default: 'en-US')
     * @returns {string} Formatted date string
     */
    static formatDate(dateString, options = {}, locale = 'en-US') {
        if (!dateString) return 'N/A';

        try {
            const date = new Date(dateString);
            const defaultOptions = {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };

            const formatOptions = {...defaultOptions, ...options};
            return new Intl.DateTimeFormat(locale, formatOptions).format(date);
        } catch (error) {
            console.error('Error formatting date:', error);
            return 'Invalid Date';
        }
    }

    /**
     * Format percentage values
     * @param {number|string} value - The value to format (0.15 = 15%)
     * @param {number} decimals - Number of decimal places (default: 1)
     * @param {string} locale - Locale string (default: 'en-US')
     * @returns {string} Formatted percentage string
     */
    static formatPercentage(value, decimals = 1, locale = 'en-US') {
        if (value === null || value === undefined) return '0%';

        const num = parseFloat(value) || 0;

        try {
            return new Intl.NumberFormat(locale, {
                style: 'percent',
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(num);
        } catch (error) {
            console.error('Error formatting percentage:', error);
            return `${(num * 100).toFixed(decimals)}%`;
        }
    }

    /**
     * Format relative time (e.g., "2 hours ago")
     * @param {string|Date} dateString - The date to compare
     * @param {string} locale - Locale string (default: 'en-US')
     * @returns {string} Relative time string
     */
    static formatRelativeTime(dateString, locale = 'en-US') {
        if (!dateString) return 'Unknown';

        try {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
            }
            if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
            }
            if (diffInSeconds < 2592000) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} ${days === 1 ? 'day' : 'days'} ago`;
            }

            // For older dates, return formatted date
            return FormatUtils.formatDate(dateString, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }, locale);
        } catch (error) {
            console.error('Error formatting relative time:', error);
            return 'Unknown';
        }
    }
}
