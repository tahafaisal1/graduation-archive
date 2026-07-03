<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    suggestions?: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'search': [value: string];
    'select': [value: string];
}>();

const showDropdown = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

function onInput(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    emit('update:modelValue', val);
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => emit('search', val), 300);
    showDropdown.value = true;
}

function onEnter() {
    if (timer) clearTimeout(timer);
    emit('search', props.modelValue);
    showDropdown.value = false;
}

function selectSuggestion(s: string) {
    emit('update:modelValue', s);
    emit('select', s);
    showDropdown.value = false;
}

function clear() {
    if (timer) clearTimeout(timer);
    emit('update:modelValue', '');
    emit('search', '');
    showDropdown.value = false;
}

function onFocus() {
    if (props.suggestions && props.suggestions.length > 0) {
        showDropdown.value = true;
    }
}

function onBlur() {
    setTimeout(() => { showDropdown.value = false; }, 200);
}

watch(() => props.suggestions, (val) => {
    if (val && val.length > 0) showDropdown.value = true;
});
</script>

<template>
    <div class="relative w-full" dir="rtl">
        <div class="relative flex items-center">
            <span class="pointer-events-none absolute right-3 text-gray-400 text-sm">🔍</span>
            <input
                :value="modelValue"
                :placeholder="placeholder ?? 'بحث...'"
                type="text"
                class="w-full rounded-lg border border-gray-300 py-2 pr-9 pl-9 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                @input="onInput"
                @keydown.enter.prevent="onEnter"
                @focus="onFocus"
                @blur="onBlur"
            />
            <button
                v-if="modelValue"
                type="button"
                class="absolute left-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                tabindex="-1"
                @mousedown.prevent="clear"
            >
                ✕
            </button>
        </div>

        <ul
            v-if="showDropdown && suggestions && suggestions.length > 0"
            class="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <li
                v-for="s in suggestions"
                :key="s"
                class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 dark:text-gray-300 dark:hover:bg-blue-900/20"
                @mousedown.prevent="selectSuggestion(s)"
            >
                {{ s }}
            </li>
        </ul>
    </div>
</template>
