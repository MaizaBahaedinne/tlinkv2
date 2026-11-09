<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Partner = { id: number; name: string; logo_url: string; url: string | null };

const props = defineProps<{ partners: Partner[]; perPage?: number; interval?: number }>();

const size = computed(() => Math.max(1, props.perPage ?? 2));
const pages = computed(() => {
  const chunks: Partner[][] = [];
  for (let i = 0; i < props.partners.length; i += size.value) chunks.push(props.partners.slice(i, i + size.value));
  return chunks;
});

// Une page de plus à la fin (copie de la première) pour que le défilement boucle sans retour en arrière.
const slides = computed(() => (pages.value.length > 1 ? [...pages.value, pages.value[0]] : pages.value));

const index = ref(0);
const animate = ref(true);
const paused = ref(false);
let timer: number | undefined;

const activeDot = computed(() => index.value % Math.max(1, pages.value.length));
const reducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function next() {
  if (paused.value || document.hidden || pages.value.length < 2) return;
  animate.value = true;
  index.value += 1;
}

function onTransitionEnd() {
  if (index.value >= pages.value.length) {
    animate.value = false;
    index.value = 0;
  }
}

function go(position: number) {
  animate.value = true;
  index.value = position;
}

onMounted(() => {
  if (!reducedMotion) timer = window.setInterval(next, props.interval ?? 3200);
});

onBeforeUnmount(() => {
  if (timer !== undefined) window.clearInterval(timer);
});
</script>

<template>
  <section v-if="partners.length" class="card p-0" aria-roledescription="carrousel" aria-label="Partenaires" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
    <div class="flex items-center justify-between border-b-2 border-slate-900 bg-slate-900 px-4 py-2">
      <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-white">Partenaires</p>
      <span class="h-2 w-6 bg-tlink-blue" aria-hidden="true"></span>
    </div>

    <div class="overflow-hidden">
      <div
        class="flex"
        :class="animate ? 'transition-transform duration-700 ease-in-out' : ''"
        :style="{ transform: `translateX(-${index * 100}%)` }"
        @transitionend="onTransitionEnd"
      >
        <ul
          v-for="(page, pageIndex) in slides"
          :key="pageIndex"
          class="grid w-full shrink-0 gap-3 p-4"
          :style="{ gridTemplateColumns: `repeat(${size}, minmax(0, 1fr))` }"
          :aria-hidden="pageIndex !== activeDot && !(pageIndex === pages.length && activeDot === 0) ? 'true' : undefined"
        >
          <li v-for="partner in page" :key="partner.id">
            <a
              v-if="partner.url"
              :href="partner.url"
              target="_blank"
              rel="noopener noreferrer"
              :title="partner.name"
              class="flex h-20 items-center justify-center border border-slate-300 bg-white p-2 transition hover:border-tlink-blue"
            >
              <img :src="partner.logo_url" :alt="partner.name" loading="lazy" class="max-h-full max-w-full object-contain" />
            </a>
            <span v-else :title="partner.name" class="flex h-20 items-center justify-center border border-slate-300 bg-white p-2">
              <img :src="partner.logo_url" :alt="partner.name" loading="lazy" class="max-h-full max-w-full object-contain" />
            </span>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="pages.length > 1" class="flex justify-center gap-1.5 pb-3">
      <button
        v-for="(_, dot) in pages"
        :key="dot"
        type="button"
        class="h-1.5 transition-all"
        :class="dot === activeDot ? 'w-6 bg-tlink-blue' : 'w-3 bg-slate-300 hover:bg-slate-400'"
        :aria-label="`Partenaires, page ${dot + 1}`"
        :aria-current="dot === activeDot ? 'true' : undefined"
        @click="go(dot)"
      ></button>
    </div>
  </section>
</template>
