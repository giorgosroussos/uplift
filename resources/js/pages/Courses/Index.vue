<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { catalogue, create, index, show } from '@/routes/courses';
import type { Auth } from '@/types';
import type { CourseSpaceSummary } from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'courses.nav.courses', href: index() }],
    },
});

defineProps<{
    spaces: CourseSpaceSummary[];
    canCreate: boolean;
    canBrowseCatalogue: boolean;
}>();

const page = usePage<{ auth: Auth }>();
const role = computed(() => page.props.auth.user.role);
</script>

<template>
    <Head :title="$t('courses.index.title')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-xl font-medium" data-test="courses-heading">
                {{ $t(`courses.index.heading_${role}`) }}
            </h1>
            <div class="flex gap-2">
                <Button v-if="canBrowseCatalogue" variant="outline" as-child>
                    <Link :href="catalogue()" data-test="browse-catalogue">
                        {{ $t('courses.index.browse') }}
                    </Link>
                </Button>
                <Button v-if="canCreate" as-child>
                    <Link :href="create()" data-test="create-course">
                        {{ $t('courses.index.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="spaces.length === 0"
            class="text-sm text-muted-foreground"
            data-test="courses-empty"
        >
            {{ $t(`courses.index.empty_${role}`) }}
        </p>

        <ul v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="space in spaces"
                :key="space.id"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                data-test="course-space"
            >
                <Link
                    :href="show(space.id)"
                    class="font-medium hover:underline"
                    data-test="course-space-link"
                >
                    {{ space.title }}
                </Link>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span>{{ space.teacher }}</span>
                    <Badge
                        :variant="
                            space.status === 'active' ? 'secondary' : 'outline'
                        "
                    >
                        {{ $t(`courses.show.status_${space.status}`) }}
                    </Badge>
                    <span v-if="space.enrolments_count !== null">
                        {{ $t('courses.show.enrolments_count') }}:
                        {{ space.enrolments_count }}
                    </span>
                </div>
            </li>
        </ul>
    </div>
</template>
