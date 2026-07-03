<script setup lang="ts">
defineProps<{
    size?: 'sm' | 'md' | 'lg'
}>();
</script>

<template>
    <div class="flex items-center gap-3" :class="size === 'sm' ? 'gap-2' : size === 'lg' ? 'gap-4' : 'gap-3'">
        <!-- Replace src with actual logo image path when available -->
        <div
            class="rounded-lg bg-primary-dark flex items-center justify-center flex-shrink-0"
            :class="{
                'w-8 h-8': size === 'sm',
                'w-10 h-10': size === 'md' || !size,
                'w-14 h-14': size === 'lg',
            }"
        >
            <!-- Placeholder initials until real logo image is provided -->
            <span
                class="font-display font-bold text-white leading-none"
                :class="{
                    'text-xs': size === 'sm',
                    'text-sm': size === 'md' || !size,
                    'text-xl': size === 'lg',
                }"
            >ك.ت</span>
        </div>

        <div class="flex flex-col leading-tight">
            <span
                class="font-display font-bold text-primary-dark"
                :class="{
                    'text-sm': size === 'sm',
                    'text-base': size === 'md' || !size,
                    'text-xl': size === 'lg',
                }"
            >كلية التقنية الإلكترونية</span>
            <span
                v-if="size !== 'sm'"
                class="font-body text-text-muted"
                :class="{
                    'text-xs': size === 'md' || !size,
                    'text-sm': size === 'lg',
                }"
            >نظام أرشفة مشاريع التخرج</span>
        </div>
    </div>
</template>
