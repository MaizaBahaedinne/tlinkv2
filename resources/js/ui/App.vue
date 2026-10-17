<script setup lang="ts">
import { computed, reactive, ref } from 'vue';

type FeedPost = {
  id: number;
  content: string;
  type: string;
  visibility: string;
  sentiment: string;
  published_at: string | null;
  author?: { id: number; name: string };
  attachments: Array<{ url: string; mime_type: string; kind: 'image' | 'video' }>;
  counts: { comments: number; reactions: number; shares: number; views: number };
  reactions: Record<string, number>;
  comments_preview: Array<{ id: number; content: string; author: string }>;
};

type RootData = {
  auth: {
    isAuthenticated: boolean;
    user?: { id: number; name: string; email: string };
  };
  feed: FeedPost[];
  entities: Array<{ id: number; name: string; slug: string }>;
  governorates: Array<{ id: number; name: string; slug: string }>;
  csrf: string;
};

const data = (window.__TLINK__ ?? {
  auth: { isAuthenticated: false },
  feed: [],
  entities: [],
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

const visibilityChoices = [
  { value: 'public', icon: 'fa-earth-africa', title: 'Public' },
  { value: 'authenticated', icon: 'fa-user-group', title: 'Communaute' },
  { value: 'entity', icon: 'fa-building', title: 'Entite' },
  { value: 'department', icon: 'fa-diagram-project', title: 'Departement' },
  { value: 'club', icon: 'fa-people-group', title: 'Club' },
  { value: 'restricted', icon: 'fa-lock', title: 'Restreint' },
];

const sentimentChoices = [
  { value: 'neutral', icon: 'fa-face-meh', title: 'Neutre' },
  { value: 'positive', icon: 'fa-face-smile', title: 'Positif' },
  { value: 'negative', icon: 'fa-face-frown', title: 'Negatif' },
  { value: 'inspired', icon: 'fa-lightbulb', title: 'Inspire' },
  { value: 'grateful', icon: 'fa-hands-praying', title: 'Reconnaissant' },
];

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
  return visibilityChoices.find((item) => item.value === value)?.icon ?? 'fa-user-group';
}

function iconForSentiment(value: string): string {
  return sentimentChoices.find((item) => item.value === value)?.icon ?? 'fa-face-meh';
}
</script>

<template>
  <div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 lg:px-8">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-2xl bg-tlink-blue p-2 text-white shadow-soft">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v13a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 18.5v-13zm3.75 1.25a1 1 0 100 2h8.5a1 1 0 100-2h-8.5zm0 4a1 1 0 100 2h8.5a1 1 0 100-2h-8.5zm0 4a1 1 0 100 2h5.25a1 1 0 100-2H7.75z"/>
            </svg>
          </div>
          <div>
            <p class="font-display text-xl font-semibold text-tlink-blue">T.Link</p>
            <p class="text-xs text-slate-500">Fondation Tunivisions</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <a
            v-if="!data.auth.isAuthenticated"
            href="/connexion"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
            >Connexion</a
          >
          <a
            v-if="!data.auth.isAuthenticated"
            href="/inscription"
            class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700"
            >Inscription</a
          >
          <details v-if="data.auth.isAuthenticated" class="relative">
            <summary class="list-none cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
              <span class="inline-flex items-center gap-2">
                <i class="fa-regular fa-user"></i>
                <span>{{ data.auth.user?.name }}</span>
                <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
              </span>
            </summary>
            <div class="absolute right-0 z-30 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-soft">
              <a href="/profil" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-regular fa-id-card mr-2"></i>Profil</a>
              <a href="/admin" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-list-check mr-2"></i>Roadmap</a>
              <a href="/notifications" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-regular fa-bell mr-2"></i>Notifications</a>
              <form action="/deconnexion" method="post" class="mt-1">
                <input type="hidden" name="_token" :value="data.csrf" />
                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                  <i class="fa-solid fa-right-from-bracket mr-2"></i>Deconnexion
                </button>
              </form>
            </div>
          </details>
        </div>
      </div>
    </header>

    <main class="mx-auto mt-6 w-full max-w-4xl px-4 lg:px-8">
      <section class="space-y-4">
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
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-300 p-1">
              <button
                v-for="item in visibilityChoices"
                :key="item.value"
                type="button"
                :title="item.title"
                class="rounded-lg px-2 py-1 text-sm"
                :class="form.visibility === item.value ? 'bg-slate-100 text-tlink-blue' : 'text-slate-500 hover:bg-slate-50'"
                @click="form.visibility = item.value"
              >
                <i class="fa-solid" :class="item.icon"></i>
              </button>
            </div>
            <input type="hidden" name="sentiment" :value="form.sentiment" />
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-300 p-1">
              <button
                v-for="item in sentimentChoices"
                :key="item.value"
                type="button"
                :title="item.title"
                class="rounded-lg px-2 py-1 text-sm"
                :class="form.sentiment === item.value ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50'"
                @click="form.sentiment = item.value"
              >
                <i class="fa-regular" :class="item.icon"></i>
              </button>
            </div>
          </div>
          <div class="mt-3 flex justify-end">
            <button class="rounded-xl bg-tlink-cyan px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-600" type="submit">
              <i class="fa-solid fa-paper-plane mr-1"></i>
              Publier
            </button>
          </div>
        </form>

        <article v-for="post in latestPosts" :key="post.id" class="card p-4">
          <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">{{ post.author?.name ?? 'Membre Tunivisions' }}</p>
            <div class="flex items-center gap-2">
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-600" :title="post.visibility">
                <i class="fa-solid" :class="iconForVisibility(post.visibility)"></i>
              </span>
              <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-xs text-blue-700" :title="post.sentiment">
                <i class="fa-regular" :class="iconForSentiment(post.sentiment)"></i>
              </span>
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

          <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100" :title="post.type">
              <i class="fa-regular fa-newspaper"></i>
            </span>
            <span>{{ post.published_at ? new Date(post.published_at).toLocaleString('fr-FR') : 'Brouillon' }}</span>
          </div>

          <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
            <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-eye mr-1"></i>{{ post.counts.views }}</span>
            <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-thumbs-up mr-1"></i>{{ post.counts.reactions }}</span>
            <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-regular fa-comment mr-1"></i>{{ post.counts.comments }}</span>
            <span class="rounded-full bg-slate-100 px-2 py-1"><i class="fa-solid fa-share-nodes mr-1"></i>{{ post.counts.shares }}</span>
          </div>

          <div v-if="Object.keys(post.reactions).length" class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
            <span v-for="(count, key) in post.reactions" :key="key" class="rounded-full bg-blue-50 px-2 py-1 text-blue-700" :title="reactionLabels[key] ?? key">
              <i class="fa-regular mr-1" :class="iconForReaction(key)"></i>{{ count }}
            </span>
          </div>

          <div v-if="data.auth.isAuthenticated" class="mt-3 flex flex-wrap gap-2">
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
          </div>

          <form :action="`/posts/${post.id}/view`" method="post" class="mt-2">
            <input type="hidden" name="_token" :value="data.csrf" />
            <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" title="Marquer vue">
              <i class="fa-regular fa-eye"></i>
            </button>
          </form>

          <div v-if="post.comments_preview.length" class="mt-3 space-y-2">
            <div v-for="comment in post.comments_preview" :key="comment.id" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">
              <strong>{{ comment.author }}:</strong> {{ comment.content }}
            </div>
          </div>

          <form v-if="data.auth.isAuthenticated" :action="`/posts/${post.id}/comment`" method="post" class="mt-3 flex gap-2">
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
    </main>
  </div>
</template>
