<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { avatarUrl } from '@/lib/avatars';
import type { LivePublicProfile } from '@/types/live';

// Nickname and avatar only (SEC-008); without an avatar the initials.
const props = defineProps<{ student: LivePublicProfile }>();

const src = computed(() => avatarUrl(props.student.avatar));
</script>

<template>
    <Avatar class="h-8 w-8 shrink-0 overflow-hidden rounded-full">
        <AvatarImage v-if="src" :src="src" :alt="student.nickname" />
        <AvatarFallback class="rounded-full text-xs">
            {{ getInitials(student.nickname) }}
        </AvatarFallback>
    </Avatar>
</template>
