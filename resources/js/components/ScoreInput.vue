<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    projectId: number;
    currentScore: string | null;
}>();

defineEmits<{
    'score-updated': [];
}>();

const form = useForm({
    final_score: props.currentScore !== null ? Number(props.currentScore) : ('' as number | ''),
});

// Standard graduation passing threshold
const PASS_THRESHOLD = 50;

const savedScore = computed(() => {
    if (props.currentScore === null || props.currentScore === '') return null;
    const n = Number(props.currentScore);
    return isNaN(n) ? null : n;
});

const isPass = computed(() => savedScore.value !== null && savedScore.value >= PASS_THRESHOLD);

function submit() {
    form.patch(route('projects.score', [props.projectId]));
}
</script>

<template>
    <div>
        <!-- Current score display -->
        <div v-if="savedScore !== null" class="mb-4 flex items-center gap-3">
            <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ savedScore }}</span>
            <span
                :class="[
                    'rounded-full px-3 py-1 text-sm font-medium',
                    isPass
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                        : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
                ]"
            >
                {{ isPass ? 'ناجح' : 'راسب' }}
            </span>
        </div>
        <p v-else class="mb-4 text-sm text-gray-500">لم تُسجَّل درجة بعد</p>

        <!-- Input form -->
        <form class="flex items-start gap-2" @submit.prevent="submit">
            <div class="flex-1">
                <input
                    v-model.number="form.final_score"
                    type="number"
                    min="0"
                    max="100"
                    step="0.5"
                    placeholder="0 – 100"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    :class="{ 'border-red-500': form.errors.final_score }"
                />
                <p v-if="form.errors.final_score" class="mt-1 text-xs text-red-600">{{ form.errors.final_score }}</p>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
                {{ savedScore !== null ? 'تحديث' : 'حفظ' }}
            </button>
        </form>
    </div>
</template>
