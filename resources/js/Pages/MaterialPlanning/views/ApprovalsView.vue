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
    functionalAreas: { type: Array, default: () => [] },
    refreshing: { type: Boolean, default: false },
    showItemValues: { type: Boolean, default: false },
    event:    { type: Object, default: null },
});

const emit = defineEmits(['open-request', 'go-to', 'requests-deleted', 'refresh']);
</script>

<template>
    <RequestsView
        :requests="requests"
        :domains="domains"
        :venues="venues"
        :statuses="statuses"
        :people="people"
        :functional-areas="functionalAreas"
        :refreshing="refreshing"
        :show-item-values="showItemValues"
        :event="event"
        :approval-only="true"
        @open-request="emit('open-request', $event)"
        @go-to="emit('go-to', $event)"
        @requests-deleted="emit('requests-deleted')"
        @refresh="emit('refresh')"
    />
</template>
