<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';
import { format, isToday } from 'date-fns';

/**
 * Right side of the chat page: one conversation's messages and the box to write a new one.
 */
const props = defineProps({
    conversation: { type: Object, required: true },
});

const form = useForm({ body: '' });
const messageList = ref(null);

const formatDate = (date) => {
    const value = new Date(date);
    return isToday(value) ? format(value, 'HH:mm') : format(value, 'dd MMM, HH:mm');
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messageList.value) {
            messageList.value.scrollTop = messageList.value.scrollHeight;
        }
    });
};

onMounted(scrollToBottom);
watch(() => props.conversation.messages.length, scrollToBottom);

const send = () => {
    if (form.processing || form.body.trim() === '') return;

    form.post(route('conversation.messages.store', props.conversation.uuid), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};
</script>

<template>
    <div class="h-full flex flex-col">
        <div class="flex items-center border-b border-gray-200 px-5 py-4">
            <Link :href="route('conversation.index')" class="lg:hidden mr-3 text-theme-1" aria-label="Back to conversations">
                &larr;
            </Link>
            <div class="overflow-hidden">
                <div class="font-medium text-base truncate">{{ conversation.title }}</div>
                <div class="text-gray-600 text-xs sm:text-sm truncate">
                    {{ conversation.users.map((user) => user.name).join(', ') }}
                </div>
            </div>
        </div>

        <div ref="messageList" class="overflow-y-auto px-5 pt-5 flex-1">
            <p v-if="conversation.messages.length === 0" class="text-gray-600 text-center mt-10">
                No messages yet. Say hello.
            </p>

            <template v-for="message in conversation.messages" :key="message.id">
                <div class="chat__box__text-box flex items-end mb-4"
                    :class="message.is_own ? 'float-right' : 'float-left'">
                    <div class="px-4 py-3"
                        :class="message.is_own
                            ? 'bg-theme-1 text-white rounded-l-md rounded-t-md'
                            : 'bg-gray-200 text-gray-700 rounded-r-md rounded-t-md'">
                        <div v-if="!message.is_own && conversation.users.length > 2" class="text-xs font-medium mb-1">
                            {{ message.user_name }}
                        </div>
                        <div class="whitespace-pre-wrap break-words">{{ message.body }}</div>
                        <div class="mt-1 text-xs" :class="message.is_own ? 'text-theme-25' : 'text-gray-600'">
                            {{ formatDate(message.created_at) }}
                        </div>
                    </div>
                </div>
                <div class="clear-both"></div>
            </template>
        </div>

        <form class="pt-4 pb-10 sm:py-4 flex items-center border-t border-gray-200" @submit.prevent="send">
            <label class="sr-only" for="message-body">Message</label>
            <textarea id="message-body" v-model="form.body"
                class="chat__box__input input w-full h-16 resize-none border-transparent px-5 py-3 focus:shadow-none"
                rows="1" placeholder="Type your message..." @keydown.enter.exact.prevent="send"></textarea>
            <button type="submit"
                class="w-8 h-8 sm:w-10 sm:h-10 block bg-theme-1 text-white rounded-full flex-none flex items-center justify-center mr-5"
                :disabled="form.processing || form.body.trim() === ''"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing || form.body.trim() === '' }"
                aria-label="Send">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </form>
        <p v-if="form.errors.body" class="text-theme-6 px-5 pb-3">{{ form.errors.body }}</p>
    </div>
</template>
