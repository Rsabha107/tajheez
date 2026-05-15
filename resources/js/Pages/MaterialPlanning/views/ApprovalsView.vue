<script setup>
import RequestsView from './RequestsView.vue';

// ApprovalsView is RequestsView with approvalOnly=true.
// We re-export its props (minus approvalOnly) and forward events.
const props = defineProps({
    requests: Array,
    domains:  Array,
    venues:   Array,
    statuses: Object,
    people:   Array,
});

const emit = defineEmits(['open-request', 'go-to']);
</script>

<template>
    <RequestsView
        :requests="requests"
        :domains="domains"
        :venues="venues"
        :statuses="statuses"
        :people="people"
        :approval-only="true"
        @open-request="emit('open-request', $event)"
        @go-to="emit('go-to', $event)"
    />
</template>
