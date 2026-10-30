export function formatTime(value: string | null | undefined): string {
    if (!value) return '';
    const match = value.match(/^(\d{1,2}):(\d{2})/);
    if (!match) return value;
    const hour = Number(match[1]);
    return `${hour % 12 || 12}:${match[2]} ${hour >= 12 ? 'p. m.' : 'a. m.'}`;
}
