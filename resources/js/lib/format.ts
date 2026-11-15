export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export function timeAgo(value: string | null | undefined): string {
    if (!value) {
        return 'never';
    }

    const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return `${Math.floor(seconds / 86400)} d ago`;
}
