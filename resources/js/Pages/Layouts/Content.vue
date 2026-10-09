<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { format, isToday } from 'date-fns';

/**
 * Left side of the chat page: the "new chat" form and the list of conversations.
 */
const props = defineProps({
    conversations: { type: Array, required: true },
    users: { type: Array, required: true },
    activeUuid: { type: String, default: null },
});

const startingChat = ref(false);
const search = ref('');
const form = useForm({ user_ids: [], name: '' });

const matchingUsers = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term ? props.users.filter((user) => user.name.toLowerCase().includes(term)) : props.users;
});

const formatDate = (date) => {
    if (!date) return '';
    const value = new Date(date);
    return isToday(value) ? format(value, 'HH:mm') : format(value, 'dd MMM');
};

const initials = (title) => title.slice(0, 2).toUpperCase();

const cancel = () => {
    startingChat.value = false;
    search.value = '';
    form.reset();
    form.clearErrors();
};

const submit = () => {
    form.post(route('conversation.store'), { onSuccess: cancel });
};
</script>

<template>
    <div>
        <button v-if="!startingChat" type="button" class="button w-full text-white bg-theme-1 shadow-md"
            @click="startingChat = true">
            Start New Chat
        </button>

        <form v-else class="box p-5" @submit.prevent="submit">
            <div class="font-medium text-base">New chat</div>

            <p v-if="users.length === 0" class="text-gray-600 mt-3">
                There is nobody else to chat with yet.
            </p>
            <template v-else>
                <label class="sr-only" for="user-search">Search people</label>
                <input id="user-search" v-model="search" type="text" class="input w-full border mt-3"
                    placeholder="Search people" />

                <div class="mt-3 overflow-y-auto" style="max-height: 14rem">
                    <label v-for="user in matchingUsers" :key="user.id"
                        class="flex items-center py-2 cursor-pointer">
                        <input v-model="form.user_ids" type="checkbox" class="input border mr-3" :value="user.id" />
                        <span>{{ user.name }}</span>
                    </label>
                    <p v-if="matchingUsers.length === 0" class="text-gray-600 py-2">No one matches.</p>
                </div>

                <div v-if="form.user_ids.length > 1" class="mt-3">
                    <label class="sr-only" for="group-name">Group name</label>
                    <input id="group-name" v-model="form.name" type="text" class="input w-full border"
                        placeholder="Group name (optional)" />
                </div>
            </template>

            <p v-if="form.errors.user_ids" class="text-theme-6 mt-2">{{ form.errors.user_ids }}</p>
            <p v-if="form.errors.name" class="text-theme-6 mt-2">{{ form.errors.name }}</p>

            <div class="flex mt-4">
                <button type="submit" class="button text-white bg-theme-1 mr-2"
                    :disabled="form.processing || form.user_ids.length === 0"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing || form.user_ids.length === 0 }">
                    Start chat
                </button>
                <button type="button" class="button border text-gray-700" @click="cancel">Cancel</button>
            </div>
        </form>

        <div class="mt-4">
            <p v-if="conversations.length === 0" class="box p-5 text-gray-600">
                No conversations yet. Start a new chat to send your first message.
            </p>

            <Link v-for="item in conversations" :key="item.uuid" :href="route('conversation.show', item.uuid)"
                class="box relative flex items-center p-5 mb-4"
                :class="{ 'border-2 border-theme-1': item.uuid === activeUuid }"
                :aria-current="item.uuid === activeUuid ? 'page' : null">
                <div class="w-12 h-12 flex-none rounded-full flex items-center justify-center font-bold"
                    style="background-color: #ffdada">
                    {{ initials(item.title) }}
                </div>
                <div class="ml-3 overflow-hidden flex-1">
                    <div class="flex items-center">
                        <span class="font-medium truncate">{{ item.title }}</span>
                        <span v-if="item.last_message" class="text-xs text-gray-500 ml-auto pl-2 whitespace-no-wrap">
                            {{ formatDate(item.last_message.created_at) }}
                        </span>
                    </div>
                    <div v-if="item.last_message" class="w-full truncate text-gray-600 mt-1">
                        {{ item.last_message.user_name }}: {{ item.last_message.body }}
                    </div>
                    <div v-else class="text-gray-600 mt-1">No messages yet.</div>
                </div>
                <div v-if="item.unread_count > 0"
                    class="w-5 h-5 flex items-center justify-center absolute top-0 right-0 text-xs text-white rounded-full bg-theme-1 font-medium -mt-1 -mr-1"
                    :aria-label="`${item.unread_count} unread`">
                    {{ item.unread_count }}
                </div>
            </Link>
        </div>
    </div>
</template>
