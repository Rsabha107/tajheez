<script setup>
import { Head } from "@inertiajs/vue3";
import AppShell from "@/Components/AppShell.vue";
import EventBtTable from "@/Components/ems/EventBtTable.vue";
</script>

<template>
    <Head title="Events" />
    <AppShell page-title="Events" active-section="events">
        <EventBtTable />
    </AppShell>
</template>
