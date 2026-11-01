<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type StoryItem = {
  id: number;
  type: 'image' | 'video';
  url: string;
  caption: string | null;
  created_at: string | null;
  seen: boolean;
  views: number | null;
};

type StoryGroup = {
  user: { id: number; name: string; avatar_url: string | null; is_me: boolean };
  all_seen: boolean;
  items: StoryItem[];
};

const props = defineProps<{
  groups: StoryGroup[];
  csrf: string;
  meName: string;
  meAvatar?: string | null;
}>();

const IMAGE_DURATION = 5000;
const TICK = 50;
const MAX_IMAGE = 5 * 1024 * 1024;
const MAX_VIDEO = 20 * 1024 * 1024;
const ACCEPT = 'image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';

const groups = ref<StoryGroup[]>(props.groups.map((group) => ({ ...group, user: { ...group.user }, items: group.items.map((item) => ({ ...item })) })));

const myGroup = computed(() => groups.value.find((group) => group.user.is_me) ?? null);
const others = computed(() => groups.value.filter((group) => !group.user.is_me));

function initialsOf(name: string): string {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
}

function headers(): Record<string, string> {
  return { 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
}

function timeAgo(value: string | null): string {
  if (!value) return '';
  const minutes = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 60000));
  if (minutes < 1) return 'à l’instant';
  if (minutes < 60) return `il y a ${minutes} min`;
  return `il y a ${Math.floor(minutes / 60)} h`;
}

/* ---------- Visionneuse ---------- */

const position = ref<{ g: number; i: number } | null>(null);
const progress = ref(0);
const paused = ref(false);
const videoEl = ref<HTMLVideoElement | null>(null);
let elapsed = 0;
let timer: ReturnType<typeof setInterval> | null = null;
let holdAt = 0;
let heldLong = false;

const currentGroup = computed(() => (position.value ? groups.value[position.value.g] : null));
const current = computed(() => (position.value && currentGroup.value ? currentGroup.value.items[position.value.i] ?? null : null));

function openGroup(group: StoryGroup) {
  const g = groups.value.indexOf(group);
  if (g < 0 || group.items.length === 0) return;
  const firstUnseen = group.items.findIndex((item) => !item.seen);
  position.value = { g, i: firstUnseen >= 0 ? firstUnseen : 0 };
}

function close() {
  position.value = null;
}

function next() {
  if (!position.value || !currentGroup.value) return;
  const { g, i } = position.value;
  if (i < currentGroup.value.items.length - 1) {
    position.value = { g, i: i + 1 };
  } else if (g < groups.value.length - 1 && groups.value[g + 1].items.length > 0) {
    position.value = { g: g + 1, i: 0 };
  } else {
    close();
  }
}

function previous() {
  if (!position.value) return;
  const { g, i } = position.value;
  if (i > 0) {
    position.value = { g, i: i - 1 };
  } else if (g > 0 && groups.value[g - 1].items.length > 0) {
    position.value = { g: g - 1, i: groups.value[g - 1].items.length - 1 };
  } else {
    restart();
  }
}

function restart() {
  elapsed = 0;
  progress.value = 0;
  if (videoEl.value) videoEl.value.currentTime = 0;
}

function markSeen(group: StoryGroup, item: StoryItem) {
  if (group.user.is_me || item.seen) return;
  item.seen = true;
  group.all_seen = group.items.every((entry) => entry.seen);
  fetch(`/stories/${item.id}/vue`, { method: 'POST', headers: headers(), credentials: 'same-origin' }).catch(() => {
    /* la vue sera comptée à la prochaine ouverture */
  });
}

function startTimer() {
  stopTimer();
  timer = setInterval(() => {
    if (paused.value || !current.value || current.value.type === 'video') return;
    elapsed += TICK;
    progress.value = Math.min(1, elapsed / IMAGE_DURATION);
    if (elapsed >= IMAGE_DURATION) next();
  }, TICK);
}

function stopTimer() {
  if (timer) clearInterval(timer);
  timer = null;
}

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') close();
  else if (event.key === 'ArrowRight') next();
  else if (event.key === 'ArrowLeft') previous();
}

function onVideoTime() {
  const video = videoEl.value;
  if (video && video.duration > 0) progress.value = video.currentTime / video.duration;
}

function onDown() {
  holdAt = Date.now();
  paused.value = true;
}

function onUp() {
  heldLong = Date.now() - holdAt > 300;
  paused.value = false;
}

function tap(direction: 'next' | 'previous') {
  if (heldLong) {
    heldLong = false;
    return;
  }
  if (direction === 'next') next();
  else previous();
}

watch(
  () => current.value?.id,
  () => {
    elapsed = 0;
    progress.value = 0;
    if (currentGroup.value && current.value) markSeen(currentGroup.value, current.value);
  },
);

watch(position, (value, old) => {
  if (value && !old) {
    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    startTimer();
  } else if (!value && old) {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
    stopTimer();
    paused.value = false;
  }
});

watch(paused, (value) => {
  const video = videoEl.value;
  if (!video) return;
  if (value) video.pause();
  else void video.play().catch(() => undefined);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
  stopTimer();
});

async function removeCurrent() {
  const item = current.value;
  if (!item || !window.confirm('Supprimer cette story ?')) return;
  const response = await fetch(`/stories/${item.id}`, { method: 'DELETE', headers: headers(), credentials: 'same-origin' });
  if (response.ok) window.location.reload();
}

/* ---------- Ajout ---------- */

const addOpen = ref(false);
const file = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const previewIsVideo = ref(false);
const caption = ref('');
const busy = ref(false);
const error = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

function resetAdd() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  file.value = null;
  previewUrl.value = null;
  caption.value = '';
  error.value = '';
  busy.value = false;
}

function openAdd() {
  resetAdd();
  addOpen.value = true;
}

function closeAdd() {
  addOpen.value = false;
  resetAdd();
}

function onPick(event: Event) {
  const input = event.target as HTMLInputElement;
  const picked = input.files?.[0] ?? null;
  error.value = '';

  if (!picked) return;

  const isVideo = picked.type.startsWith('video/');
  if (!ACCEPT.split(',').includes(picked.type)) {
    error.value = 'Format non pris en charge : photo JPG, PNG ou WebP, ou vidéo MP4, WebM ou MOV.';
    input.value = '';
    return;
  }
  if (picked.size > (isVideo ? MAX_VIDEO : MAX_IMAGE)) {
    error.value = isVideo ? 'La vidéo dépasse 20 Mo.' : 'La photo dépasse 5 Mo.';
    input.value = '';
    return;
  }

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  file.value = picked;
  previewIsVideo.value = isVideo;
  previewUrl.value = URL.createObjectURL(picked);
}

async function publish() {
  if (!file.value || busy.value) return;
  busy.value = true;
  error.value = '';

  const body = new FormData();
  body.append('media', file.value);
  if (caption.value.trim() !== '') body.append('caption', caption.value.trim());

  try {
    const response = await fetch('/stories', { method: 'POST', headers: headers(), body, credentials: 'same-origin' });
    if (response.ok) {
      window.location.reload();
      return;
    }
    const payload = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
    error.value = payload.errors?.media?.[0] ?? payload.errors?.caption?.[0] ?? payload.message ?? 'La story n’a pas pu être publiée.';
  } catch {
    error.value = 'Connexion impossible. Réessayez.';
  }
  busy.value = false;
}
</script>

<template>
  <section class="card px-3 py-3" aria-label="Stories">
    <ul class="flex gap-3 overflow-x-auto pb-1">
      <li class="shrink-0">
        <div class="relative flex w-16 flex-col items-center gap-1">
          <button
            type="button"
            class="rounded-full p-[2.5px]"
            :class="myGroup ? 'bg-slate-300' : 'bg-transparent'"
            :aria-label="myGroup ? 'Voir mes stories' : 'Ajouter une story'"
            @click="myGroup ? openGroup(myGroup) : openAdd()"
          >
            <span class="block rounded-full bg-white p-0.5">
              <img v-if="meAvatar" :src="meAvatar" :alt="meName" class="h-14 w-14 rounded-full object-cover" />
              <span v-else class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-tlink-blue text-sm font-semibold text-white" aria-hidden="true">{{ initialsOf(meName) }}</span>
            </span>
          </button>
          <button
            type="button"
            class="absolute right-0 top-9 inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-tlink-blue text-[10px] text-white shadow-soft"
            title="Ajouter une story"
            aria-label="Ajouter une story"
            @click="openAdd"
          >
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
          </button>
          <span class="w-full truncate text-center text-[11px] font-medium text-slate-600">Votre story</span>
        </div>
      </li>

      <li v-for="group in others" :key="group.user.id" class="shrink-0">
        <button type="button" class="flex w-16 flex-col items-center gap-1" :aria-label="`Stories de ${group.user.name}`" @click="openGroup(group)">
          <span class="rounded-full p-[2.5px]" :class="group.all_seen ? 'bg-slate-300' : 'bg-gradient-to-tr from-amber-400 via-rose-500 to-tlink-blue'">
            <span class="block rounded-full bg-white p-0.5">
              <img v-if="group.user.avatar_url" :src="group.user.avatar_url" :alt="group.user.name" class="h-14 w-14 rounded-full object-cover" loading="lazy" />
              <span v-else class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-tlink-blue text-sm font-semibold text-white" aria-hidden="true">{{ initialsOf(group.user.name) }}</span>
            </span>
          </span>
          <span class="w-full truncate text-center text-[11px]" :class="group.all_seen ? 'text-slate-500' : 'font-semibold text-slate-800'">{{ group.user.name.split(' ')[0] }}</span>
        </button>
      </li>
    </ul>
  </section>

  <!-- Visionneuse -->
  <div v-if="current && currentGroup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" role="dialog" aria-modal="true" :aria-label="`Story de ${currentGroup.user.name}`">
    <div class="relative flex h-[100dvh] w-full max-w-md flex-col overflow-hidden bg-black sm:h-[92vh] sm:rounded-2xl" @pointerdown="onDown" @pointerup="onUp" @pointerleave="paused = false" @pointercancel="paused = false">
      <div class="absolute inset-x-0 top-0 z-20 bg-gradient-to-b from-black/60 to-transparent px-3 pb-6 pt-3">
        <div class="flex gap-1" aria-hidden="true">
          <span v-for="(item, index) in currentGroup.items" :key="item.id" class="h-0.5 flex-1 overflow-hidden rounded-full bg-white/30">
            <span class="block h-full bg-white" :style="{ width: index < (position?.i ?? 0) ? '100%' : index === position?.i ? `${progress * 100}%` : '0%' }"></span>
          </span>
        </div>
        <div class="mt-3 flex items-center gap-2 text-white">
          <img v-if="currentGroup.user.avatar_url" :src="currentGroup.user.avatar_url" alt="" class="h-9 w-9 rounded-full border border-white/40 object-cover" />
          <span v-else class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-tlink-blue text-xs font-semibold" aria-hidden="true">{{ initialsOf(currentGroup.user.name) }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">{{ currentGroup.user.is_me ? 'Votre story' : currentGroup.user.name }}</p>
            <p class="text-xs text-white/70">{{ timeAgo(current.created_at) }}</p>
          </div>
          <button v-if="currentGroup.user.is_me" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white/15" title="Supprimer cette story" aria-label="Supprimer cette story" @pointerdown.stop @click.stop="removeCurrent">
            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
          </button>
          <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white/15" title="Fermer" aria-label="Fermer" @pointerdown.stop @click.stop="close">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="relative flex-1">
        <img v-if="current.type === 'image'" :key="current.id" :src="current.url" alt="" class="absolute inset-0 h-full w-full object-contain" />
        <video
          v-else
          :key="current.id"
          ref="videoEl"
          :src="current.url"
          class="absolute inset-0 h-full w-full object-contain"
          autoplay
          playsinline
          @timeupdate="onVideoTime"
          @ended="next"
        ></video>
        <button type="button" class="absolute inset-y-0 left-0 z-10 w-1/3" aria-label="Story précédente" @click="tap('previous')"></button>
        <button type="button" class="absolute inset-y-0 right-0 z-10 w-2/3" aria-label="Story suivante" @click="tap('next')"></button>
      </div>

      <div v-if="current.caption || currentGroup.user.is_me" class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/70 to-transparent px-4 pb-5 pt-10 text-white">
        <p v-if="current.caption" class="text-sm">{{ current.caption }}</p>
        <p v-if="currentGroup.user.is_me" class="mt-1 text-xs text-white/70"><i class="fa-regular fa-eye mr-1" aria-hidden="true"></i>{{ current.views ?? 0 }} vue{{ (current.views ?? 0) > 1 ? 's' : '' }}</p>
      </div>
    </div>
  </div>

  <!-- Nouvelle story -->
  <div v-if="addOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-label="Ajouter une story" @click.self="closeAdd">
    <div class="w-full max-w-sm rounded-2xl bg-white p-4 shadow-soft">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">Nouvelle story</h2>
        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="Fermer" @click="closeAdd">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>

      <input ref="fileInput" type="file" :accept="ACCEPT" class="hidden" @change="onPick" />

      <button v-if="!previewUrl" type="button" class="flex h-64 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 text-sm text-slate-600 hover:bg-slate-100" @click="fileInput?.click()">
        <i class="fa-regular fa-image text-2xl text-slate-400" aria-hidden="true"></i>
        <span>Choisir une photo ou une vidéo</span>
        <span class="text-xs text-slate-400">Visible 24 h par les membres</span>
      </button>
      <div v-else class="relative h-64 overflow-hidden rounded-xl bg-black">
        <video v-if="previewIsVideo" :src="previewUrl" class="h-full w-full object-contain" controls muted playsinline></video>
        <img v-else :src="previewUrl" alt="Aperçu de la story" class="h-full w-full object-contain" />
        <button type="button" class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white" title="Changer de fichier" aria-label="Changer de fichier" @click="fileInput?.click()">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        </button>
      </div>

      <input v-model="caption" type="text" maxlength="160" placeholder="Ajouter une légende (facultatif)" class="mt-3 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-tlink-blue focus:bg-white" />
      <p v-if="error" class="mt-2 text-sm text-rose-600" role="alert">{{ error }}</p>

      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50" @click="closeAdd">Annuler</button>
        <button type="button" class="rounded-xl bg-tlink-cyan px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700 disabled:opacity-50" :disabled="!file || busy" @click="publish">
          {{ busy ? 'Publication…' : 'Publier la story' }}
        </button>
      </div>
    </div>
  </div>
</template>
