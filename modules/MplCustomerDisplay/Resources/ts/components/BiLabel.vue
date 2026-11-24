<script setup lang="ts">
/**
 * English label (translatable) with its fixed Arabic counterpart rendered RTL.
 */
withDefaults(defineProps<{
    en: string;
    ar: string;
    stacked?: boolean;
}>(), {
    stacked: true,
});
</script>

<template>
    <span :class="stacked ? 'mplcd:flex mplcd:flex-col mplcd:leading-tight' : 'mplcd:inline-flex mplcd:items-baseline mplcd:gap-2'">
        <span>{{ en }}</span>
        <span class="mplcd-arabic mplcd:opacity-80" dir="rtl" lang="ar">{{ ar }}</span>
    </span>
</template>
