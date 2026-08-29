<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase title="رابط غير صالح" description="تعذّر فتح رابط الدعوة">
        <Head title="رابط غير صالح" />
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" dir="rtl">
            انتهت صلاحية الرابط أو استُخدم من قبل. اطلب من المدير إعادة إرسال دعوة جديدة.
        </div>
        <div class="mt-4 text-center text-sm">
            <TextLink :href="route('login')">العودة لتسجيل الدخول</TextLink>
        </div>
    </AuthBase>
</template>
