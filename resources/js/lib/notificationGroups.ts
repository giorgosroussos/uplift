/**
 * Groups notifications by local calendar day, keeping their order
 * (newest first), for the notification centre (UC-007, DEC-002).
 */
export type DayGroup<T> = {
    /** Local calendar day, YYYY-MM-DD. */
    day: string;
    /** 'today' or 'yesterday' when it is one of them, else null. */
    relative: 'today' | 'yesterday' | null;
    items: T[];
};

function localDay(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function groupByDay<T extends { created_at: string }>(
    items: T[],
    now: Date = new Date(),
): DayGroup<T>[] {
    const today = localDay(now);
    const yesterdayDate = new Date(now);
    yesterdayDate.setDate(now.getDate() - 1);
    const yesterday = localDay(yesterdayDate);

    const groups: DayGroup<T>[] = [];

    for (const item of items) {
        const day = localDay(new Date(item.created_at));
        let group = groups[groups.length - 1];

        if (group === undefined || group.day !== day) {
            group = {
                day,
                relative:
                    day === today
                        ? 'today'
                        : day === yesterday
                          ? 'yesterday'
                          : null,
                items: [],
            };
            groups.push(group);
        }

        group.items.push(item);
    }

    return groups;
}
