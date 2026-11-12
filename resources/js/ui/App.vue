<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import PartnersCarousel from './PartnersCarousel.vue';
import PendingMemberships from './PendingMemberships.vue';
import StoriesBar from './StoriesBar.vue';
import UpcomingActivities from './UpcomingActivities.vue';

type FeedPost = {
  id: number;
  content: string;
  type: string;
  visibility: string;
  sentiment: string;
  comments_enabled: boolean;
  can_manage: boolean;
  published_at: string | null;
  author?: { id: number; name: string; headline?: string | null; avatar_url?: string | null; verified?: boolean };
  attachments: Array<{ url: string; mime_type: string; kind: 'image' | 'video' }>;
  counts: { comments: number; reactions: number; shares: number; views: number };
  reactions: Record<string, number>;
  my_reaction?: string | null;
  comments_preview: Array<{
    id: number;
    content: string;
    author: string;
    author_id?: number | null;
    author_verified?: boolean;
    author_avatar_url?: string | null;
    created_at: string | null;
    replies: Array<{
      id: number;
      content: string;
      author: string;
      author_id?: number | null;
      author_verified?: boolean;
      author_avatar_url?: string | null;
      created_at: string | null;
    }>;
  }>;
};

type RootData = {
  auth: {
    isAuthenticated: boolean;
    canAdmin?: boolean;
    hasOffice?: boolean;
    user?: { id: number; name: string; email: string; avatar_url?: string | null };
  };
  feed: FeedPost[];
  composer: {
    can_publish_public: boolean;
    allowed_visibilities: string[];
  };
  partners?: Array<{ id: number; name: string; logo_url: string; url: string | null }>;
  advert?: { id: number; title: string; image_url: string; url: string | null } | null;
  links?: { mission?: string; badges?: string; academy?: string; challenges?: string };
  myBadges?: Array<{ id: number; name: string; tier: string; tier_key?: string; chip?: string; icon: string; gradient: string }>;
  menu?: Array<{ key: string; label: string; href: string; icon: string; active: boolean; badge: number }>;
  storiesEnabled?: boolean;
  stories?: Array<{
    user: { id: number; name: string; avatar_url: string | null; is_me: boolean };
    all_seen: boolean;
    items: Array<{ id: number; type: 'image' | 'video'; url: string; caption: string | null; created_at: string | null; seen: boolean; views: number | null }>;
  }>;
  governorates: Array<{ id: number; name: string; slug: string }>;
  upcoming?: Array<{ id: number; title: string; club: string | null; type: string; day: string; time: string; location: string | null; url: string }>;
  upcomingEnabled?: boolean;
  pendingMemberships?: { total: number; url: string; items: Array<{ id: number; name: string; photo_url: string | null; position: string | null; ago: string | null }> } | null;
  csrf: string;
};

const data = (window.__TLINK__ ?? {
  auth: { isAuthenticated: false },
  feed: [],
  composer: { can_publish_public: false, allowed_visibilities: ['authenticated', 'entity', 'department', 'club', 'restricted'] },
  partners: [],
  links: {},
  myBadges: [],
  governorates: [],
  upcoming: [],
  pendingMemberships: null,
  csrf: '',
}) as RootData;

const form = reactive({
  content: '',
  visibility: 'authenticated',
  sentiment: 'neutral',
});

const photoInput = ref<HTMLInputElement | null>(null);
const videoInput = ref<HTMLInputElement | null>(null);
const uploadState = reactive({
  photos: 0,
  hasVideo: false,
});

const posts = ref<FeedPost[]>(data.feed.map((post) => ({ ...post })));
const latestPosts = computed(() => posts.value.slice(0, 20));

// ---- Actions en temps réel : aucune page n'est rechargée, l'écran suit la réponse du serveur. ----
const toast = ref<string | null>(null);
let toastTimer: number | undefined;
const publishing = ref(false);
const drafts = reactive<Record<string, string>>({});
const inFlight = new Set<number>();
const incoming = ref<FeedPost[]>([]);

function showError(message: string) {
  toast.value = message;
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => (toast.value = null), 4500);
}

async function call(method: 'GET' | 'POST', url: string, body?: FormData | Record<string, unknown>): Promise<any> {
  const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': data.csrf };
  let payload: FormData | string | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  const response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    const first = json?.errors ? (Object.values(json.errors)[0] as string[] | undefined)?.[0] : null;
    throw new Error(first ?? json?.message ?? 'Action impossible pour le moment.');
  }
  return json;
}

function applyPost(updated: FeedPost, front = false) {
  const at = posts.value.findIndex((post) => post.id === updated.id);
  if (at >= 0) posts.value[at] = updated;
  else if (front) posts.value.unshift(updated);
}

async function act(post: FeedPost, run: () => Promise<any>) {
  inFlight.add(post.id);
  try {
    const json = await run();
    if (json?.deleted) posts.value = posts.value.filter((item) => item.id !== post.id);
    else if (json?.post) applyPost(json.post);
  } catch (error) {
    showError((error as Error).message);
    await refreshLive();
  } finally {
    inFlight.delete(post.id);
  }
}

function react(post: FeedPost, type: string) {
  // Affichage immédiat, puis alignement sur la réponse du serveur.
  const previous = post.my_reaction ?? null;
  const counts = { ...post.reactions };
  if (previous) {
    counts[previous] = (counts[previous] ?? 1) - 1;
    if (counts[previous] <= 0) delete counts[previous];
  }
  if (previous === type) {
    post.my_reaction = null;
    post.counts.reactions = Math.max(0, post.counts.reactions - 1);
  } else {
    counts[type] = (counts[type] ?? 0) + 1;
    post.my_reaction = type;
    if (!previous) post.counts.reactions += 1;
  }
  post.reactions = counts;
  void act(post, () => call('POST', `/posts/${post.id}/react`, { reaction_type: type }));
}

async function sendComment(post: FeedPost, key: string, parentId: number | null = null) {
  const content = (drafts[key] ?? '').trim();
  if (!content) return;
  drafts[key] = '';
  await act(post, () => call('POST', `/posts/${post.id}/comment`, { content, parent_id: parentId }));
}

function share(post: FeedPost) {
  void act(post, () => call('POST', `/posts/${post.id}/share`, { channel: 'internal' }));
}

function markViewed(post: FeedPost) {
  void act(post, () => call('POST', `/posts/${post.id}/view`));
}

// Formulaires de gestion d'une publication (modifier, commentaires, supprimer) : envoyés sans recharger.
function managePost(post: FeedPost, event: Event, confirmation?: string) {
  if (confirmation && !window.confirm(confirmation)) return;
  const form = event.target as HTMLFormElement;
  form.closest('details')?.removeAttribute('open');
  void act(post, () => call('POST', form.action, new FormData(form)));
}

async function publish(event: Event) {
  if (publishing.value) return;
  const formElement = event.target as HTMLFormElement;
  publishing.value = true;
  try {
    const json = await call('POST', formElement.action, new FormData(formElement));
    if (json?.post) applyPost(json.post, true);
    form.content = '';
    uploadState.photos = 0;
    uploadState.hasVideo = false;
    if (photoInput.value) photoInput.value.value = '';
    if (videoInput.value) videoInput.value.value = '';
  } catch (error) {
    showError((error as Error).message);
  } finally {
    publishing.value = false;
  }
}

// Actualisation en direct : réactions, commentaires et publications des autres membres apparaissent sans rien faire.
async function refreshLive() {
  if (!data.auth.isAuthenticated) return;
  try {
    const json = await call('GET', '/feed/posts');
    const fresh: FeedPost[] = json.posts ?? [];
    const known = new Set(posts.value.map((post) => post.id));
    const freshIds = new Set(fresh.map((post) => post.id));
    const oldest = fresh.length >= 12 ? Math.min(...fresh.map((post) => Date.parse(post.published_at ?? '') || 0)) : -Infinity;

    for (const post of fresh) {
      if (known.has(post.id)) {
        if (!inFlight.has(post.id)) applyPost(post);
      } else if (!incoming.value.some((item) => item.id === post.id)) {
        incoming.value.push(post);
      }
    }
    incoming.value = incoming.value.filter((post) => freshIds.has(post.id)).map((post) => fresh.find((item) => item.id === post.id) ?? post);
    posts.value = posts.value.filter((post) => freshIds.has(post.id) || inFlight.has(post.id) || (Date.parse(post.published_at ?? '') || 0) < oldest);
  } catch {
    /* connexion momentanément indisponible : on réessaiera */
  }
}

function showIncoming() {
  const ordered = [...incoming.value].sort((a, b) => (Date.parse(b.published_at ?? '') || 0) - (Date.parse(a.published_at ?? '') || 0));
  posts.value = [...ordered, ...posts.value];
  incoming.value = [];
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

let liveTimer: number | undefined;

function onVisible() {
  if (!document.hidden) void refreshLive();
}

// Badges regroupés par type, du plus courant au plus élevé.
const TIER_ORDER = ['bronze', 'silver', 'gold', 'platinum'];
const badgeGroups = computed(() => {
  const groups = new Map<string, { key: string; label: string; chip: string; badges: NonNullable<RootData['myBadges']> }>();
  for (const badge of data.myBadges ?? []) {
    const key = badge.tier_key ?? badge.tier;
    if (!groups.has(key)) groups.set(key, { key, label: badge.tier, chip: badge.chip ?? 'border-slate-200 bg-slate-50 text-slate-700', badges: [] });
    groups.get(key)?.badges.push(badge);
  }
  return [...groups.values()].sort((a, b) => {
    const rank = (key: string) => (TIER_ORDER.includes(key) ? TIER_ORDER.indexOf(key) : TIER_ORDER.length);
    return rank(a.key) - rank(b.key);
  });
});

const initials = computed(() => {
  const full = data.auth.user?.name ?? 'Invité';
  return full
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
});

const reactionLabels: Record<string, string> = {
  like: 'Like',
  support: 'Support',
  insightful: 'Insight',
  celebrate: 'Bravo',
};

const allVisibilityChoices = [
  { value: 'public', icon: 'fa-earth-africa', title: 'Public' },
  { value: 'authenticated', icon: 'fa-user-group', title: 'Communaute' },
  { value: 'entity', icon: 'fa-building', title: 'Entite' },
  { value: 'department', icon: 'fa-diagram-project', title: 'Departement' },
  { value: 'club', icon: 'fa-people-group', title: 'Club' },
  { value: 'restricted', icon: 'fa-lock', title: 'Restreint' },
];

const visibilityChoices = computed(() => {
  const allowed = new Set(data.composer?.allowed_visibilities ?? []);
  return allVisibilityChoices.filter((item) => allowed.has(item.value));
});

const sentimentChoices = [
  { value: 'neutral', icon: 'fa-face-meh', title: 'Neutre' },
  { value: 'positive', icon: 'fa-face-smile', title: 'Positif' },
  { value: 'negative', icon: 'fa-face-frown', title: 'Negatif' },
  { value: 'inspired', icon: 'fa-lightbulb', title: 'Inspire' },
  { value: 'grateful', icon: 'fa-hands-praying', title: 'Reconnaissant' },
];

const openMenu = ref<'visibility' | 'sentiment' | null>(null);
const composerMenus = ref<HTMLElement | null>(null);

const currentVisibility = computed(() => visibilityChoices.value.find((item) => item.value === form.visibility) ?? visibilityChoices.value[0]);
const currentSentiment = computed(() => sentimentChoices.find((item) => item.value === form.sentiment) ?? sentimentChoices[0]);

function toggleMenu(name: 'visibility' | 'sentiment') {
  openMenu.value = openMenu.value === name ? null : name;
}

function chooseVisibility(value: string) {
  form.visibility = value;
  openMenu.value = null;
}

function chooseSentiment(value: string) {
  form.sentiment = value;
  openMenu.value = null;
}

function closeMenuOutside(event: Event) {
  if (composerMenus.value && !composerMenus.value.contains(event.target as Node)) {
    openMenu.value = null;
  }
}

function closeMenuOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    openMenu.value = null;
  }
}

onMounted(() => {
  document.addEventListener('click', closeMenuOutside);
  document.addEventListener('keydown', closeMenuOnEscape);
  document.addEventListener('visibilitychange', onVisible);
  liveTimer = window.setInterval(() => {
    if (!document.hidden) void refreshLive();
  }, 12000);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenuOutside);
  document.removeEventListener('keydown', closeMenuOnEscape);
  document.removeEventListener('visibilitychange', onVisible);
  window.clearInterval(liveTimer);
  window.clearTimeout(toastTimer);
});

function triggerPhotos() {
  photoInput.value?.click();
}

function triggerVideo() {
  videoInput.value?.click();
}

function onPhotosChanged(event: Event) {
  const target = event.target as HTMLInputElement;
  uploadState.photos = target.files?.length ?? 0;
}

function onVideoChanged(event: Event) {
  const target = event.target as HTMLInputElement;
  uploadState.hasVideo = (target.files?.length ?? 0) > 0;
}

function iconForReaction(type: string): string {
  if (type === 'like') return 'fa-thumbs-up';
  if (type === 'support') return 'fa-handshake';
  if (type === 'insightful') return 'fa-lightbulb';
  return 'fa-star';
}

function iconForVisibility(value: string): string {
  return allVisibilityChoices.find((item) => item.value === value)?.icon ?? 'fa-user-group';
}

function iconForSentiment(value: string): string {
  return sentimentChoices.find((item) => item.value === value)?.icon ?? 'fa-face-meh';
}

function authorInitials(name?: string): string {
  const full = (name ?? 'Membre').trim();
  return full
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
}

function formatPublishedAt(value: string | null): string {
  if (!value) return 'Brouillon';
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatCommentAt(value: string | null): string {
  if (!value) return 'A l\'instant';
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

if (!visibilityChoices.value.some((item) => item.value === form.visibility)) {
  form.visibility = visibilityChoices.value[0]?.value ?? 'authenticated';
}
</script>

<template>
  <div class="bg-tlink-sand pb-12 text-slate-800">
    <main class="app-container mt-6">
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <aside class="hidden lg:col-span-3 lg:block">
          <div class="sticky top-24 space-y-4">
            <nav class="card p-4" aria-label="Menu">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Menu</p>
              <ul class="space-y-1 text-sm">
                <li v-for="item in data.menu ?? []" :key="item.key">
                  <a
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="flex items-center gap-2 rounded-lg px-3 py-2"
                    :class="item.active ? 'bg-slate-100 text-slate-800' : 'text-slate-700 hover:bg-slate-50'"
                  >
                    <i :class="item.icon" class="w-4 text-center text-slate-500" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                    <span v-if="item.badge > 0" class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-tlink-blue px-1.5 text-[10px] font-bold leading-5 text-white">{{ item.badge > 99 ? '99+' : item.badge }}</span>
                  </a>
                </li>
              </ul>
            </nav>

            <section class="card p-4">
              <div class="mb-3 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mes badges</p>
                <a :href="data.links?.badges ?? '/badges'" class="text-xs font-semibold text-tlink-blue hover:underline">Missions</a>
              </div>
              <div v-if="badgeGroups.length" class="space-y-3">
                <div v-for="group in badgeGroups" :key="group.key">
                  <p class="mb-1.5 flex items-center gap-2">
                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide" :class="group.chip">{{ group.label }}</span>
                    <span class="text-xs text-slate-400">{{ group.badges.length }}</span>
                  </p>
                  <ul class="flex flex-wrap gap-2">
                    <li v-for="badge in group.badges" :key="badge.id">
                      <span
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br text-base text-white shadow-soft ring-2 ring-white"
                        :class="badge.gradient"
                        :title="`${badge.name} · ${badge.tier}`"
                      >
                        <i :class="badge.icon" aria-hidden="true"></i>
                      </span>
                    </li>
                  </ul>
                </div>
              </div>
              <a v-else :href="data.links?.badges ?? '/badges'" class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600 hover:bg-slate-100">
                <i class="fa-solid fa-award text-lg text-slate-400"></i>
                <span>Relevez des missions pour gagner vos premiers badges.</span>
              </a>
            </section>
          </div>
        </aside>

        <section class="space-y-4 lg:col-span-6">
        <StoriesBar
          v-if="data.auth.isAuthenticated && data.storiesEnabled"
          :groups="data.stories ?? []"
          :csrf="data.csrf"
          :me-name="data.auth.user?.name ?? ''"
          :me-avatar="data.auth.user?.avatar_url"
        />
        <PartnersCarousel v-if="data.partners?.length" :partners="data.partners" :per-page="3" class="lg:hidden" />
        <UpcomingActivities v-if="data.upcomingEnabled" :entries="data.upcoming ?? []" :challenges-url="data.links?.challenges" class="lg:hidden" />
        <PendingMemberships v-if="data.pendingMemberships" v-bind="data.pendingMemberships" class="lg:hidden" />
        <form v-if="data.auth.isAuthenticated" action="/posts" method="post" enctype="multipart/form-data" class="card p-4" @submit.prevent="publish">
          <input type="hidden" name="_token" :value="data.csrf" />
          <div class="flex items-start gap-3">
            <img
              v-if="data.auth.user?.avatar_url"
              :src="data.auth.user.avatar_url"
              :alt="data.auth.user.name"
              class="h-10 w-10 shrink-0 rounded-full border border-slate-200 object-cover"
            />
            <span v-else class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tlink-blue text-xs font-semibold text-white" aria-hidden="true">
              {{ initials }}
            </span>
            <textarea
              id="composer"
              v-model="form.content"
              name="content"
              rows="2"
              class="min-h-[4.5rem] w-full flex-1 resize-y rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-tlink-blue focus:bg-white"
              placeholder="Partage une actualité de ton club, projet ou événement…"
              required
            ></textarea>
          </div>
          <input ref="photoInput" type="file" name="photos[]" accept="image/*" multiple class="hidden" @change="onPhotosChanged" />
          <input ref="videoInput" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" class="hidden" @change="onVideoChanged" />
          <div class="mt-3 flex items-center justify-between gap-2 sm:pl-[3.25rem]">
          <div class="flex min-w-0 flex-wrap items-center gap-1.5">
            <button type="button" class="inline-flex h-9 items-center gap-1 rounded-full border border-slate-200 px-3 text-sm text-slate-600 hover:bg-slate-50" title="Ajouter des photos" aria-label="Ajouter des photos" @click="triggerPhotos">
              <i class="fa-regular fa-image"></i>
              <span v-if="uploadState.photos" class="text-xs font-semibold text-tlink-blue">{{ uploadState.photos }}</span>
            </button>
            <button type="button" class="inline-flex h-9 items-center gap-1 rounded-full border border-slate-200 px-3 text-sm text-slate-600 hover:bg-slate-50" title="Ajouter une vidéo" aria-label="Ajouter une vidéo" @click="triggerVideo">
              <i class="fa-solid fa-video"></i>
              <span v-if="uploadState.hasVideo" class="text-xs font-semibold text-tlink-blue">1</span>
            </button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
            <input type="hidden" name="visibility" :value="form.visibility" />
            <input type="hidden" name="sentiment" :value="form.sentiment" />
            <div ref="composerMenus" class="flex flex-wrap items-center gap-1.5">
              <div class="relative">
                <button
                  type="button"
                  class="flex h-9 items-center gap-2 rounded-full border border-slate-200 px-3 text-sm text-slate-700 hover:bg-slate-50"
                  aria-haspopup="listbox"
                  :aria-expanded="openMenu === 'visibility'"
                  :aria-label="`Qui peut voir cette publication : ${currentVisibility?.title ?? ''}`"
                  :title="`Qui peut voir cette publication : ${currentVisibility?.title ?? ''}`"
                  @click="toggleMenu('visibility')"
                >
                  <i class="fa-solid w-4 text-center text-tlink-blue" :class="currentVisibility?.icon"></i>
                  <span class="hidden sm:inline">{{ currentVisibility?.title }}</span>
                  <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </button>
                <ul v-if="openMenu === 'visibility'" role="listbox" class="absolute left-0 z-20 mt-1 min-w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                  <li v-for="item in visibilityChoices" :key="item.value" role="option" :aria-selected="form.visibility === item.value">
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm"
                      :class="form.visibility === item.value ? 'bg-slate-100 text-tlink-blue' : 'text-slate-700 hover:bg-slate-50'"
                      @click="chooseVisibility(item.value)"
                    >
                      <i class="fa-solid w-4 text-center" :class="item.icon"></i>
                      <span>{{ item.title }}</span>
                    </button>
                  </li>
                </ul>
              </div>

              <div class="relative">
                <button
                  type="button"
                  class="flex h-9 items-center gap-2 rounded-full border border-slate-200 px-3 text-sm text-slate-700 hover:bg-slate-50"
                  aria-haspopup="listbox"
                  :aria-expanded="openMenu === 'sentiment'"
                  :aria-label="`Ton de la publication : ${currentSentiment?.title ?? ''}`"
                  :title="`Ton de la publication : ${currentSentiment?.title ?? ''}`"
                  @click="toggleMenu('sentiment')"
                >
                  <i class="fa-regular w-4 text-center text-tlink-blue" :class="currentSentiment?.icon"></i>
                  <span class="hidden sm:inline">{{ currentSentiment?.title }}</span>
                  <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                </button>
                <ul v-if="openMenu === 'sentiment'" role="listbox" class="absolute left-0 z-20 mt-1 min-w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                  <li v-for="item in sentimentChoices" :key="item.value" role="option" :aria-selected="form.sentiment === item.value">
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm"
                      :class="form.sentiment === item.value ? 'bg-rose-50 text-tlink-blue' : 'text-slate-700 hover:bg-slate-50'"
                      @click="chooseSentiment(item.value)"
                    >
                      <i class="fa-regular w-4 text-center" :class="item.icon"></i>
                      <span>{{ item.title }}</span>
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </div>
            <button class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tlink-cyan text-white shadow-soft hover:bg-tlink-blue-700 disabled:opacity-60" type="submit" title="Publier" aria-label="Publier" :disabled="publishing">
              <i class="fa-solid" :class="publishing ? 'fa-spinner fa-spin' : 'fa-paper-plane'" aria-hidden="true"></i>
            </button>
          </div>
        </form>

        <button v-if="incoming.length" type="button" class="flex w-full items-center justify-center gap-2 rounded-full bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white shadow-soft hover:bg-tlink-blue-700" @click="showIncoming">
          <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
          {{ incoming.length }} nouvelle{{ incoming.length > 1 ? 's' : '' }} publication{{ incoming.length > 1 ? 's' : '' }}
        </button>

        <template v-for="(post, postIndex) in latestPosts" :key="post.id">
        <article class="card p-4">
          <div class="mb-3 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
              <img
                v-if="post.author?.avatar_url"
                :src="post.author.avatar_url"
                :alt="post.author?.name ?? 'avatar'"
                class="h-10 w-10 rounded-full border border-slate-200 object-cover"
                loading="lazy"
              />
              <span
                v-else
                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-tlink-blue text-xs font-semibold text-white"
              >
                {{ authorInitials(post.author?.name) }}
              </span>
              <div>
                <p class="text-sm font-semibold text-slate-800">
                  <a v-if="post.author?.id" :href="`/membres/${post.author.id}`" class="hover:text-tlink-blue hover:underline">{{ post.author.name }}</a>
                  <template v-else>Membre Tunivisions</template>
                  <i v-if="post.author?.verified" class="fa-solid fa-circle-check ml-1 text-[0.9em] text-sky-500" title="Profil vérifié" role="img" aria-label="Profil vérifié"></i>
                </p>
                <p class="text-xs text-slate-500">{{ post.author?.headline || 'Membre Tunivisions' }}</p>
                <p class="mt-0.5 text-xs text-slate-400">
                  <i class="fa-regular fa-clock mr-1"></i>{{ formatPublishedAt(post.published_at) }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-600" :title="post.visibility">
                <i class="fa-solid" :class="iconForVisibility(post.visibility)"></i>
              </span>
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-50 text-xs text-tlink-blue" :title="post.sentiment">
                <i class="fa-regular" :class="iconForSentiment(post.sentiment)"></i>
              </span>
              <details v-if="post.can_manage" class="relative">
                <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 text-xs text-slate-600 hover:bg-slate-50" title="Gerer le post">
                  <i class="fa-solid fa-ellipsis"></i>
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-soft">
                  <form :action="`/posts/${post.id}`" method="post" class="space-y-2" @submit.prevent="managePost(post, $event)">
                    <input type="hidden" name="_token" :value="data.csrf" />
                    <input type="hidden" name="_method" value="PATCH" />
                    <textarea
                      name="content"
                      rows="3"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-tlink-blue"
                      required
                      :value="post.content"
                    ></textarea>
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                      Modifier le post
                    </button>
                  </form>

                  <form :action="`/posts/${post.id}/comments-state`" method="post" class="mt-2" @submit.prevent="managePost(post, $event)">
                    <input type="hidden" name="_token" :value="data.csrf" />
                    <input type="hidden" name="_method" value="PATCH" />
                    <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50">
                      {{ post.comments_enabled ? 'Desactiver les commentaires' : 'Activer les commentaires' }}
                    </button>
                  </form>

                  <form :action="`/posts/${post.id}`" method="post" class="mt-2" @submit.prevent="managePost(post, $event, 'Supprimer ce post ?')">
                    <input type="hidden" name="_token" :value="data.csrf" />
                    <input type="hidden" name="_method" value="DELETE" />
                    <button type="submit" class="w-full rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                      Supprimer le post
                    </button>
                  </form>
                </div>
              </details>
            </div>
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ post.content }}</p>

          <div v-if="post.attachments.length" class="mt-3 grid gap-2">
            <template v-for="(attachment, idx) in post.attachments" :key="idx">
              <img
                v-if="attachment.kind === 'image'"
                :src="attachment.url"
                alt="media"
                class="max-h-96 w-full rounded-xl border border-slate-200 object-cover"
                loading="lazy"
              />
              <video
                v-else
                controls
                class="max-h-96 w-full rounded-xl border border-slate-200"
              >
                <source :src="attachment.url" :type="attachment.mime_type" />
              </video>
            </template>
          </div>

          <div v-if="Object.keys(post.reactions).length" class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
            <span v-for="(count, key) in post.reactions" :key="key" class="rounded-full bg-rose-50 px-2 py-1 text-tlink-blue" :title="reactionLabels[key] ?? key">
              <i class="fa-regular mr-1" :class="iconForReaction(key)"></i>{{ count }}
            </span>
          </div>

          <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <div v-if="data.auth.isAuthenticated" class="flex flex-wrap gap-2">
              <button
                v-for="(label, reactionType) in reactionLabels"
                :key="reactionType"
                type="button"
                class="rounded-lg border px-2 py-1 text-xs transition"
                :class="post.my_reaction === reactionType ? 'border-tlink-blue bg-tlink-blue text-white' : 'border-slate-300 hover:bg-slate-50'"
                :title="label"
                :aria-label="label"
                :aria-pressed="post.my_reaction === reactionType"
                @click="react(post, reactionType)"
              >
                <i v-if="reactionType === 'like'" :class="post.my_reaction === reactionType ? 'fa-solid' : 'fa-regular'" class="fa-thumbs-up"></i>
                <i v-else-if="reactionType === 'support'" :class="post.my_reaction === reactionType ? 'fa-solid' : 'fa-regular'" class="fa-handshake"></i>
                <i v-else-if="reactionType === 'insightful'" :class="post.my_reaction === reactionType ? 'fa-solid' : 'fa-regular'" class="fa-lightbulb"></i>
                <i v-else :class="post.my_reaction === reactionType ? 'fa-solid' : 'fa-regular'" class="fa-star"></i>
              </button>
              <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" title="Partager" aria-label="Partager" @click="share(post)">
                <i class="fa-solid fa-share-nodes"></i>
              </button>
              <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" title="Marquer vue" aria-label="Marquer vue" @click="markViewed(post)">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
            <div class="flex flex-wrap gap-2 text-xs text-slate-600">
              <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-thumbs-up mr-1"></i>{{ post.counts.reactions }}</span>
              <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-comment mr-1"></i>{{ post.counts.comments }}</span>
              <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-solid fa-share-nodes mr-1"></i>{{ post.counts.shares }}</span>
              <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-eye mr-1"></i>{{ post.counts.views }}</span>
            </div>
          </div>

          <div v-if="!post.comments_enabled" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Commentaires desactives pour ce post.
          </div>

          <div v-if="post.comments_preview.length" class="mt-3 space-y-3">
            <div v-for="comment in post.comments_preview" :key="comment.id" class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-700">
              <div class="flex items-start gap-2">
                <img
                  v-if="comment.author_avatar_url"
                  :src="comment.author_avatar_url"
                  :alt="comment.author"
                  class="h-7 w-7 rounded-full border border-slate-200 object-cover"
                  loading="lazy"
                />
                <span
                  v-else
                  class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-700"
                >
                  {{ authorInitials(comment.author) }}
                </span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <strong>
                      <a v-if="comment.author_id" :href="`/membres/${comment.author_id}`" class="hover:text-tlink-blue hover:underline">{{ comment.author }}</a>
                      <template v-else>{{ comment.author }}</template>
                      <i v-if="comment.author_verified" class="fa-solid fa-circle-check ml-1 text-[0.9em] text-sky-500" title="Profil vérifié" role="img" aria-label="Profil vérifié"></i>
                    </strong>
                    <span class="text-[11px] text-slate-400">{{ formatCommentAt(comment.created_at) }}</span>
                  </div>
                  <p class="mt-0.5 whitespace-pre-line text-xs text-slate-700">{{ comment.content }}</p>

                  <div v-if="comment.replies.length" class="mt-2 space-y-2 border-l border-slate-200 pl-3">
                    <div v-for="reply in comment.replies" :key="reply.id" class="rounded-lg bg-white px-2 py-2">
                      <div class="flex items-start gap-2">
                        <img
                          v-if="reply.author_avatar_url"
                          :src="reply.author_avatar_url"
                          :alt="reply.author"
                          class="h-6 w-6 rounded-full border border-slate-200 object-cover"
                          loading="lazy"
                        />
                        <span
                          v-else
                          class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-700"
                        >
                          {{ authorInitials(reply.author) }}
                        </span>
                        <div class="min-w-0 flex-1">
                          <div class="flex items-center gap-2">
                            <strong>
                              <a v-if="reply.author_id" :href="`/membres/${reply.author_id}`" class="hover:text-tlink-blue hover:underline">{{ reply.author }}</a>
                              <template v-else>{{ reply.author }}</template>
                              <i v-if="reply.author_verified" class="fa-solid fa-circle-check ml-1 text-[0.9em] text-sky-500" title="Profil vérifié" role="img" aria-label="Profil vérifié"></i>
                            </strong>
                            <span class="text-[11px] text-slate-400">{{ formatCommentAt(reply.created_at) }}</span>
                          </div>
                          <p class="mt-0.5 whitespace-pre-line text-xs text-slate-700">{{ reply.content }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <details v-if="data.auth.isAuthenticated && post.comments_enabled" class="mt-2">
                    <summary class="cursor-pointer select-none text-[11px] text-tlink-blue">Repondre</summary>
                    <form :action="`/posts/${post.id}/comment`" method="post" class="mt-2 flex gap-2" @submit.prevent="sendComment(post, `r-${comment.id}`, comment.id)">
                      <input
                        v-model="drafts[`r-${comment.id}`]"
                        type="text"
                        name="content"
                        maxlength="1000"
                        required
                        placeholder="Votre reponse..."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs"
                      />
                      <button type="submit" class="rounded-xl bg-tlink-blue px-3 py-2 text-xs font-semibold text-white hover:bg-tlink-blue-700" title="Envoyer la reponse">
                        <i class="fa-solid fa-paper-plane"></i>
                      </button>
                    </form>
                  </details>
                </div>
              </div>
            </div>
          </div>

          <form v-if="data.auth.isAuthenticated && post.comments_enabled" :action="`/posts/${post.id}/comment`" method="post" class="mt-3 flex gap-2" @submit.prevent="sendComment(post, `c-${post.id}`)">
            <input
              v-model="drafts[`c-${post.id}`]"
              type="text"
              name="content"
              maxlength="1000"
              required
              placeholder="Commenter..."
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
            />
            <button type="submit" class="rounded-xl bg-tlink-blue px-3 py-2 text-xs font-semibold text-white hover:bg-tlink-blue-700" title="Commenter">
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </form>
        </article>

        <aside v-if="data.advert && (postIndex === 1 || (postIndex === 0 && latestPosts.length === 1))" class="card overflow-hidden p-0" aria-label="Publicité">
          <a v-if="data.advert.url" :href="data.advert.url" target="_blank" rel="noopener noreferrer sponsored" class="block">
            <img :src="data.advert.image_url" :alt="data.advert.title" class="w-full object-cover" loading="lazy" />
          </a>
          <img v-else :src="data.advert.image_url" :alt="data.advert.title" class="w-full object-cover" loading="lazy" />
          <p class="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Publicité</p>
        </aside>
        </template>

        <div v-if="latestPosts.length === 0" class="card p-6 text-center text-sm text-slate-500">
          Aucun post pour le moment.
        </div>
        </section>

        <aside class="hidden lg:col-span-3 lg:block">
          <div class="sticky top-24 space-y-4">
            <PartnersCarousel v-if="data.partners?.length" :partners="data.partners" :per-page="2" />
            <UpcomingActivities v-if="data.upcomingEnabled" :entries="data.upcoming ?? []" :challenges-url="data.links?.challenges" />
            <PendingMemberships v-if="data.pendingMemberships" v-bind="data.pendingMemberships" />
          </div>
        </aside>
      </div>
    </main>

    <div v-if="toast" class="fixed inset-x-0 bottom-20 z-50 mx-auto w-[90%] max-w-md rounded-2xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-soft lg:bottom-6" role="alert">{{ toast }}</div>
  </div>
</template>
