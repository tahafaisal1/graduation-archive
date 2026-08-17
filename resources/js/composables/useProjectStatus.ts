export const STATUS_ARCHIVED = 'مؤرشف';
export const STATUS_PROPOSAL = 'مقترح';

const STATUS_COLORS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    [STATUS_PROPOSAL]: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
};

const STATUS_LABELS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'مؤرشف',
    [STATUS_PROPOSAL]: 'في انتظار الموافقة',
};

export function statusColor(name?: string | null) {
    return (name && STATUS_COLORS[name]) ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
}

export function statusLabel(name?: string | null) {
    return (name && STATUS_LABELS[name]) ?? name ?? '';
}

export function isPendingApproval(name?: string | null) {
    return name === STATUS_PROPOSAL;
}

export function useProjectStatus() {
    return { STATUS_ARCHIVED, STATUS_PROPOSAL, statusColor, statusLabel, isPendingApproval };
}
