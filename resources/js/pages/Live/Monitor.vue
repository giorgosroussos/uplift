<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import HostSession from '@/components/live/HostSession.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show as showActivity } from '@/routes/activities';
import { open as openRoute } from '@/routes/live/sessions';
import type { LiveMonitorPage } from '@/types/live';

/*
 * UC-024: the teacher's monitor page of a live quiz. Without a session
 * (never opened, or the lobby was closed without starting) the teacher
 * opens the lobby; the session's host then sees the lobby, the live
 * progress and the results (HostSession, re-mounted per session so it
 * subscribes to that session's channels). Refusals (e.g. the quiz was
 * already run, SCOPE-019) come back as the "session" error.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'activities.index.title', href: index() }],
    },
});

const props = defineProps<LiveMonitorPage>();

const page = usePage();
const refusal = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.session,
);
const opening = ref(false);

function formatTime(value: string): string {
    return new Date(value).toLocaleString(
        document.documentElement.lang || undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    );
}

function openLobby(): void {
    opening.value = true;
    router.post(
        openRoute(props.activity.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                opening.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="activity.title" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-medium" data-test="live-title">
                    {{ activity.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{{
                        $t('live.monitor.title')
                    }}</Badge>
                </div>
            </div>
            <Button variant="outline" as-child>
                <Link :href="showActivity(activity.id)" data-test="live-back">
                    {{ $t('live.monitor.back') }}
                </Link>
            </Button>
        </div>

        <dl class="grid gap-3 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="text-muted-foreground">
                {{ $t('live.monitor.course_space') }}
            </dt>
            <dd>{{ activity.course_space_title }}</dd>
            <dt class="text-muted-foreground">
                {{ $t('live.monitor.duration') }}
            </dt>
            <dd>
                {{
                    $t('live.monitor.duration_minutes', {
                        minutes: String(Math.round(quiz.duration_seconds / 60)),
                    })
                }}
            </dd>
            <template v-if="quiz.start_mode">
                <dt class="text-muted-foreground">
                    {{ $t('live.monitor.start_mode') }}
                </dt>
                <dd data-test="live-start-mode">
                    {{
                        $t(`live.monitor.start_modes.${quiz.start_mode}`, {
                            time: quiz.scheduled_start_at
                                ? formatTime(quiz.scheduled_start_at)
                                : '',
                        })
                    }}
                </dd>
            </template>
        </dl>

        <Alert v-if="refusal" variant="destructive" data-test="live-refusal">
            <AlertDescription>{{ refusal }}</AlertDescription>
        </Alert>

        <section
            v-if="!session"
            class="flex flex-col items-start gap-3"
            data-test="live-no-session"
        >
            <p class="text-sm">
                {{
                    quiz.start_mode === 'scheduled'
                        ? $t('live.monitor.no_session_scheduled')
                        : $t('live.monitor.no_session')
                }}
            </p>
            <Button
                :disabled="opening"
                data-test="live-open-lobby"
                @click="openLobby"
            >
                {{ $t('live.monitor.open_lobby') }}
            </Button>
        </section>

        <Alert
            v-else-if="!session.hosting || !state"
            data-test="live-hosted-elsewhere"
        >
            <AlertDescription>{{
                $t('live.monitor.hosted_elsewhere')
            }}</AlertDescription>
        </Alert>

        <HostSession
            v-else
            :key="session.id"
            :initial="state"
            :start-mode="quiz.start_mode"
            :scheduled-start-at="quiz.scheduled_start_at"
            :questions="questions"
        />
    </div>
</template>
