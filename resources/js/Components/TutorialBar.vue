<script setup>
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// The step-by-step tutorial's bar on Inertia pages (ADR 0040). The shared
// `tutorial` prop comes from the Tutorial module and is null outside
// tutorial mode; while it is set the bar reloads it to tick missions.
const page = usePage();
const guide = computed(() => page.props.tutorial ?? null);
const open = ref(true);
const justDone = ref(false);

const { start, stop } = usePoll(8000, { only: ['tutorial'] }, { autoStart: false });

watch(
    guide,
    (current, previous) => {
        current ? start() : stop();
        justDone.value = Boolean(current && previous && current.done > previous.done);
    },
    { immediate: true },
);

function post(url) {
    router.post(url);
}
</script>

<template>
    <div v-if="guide" class="border-b border-violet-200 bg-violet-50 px-4 py-2 text-sm text-violet-950">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-2">
            <span class="font-semibold">آموزش «{{ guide.scenario }}» · {{ Math.min(guide.done + 1, guide.total) }} از {{ guide.total }}</span>
            <span v-if="guide.finished">همه‌ی گام‌ها انجام شد.</span>
            <template v-else>
                <span>{{ guide.mission.title }} <span class="text-xs opacity-75">({{ guide.mission.actor }})</span></span>
                <button type="button" class="text-xs underline" @click="open = !open">{{ open ? 'بستن' : 'جزئیات' }}</button>
            </template>
            <span v-if="justDone" class="rounded-md bg-emerald-500 px-2 py-0.5 text-xs text-white">گام انجام شد. گام بعدی را ببینید.</span>

            <div class="ms-auto flex flex-wrap items-center gap-2">
                <a
                    v-if="!guide.finished && guide.mission.url"
                    :href="guide.mission.url"
                    class="rounded-md bg-violet-600 px-3 py-1 text-xs font-medium text-white hover:bg-violet-700"
                >{{ guide.mission.urlLabel }}</a>
                <button
                    v-if="guide.actAs"
                    type="button"
                    class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600"
                    @click="post(guide.actAs.url)"
                >ورود با حساب {{ guide.actAs.name }}</button>
                <button
                    v-if="guide.ownAccountUrl"
                    type="button"
                    class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600"
                    @click="post(guide.ownAccountUrl)"
                >بازگشت به حساب خودم</button>
                <a v-if="!page.props.auth.impersonator" :href="guide.pageUrl" class="text-xs underline">همه‌ی گام‌ها</a>
            </div>
        </div>
        <ul v-if="!guide.finished && open" class="mx-auto mt-2 max-w-5xl list-disc ps-6 text-xs leading-6">
            <li v-for="step in guide.mission.steps" :key="step">{{ step }}</li>
        </ul>
    </div>
</template>
