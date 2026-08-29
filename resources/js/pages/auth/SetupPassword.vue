<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    token: string;
    email: string;
    submitUrl: string;
    user: { name: string; email: string; role: string | null };
}>();

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام',
    dept_manager: 'مدير القسم',
    supervisor: 'مشرف',
    dept_staff: 'موظف القسم',
    viewer: 'مشاهد',
};

const form = useForm({
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(props.submitUrl, { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthBase title="إنشاء كلمة المرور" description="عيّن كلمة مرور لحسابك لإكمال التسجيل">
        <Head title="إنشاء كلمة المرور" />

        <div class="mb-4 rounded-lg border border-border bg-surface p-4 text-sm" dir="rtl">
            <p><span class="text-text-muted">الاسم:</span> <strong>{{ user.name }}</strong></p>
            <p><span class="text-text-muted">البريد الإلكتروني:</span> <strong>{{ user.email }}</strong></p>
            <p v-if="user.role"><span class="text-text-muted">الدور:</span> <strong>{{ roleLabels[user.role] ?? user.role }}</strong></p>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-2">
                <Label for="password">كلمة المرور</Label>
                <Input id="password" type="password" required autofocus autocomplete="new-password" v-model="form.password" class="text-right" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">تأكيد كلمة المرور</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    v-model="form.password_confirmation"
                    class="text-right"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                إنشاء الحساب
            </Button>
        </form>
    </AuthBase>
</template>
