<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConversationList from '../Layouts/Content.vue';
import ShowConversation from './ShowConversation.vue';
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    conversations: { type: Array, required: true },
    conversation: { type: Object, default: null },
    users: { type: Array, required: true },
});

// New messages are picked up by asking the server again every few seconds.
const REFRESH_EVERY_MS = 5000;
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            router.reload({ only: ['conversations', 'conversation'] });
        }
    }, REFRESH_EVERY_MS);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head :title="conversation ? conversation.title : 'Chat'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Chat</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-12 gap-5">
                    <!-- On small screens the list and the open conversation take turns -->
                    <div class="col-span-12 lg:col-span-4" :class="{ 'hidden lg:block': conversation }">
                        <ConversationList :conversations="conversations" :users="users"
                            :active-uuid="conversation ? conversation.uuid : null" />
                    </div>

                    <div class="col-span-12 lg:col-span-8" :class="{ 'hidden lg:block': !conversation }">
                        <div class="box" style="height: 70vh; min-height: 24rem">
                            <ShowConversation v-if="conversation" :key="conversation.uuid"
                                :conversation="conversation" />
                            <div v-else class="h-full flex items-center">
                                <div class="mx-auto text-center px-5">
                                    <div class="font-medium">Hey, {{ $page.props.auth.user.name }}!</div>
                                    <div class="text-gray-600 mt-1">Please select a chat to start messaging.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
