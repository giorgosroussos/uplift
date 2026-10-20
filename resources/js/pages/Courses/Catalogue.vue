<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { catalogue, show } from '@/routes/courses';
import type { CatalogueEntry, Paginated } from '@/types/courses';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'courses.nav.catalogue', href: catalogue() }],
    },
});

const props = defineProps<{
    spaces: Paginated<CatalogueEntry>;
    teachers: { id: string; name: string }[];
    filters: { search: string; teacher: string };
}>();

const filters = reactive({ ...props.filters });

function apply(): void {
    const query: Record<string, string> = {};

    if (filters.search.trim() !== '') {
        query.search = filters.search.trim();
    }

    if (filters.teacher !== '') {
        query.teacher = filters.teacher;
    }

    router.get(catalogue({ query }).url, {}, { preserveState: true });
}
</script>

<template>
    <Head :title="$t('courses.catalogue.title')" />

    <div class="flex flex-col gap-6 p-4">
        <h1 class="text-xl font-medium">
            {{ $t('courses.catalogue.heading') }}
        </h1>

        <form
            class="flex flex-wrap items-end gap-4"
            role="search"
            @submit.prevent="apply"
        >
            <div class="grid gap-2">
                <Label for="search">{{ $t('courses.catalogue.search') }}</Label>
                <Input
                    id="search"
                    v-model="filters.search"
                    type="search"
                    :placeholder="$t('courses.catalogue.search_placeholder')"
                    data-test="catalogue-search"
                />
            </div>
            <div class="grid gap-2">
                <Label for="teacher">{{
                    $t('courses.catalogue.teacher')
                }}</Label>
                <select
                    id="teacher"
                    v-model="filters.teacher"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                    data-test="catalogue-teacher"
                >
                    <option value="">
                        {{ $t('courses.catalogue.all_teachers') }}
                    </option>
                    <option
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        :value="teacher.id"
                    >
                        {{ teacher.name }}
                    </option>
                </select>
            </div>
            <Button type="submit" data-test="catalogue-apply">
                {{ $t('courses.catalogue.apply') }}
            </Button>
        </form>

        <p
            v-if="spaces.data.length === 0"
            class="text-sm text-muted-foreground"
            data-test="catalogue-empty"
        >
            {{ $t('courses.catalogue.empty') }}
        </p>

        <ul v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="space in spaces.data"
                :key="space.id"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                data-test="catalogue-entry"
            >
                <Link
                    :href="show(space.id)"
                    class="font-medium hover:underline"
                >
                    {{ space.title }}
                </Link>
                <p
                    v-if="space.description"
                    class="mt-1 line-clamp-3 text-sm text-muted-foreground"
                >
                    {{ space.description }}
                </p>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span>{{ space.teacher }}</span>
                    <Badge variant="outline">
                        {{
                            $t(`courses.show.policy_${space.enrolment_policy}`)
                        }}
                    </Badge>
                    <Badge v-if="space.enrolled" variant="secondary">
                        {{ $t('courses.show.enrolled') }}
                    </Badge>
                </div>
            </li>
        </ul>

        <nav
            v-if="spaces.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Link
                v-if="spaces.prev_page_url"
                :href="spaces.prev_page_url"
                class="underline"
            >
                {{ $t('courses.catalogue.previous') }}
            </Link>
            <span v-else />
            <span>{{ spaces.current_page }} / {{ spaces.last_page }}</span>
            <Link
                v-if="spaces.next_page_url"
                :href="spaces.next_page_url"
                class="underline"
            >
                {{ $t('courses.catalogue.next') }}
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
