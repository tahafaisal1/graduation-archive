export const STATUS_ARCHIVED = 'مؤرشف';
export const STATUS_PROPOSAL = 'مقترح';

const STATUS_COLORS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    [STATUS_PROPOSAL]: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
};

const STATUS_LABELS: Record<string, string> = {
    [STATUS_ARCHIVED]: 'مؤرشف',
    [STATUS_PROPOSAL]: 'مقترح',
};

export function statusColor(name?: string | null) {
    return (name && STATUS_COLORS[name]) ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
}

export function statusLabel(name?: string | null) {
    return (name && STATUS_LABELS[name]) ?? name ?? '';
}

export function isProposalStatus(name?: string | null) {
    return name === STATUS_PROPOSAL;
}

export function useProposalStatus() {
    return { STATUS_ARCHIVED, STATUS_PROPOSAL, statusColor, statusLabel, isProposalStatus };
}
