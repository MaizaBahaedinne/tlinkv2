<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

type FeedPost = {
  id: number;
  content: string;
  type: string;
  visibility: string;
  sentiment: string;
  comments_enabled: boolean;
  can_manage: boolean;
  published_at: string | null;
  author?: { id: number; name: string; headline?: string | null; avatar_url?: string | null };
  attachments: Array<{ url: string; mime_type: string; kind: 'image' | 'video' }>;
  counts: { comments: number; reactions: number; shares: number; views: number };
  reactions: Record<string, number>;
  comments_preview: Array<{
    id: number;
    content: string;
    author: string;
    author_avatar_url?: string | null;
    created_at: string | null;
    replies: Array<{
      id: number;
      content: string;
      author: string;
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
    user?: { id: number; name: string; email: string };
  };
  feed: FeedPost[];
  composer: {
    can_publish_public: boolean;
    allowed_visibilities: string[];
  };
  partners?: Array<{ id: number; name: string; logo_url: string; url: string | null }>;
  links?: { mission?: string };
  governorates: Array<{ id: number; name: string; slug: string }>;
  csrf: string;
};

const data = (window.__TLINK__ ?? {
  auth: { isAuthenticated: false },
  feed: [],
  composer: { can_publish_public: false, allowed_visibilities: ['authenticated', 'entity', 'department', 'club', 'restricted'] },
  partners: [],
  links: {},
  governorates: [],
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

const latestPosts = computed(() => data.feed.slice(0, 20));

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
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenuOutside);
  document.removeEventListener('keydown', closeMenuOnEscape);
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
              <ul class="space-y-2 text-sm">
                <li>
                  <a href="/feed" aria-current="page" class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-slate-800">
                    <i class="fa-solid fa-house w-4 text-center text-slate-500"></i>
                    <span>Accueil</span>
                  </a>
                </li>
                <li>
                  <a :href="data.links?.mission ?? '#'" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-bullseye w-4 text-center text-slate-500"></i>
                    <span>Mission</span>
                  </a>
                </li>
                <li>
                  <span class="flex cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                    <i class="fa-solid fa-graduation-cap w-4 text-center text-slate-400"></i>
                    <span>Academy</span>
                    <span class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Soon</span>
                  </span>
                </li>
                <li>
                  <span class="flex cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                    <i class="fa-solid fa-store w-4 text-center text-slate-400"></i>
                    <span>Store</span>
                    <span class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Soon</span>
                  </span>
                </li>
              </ul>
            </nav>

            <section class="card p-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Mes badges</p>
              <div class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                <i class="fa-solid fa-award text-lg text-slate-400"></i>
                <span>Vos badges apparaîtront ici.</span>
                <span class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Soon</span>
              </div>
            </section>
          </div>
        </aside>

        <section class="space-y-4 lg:col-span-6">
        <form v-if="data.auth.isAuthenticated" action="/posts" method="post" enctype="multipart/form-data" class="card p-4">
          <input type="hidden" name="_token" :value="data.csrf" />
          <textarea
            id="composer"
            v-model="form.content"
            name="content"
            rows="3"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue"
            placeholder="Partage une actualite de ton club, projet ou evenement..."
            required
          ></textarea>
          <input ref="photoInput" type="file" name="photos[]" accept="image/*" multiple class="hidden" @change="onPhotosChanged" />
          <input ref="videoInput" type="file" name="video" accept="video/mp4,video/webm,video/quicktime" class="hidden" @change="onVideoChanged" />
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" title="Ajouter des photos" @click="triggerPhotos">
              <i class="fa-regular fa-image"></i>
              <span v-if="uploadState.photos" class="ml-1 text-xs text-slate-500">{{ uploadState.photos }}</span>
            </button>
            <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" title="Ajouter une video" @click="triggerVideo">
              <i class="fa-solid fa-video"></i>
              <span v-if="uploadState.hasVideo" class="ml-1 text-xs text-slate-500">1</span>
            </button>
            <input type="hidden" name="visibility" :value="form.visibility" />
            <input type="hidden" name="sentiment" :value="form.sentiment" />
            <div ref="composerMenus" class="flex flex-wrap items-center gap-2">
              <div class="relative">
                <button
                  type="button"
                  class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  aria-haspopup="listbox"
                  :aria-expanded="openMenu === 'visibility'"
                  title="Qui peut voir cette publication"
                  @click="toggleMenu('visibility')"
                >
                  <i class="fa-solid w-4 text-center text-tlink-blue" :class="currentVisibility?.icon"></i>
                  <span>{{ currentVisibility?.title }}</span>
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
                  class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  aria-haspopup="listbox"
                  :aria-expanded="openMenu === 'sentiment'"
                  title="Ton de la publication"
                  @click="toggleMenu('sentiment')"
                >
                  <i class="fa-regular w-4 text-center text-tlink-blue" :class="currentSentiment?.icon"></i>
                  <span>{{ currentSentiment?.title }}</span>
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
          <div class="mt-3 flex justify-end">
            <button class="rounded-xl bg-tlink-cyan px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700" type="submit">
              <i class="fa-solid fa-paper-plane mr-1"></i>
              Publier
            </button>
          </div>
        </form>

        <article v-for="post in latestPosts" :key="post.id" class="card p-4">
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
                <p class="text-sm font-semibold text-slate-800">{{ post.author?.name ?? 'Membre Tunivisions' }}</p>
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
                  <form :action="`/posts/${post.id}`" method="post" class="space-y-2">
                    <input type="hidden" name="_token" :value="data.csrf" />
                    <input type="hidden" name="_method" value="PATCH" />
                    <textarea
                      name="content"
                      rows="3"
                      class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs outline-none focus:border-tlink-blue"
                      required
                    >{{ post.content }}</textarea>
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                      Modifier le post
                    </button>
                  </form>

                  <form :action="`/posts/${post.id}/comments-state`" method="post" class="mt-2">
                    <input type="hidden" name="_token" :value="data.csrf" />
                    <input type="hidden" name="_method" value="PATCH" />
                    <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50">
                      {{ post.comments_enabled ? 'Desactiver les commentaires' : 'Activer les commentaires' }}
                    </button>
                  </form>

                  <form :action="`/posts/${post.id}`" method="post" class="mt-2" onsubmit="return confirm('Supprimer ce post ?');">
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
              <form v-for="(label, reactionType) in reactionLabels" :key="reactionType" :action="`/posts/${post.id}/react`" method="post">
                <input type="hidden" name="_token" :value="data.csrf" />
                <input type="hidden" name="reaction_type" :value="reactionType" />
                <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" :title="label">
                  <i v-if="reactionType === 'like'" class="fa-regular fa-thumbs-up"></i>
                  <i v-else-if="reactionType === 'support'" class="fa-regular fa-handshake"></i>
                  <i v-else-if="reactionType === 'insightful'" class="fa-regular fa-lightbulb"></i>
                  <i v-else class="fa-regular fa-star"></i>
                </button>
              </form>
              <form :action="`/posts/${post.id}/share`" method="post">
                <input type="hidden" name="_token" :value="data.csrf" />
                <input type="hidden" name="channel" value="internal" />
                <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" title="Partager">
                  <i class="fa-solid fa-share-nodes"></i>
                </button>
              </form>
              <form :action="`/posts/${post.id}/view`" method="post">
                <input type="hidden" name="_token" :value="data.csrf" />
                <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" title="Marquer vue">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </form>
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
                    <strong>{{ comment.author }}</strong>
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
                            <strong>{{ reply.author }}</strong>
                            <span class="text-[11px] text-slate-400">{{ formatCommentAt(reply.created_at) }}</span>
                          </div>
                          <p class="mt-0.5 whitespace-pre-line text-xs text-slate-700">{{ reply.content }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <details v-if="data.auth.isAuthenticated && post.comments_enabled" class="mt-2">
                    <summary class="cursor-pointer select-none text-[11px] text-tlink-blue">Repondre</summary>
                    <form :action="`/posts/${post.id}/comment`" method="post" class="mt-2 flex gap-2">
                      <input type="hidden" name="_token" :value="data.csrf" />
                      <input type="hidden" name="parent_id" :value="comment.id" />
                      <input
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

          <form v-if="data.auth.isAuthenticated && post.comments_enabled" :action="`/posts/${post.id}/comment`" method="post" class="mt-3 flex gap-2">
            <input type="hidden" name="_token" :value="data.csrf" />
            <input
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

        <div v-if="latestPosts.length === 0" class="card p-6 text-center text-sm text-slate-500">
          Aucun post pour le moment.
        </div>
        </section>

        <aside class="hidden lg:col-span-3 lg:block">
          <div class="sticky top-24 space-y-4">
            <section class="card overflow-hidden p-0">
              <div class="bg-gradient-to-r from-tlink-blue to-tlink-blue-700 px-4 py-3 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide">Publicite</p>
                <h3 class="mt-1 text-sm font-semibold">Formation Leadership</h3>
              </div>
              <div class="p-4 text-sm text-slate-700">
                Boostez vos competences de pilotage projet en 4 semaines.
              </div>
            </section>

            <section v-if="data.partners?.length" class="card p-4">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Partenaires</p>
              <ul class="grid grid-cols-2 gap-3">
                <li v-for="partner in data.partners" :key="partner.id">
                  <a
                    v-if="partner.url"
                    :href="partner.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :title="partner.name"
                    class="flex h-16 items-center justify-center rounded-xl border border-slate-200 bg-white p-2 hover:border-slate-300"
                  >
                    <img :src="partner.logo_url" :alt="partner.name" loading="lazy" class="max-h-full max-w-full object-contain" />
                  </a>
                  <span v-else :title="partner.name" class="flex h-16 items-center justify-center rounded-xl border border-slate-200 bg-white p-2">
                    <img :src="partner.logo_url" :alt="partner.name" loading="lazy" class="max-h-full max-w-full object-contain" />
                  </span>
                </li>
              </ul>
            </section>
          </div>
        </aside>
      </div>
    </main>
  </div>
</template>
