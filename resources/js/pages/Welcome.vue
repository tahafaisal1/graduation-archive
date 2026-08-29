<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Logo from '@/components/Brand/Logo.vue';

const props = defineProps<{
    auth?: { user?: { name: string } | null };
    stats?: { total_projects: number; total_departments: number; total_specializations: number };
}>();

const statsRows = computed(() => [
    { value: props.stats?.total_projects ?? 0, label: 'مشروع مؤرشف' },
    { value: props.stats?.total_departments ?? 0, label: 'قسم أكاديمي' },
    { value: props.stats?.total_specializations ?? 0, label: 'تخصص' },
]);

const features = [
    {
        title: 'أرشفة منظمة',
        desc: 'حفظ مشاريع التخرج بطريقة منظمة ومصنفة حسب القسم والتخصص والعام الدراسي.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>`,
    },
    {
        title: 'بحث متقدم',
        desc: 'البحث السريع في الأرشيف بحسب العنوان أو اسم المشرف أو التخصص أو السنة.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>`,
    },
    {
        title: 'تقارير وإحصائيات',
        desc: 'لوحة تحكم تفاعلية مع تقارير تفصيلية للمشاريع والأقسام والمشرفين.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>`,
    },
    {
        title: 'استيراد جماعي',
        desc: 'رفع وإدارة مشاريع متعددة دفعة واحدة عبر ملفات Excel مع دعم ملفات PDF.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>`,
    },
];
</script>

<template>
    <Head title="كلية التقنية الإلكترونية — نظام أرشفة المشاريع" />

    <div class="min-h-screen bg-background font-body text-text-dark" dir="rtl">

        <!-- HEADER -->
        <header class="sticky top-0 z-50 bg-surface border-b border-border shadow-sm">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <Logo size="md" />
                <div class="flex items-center gap-3">
                    <Link
                        v-if="auth?.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-primary text-white font-body text-sm font-medium hover:bg-primary-dark transition-colors"
                    >
                        لوحة التحكم
                    </Link>
                </div>
            </div>
        </header>

        <!-- HERO -->
        <section class="relative overflow-hidden bg-primary-dark">
            <!-- Subtle dot grid -->
            <div
                class="absolute inset-0 opacity-10"
                style="background-image: radial-gradient(circle, rgba(79,168,201,0.6) 1px, transparent 1px); background-size: 28px 28px;"
            ></div>

            <div class="relative max-w-6xl mx-auto px-6 py-24 text-center">
                <!-- College badge -->
                <div class="inline-flex items-center gap-2 bg-primary/30 border border-primary-light/40 text-primary-light rounded-full px-4 py-1.5 text-sm font-body mb-8">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-light"></span>
                    كلية التقنية الإلكترونية — طرابلس
                </div>

                <!-- Main heading -->
                <h1 class="font-display font-bold text-white leading-tight mb-6" style="font-size: clamp(1.8rem, 4.5vw, 3.2rem);">
                    نظام أرشفة وتصنيف<br>مشاريع التخرج
                </h1>

                <!-- Description -->
                <p class="font-body text-primary-light/85 text-lg mb-10 max-w-2xl mx-auto leading-relaxed">
                    منصة إلكترونية متكاملة لحفظ وأرشفة وتصنيف مشاريع التخرج بطريقة منظمة وسهلة الوصول،
                    مع إمكانية البحث المتقدم وإصدار التقارير.
                </p>

                <!-- CTA buttons -->
                <div class="flex items-center justify-center flex-wrap gap-4">
                    <Link
                        :href="route('public.browse')"
                        class="inline-flex items-center gap-2 px-7 py-3 rounded-lg bg-primary-light text-primary-dark font-body font-semibold text-base hover:bg-white transition-colors shadow-lg"
                    >
                        تصفح المشاريع
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rotate-180 shrink-0"><path d="m9 18 6-6-6-6"/></svg>
                    </Link>
                </div>

                <!-- Stats -->
                <div class="mt-16 grid grid-cols-3 gap-4 max-w-lg mx-auto">
                    <div
                        v-for="stat in statsRows"
                        :key="stat.label"
                        class="bg-white/10 border border-white/15 rounded-xl py-5 px-3"
                    >
                        <div class="font-display font-bold text-3xl text-white mb-1">{{ stat.value }}</div>
                        <div class="font-body text-xs text-primary-light/75 leading-tight">{{ stat.label }}</div>
                    </div>
                </div>
            </div>

            <!-- Wave transition -->
            <svg viewBox="0 0 1440 48" preserveAspectRatio="none" class="w-full block fill-current text-background -mb-px" style="height:48px;">
                <path d="M0,48 C360,0 1080,48 1440,0 L1440,48 L0,48 Z"/>
            </svg>
        </section>

        <!-- FEATURES -->
        <section id="features" class="py-20 bg-background">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-14">
                    <h2 class="font-display font-bold text-2xl text-text-dark mb-3">ما يقدمه النظام</h2>
                    <p class="font-body text-text-muted max-w-lg mx-auto leading-relaxed">
                        أدوات متكاملة تُمكّن الكلية من إدارة مشاريع التخرج بكفاءة عالية واحترافية
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div
                        v-for="feat in features"
                        :key="feat.title"
                        class="bg-surface border border-border rounded-xl p-6 hover:border-primary/40 hover:shadow-md transition-all duration-200 group"
                    >
                        <div
                            class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-4 group-hover:bg-primary group-hover:text-white transition-colors shrink-0"
                            v-html="feat.icon"
                        ></div>
                        <h3 class="font-display font-bold text-base text-text-dark mb-2">{{ feat.title }}</h3>
                        <p class="font-body text-sm text-text-muted leading-relaxed">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA BAND -->
        <section class="bg-primary py-14">
            <div class="max-w-xl mx-auto px-6 text-center">
                <h2 class="font-display font-bold text-2xl text-white mb-3">تصفّح أرشيف المشاريع</h2>
                <p class="font-body text-white/80 mb-8 leading-relaxed">
                    استعرض مشاريع التخرج المؤرشفة وابحث فيها بحسب القسم أو التخصص أو العام الدراسي.
                </p>
                <Link
                    :href="route('public.browse')"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-lg bg-white text-primary font-body font-semibold text-base hover:bg-background transition-colors shadow-md"
                >
                    تصفح المشاريع
                </Link>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="bg-primary-dark py-8 border-t border-primary/20">
            <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <Logo size="sm" />
                <p class="font-body text-sm text-primary-light/50">
                    جميع الحقوق محفوظة &copy; {{ new Date().getFullYear() }} — كلية التقنية الإلكترونية
                </p>
            </div>
        </footer>

    </div>
</template>
