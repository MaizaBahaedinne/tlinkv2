<script setup lang="ts">
import { computed, reactive } from 'vue';

type FeedPost = {
  id: number;
  content: string;
  type: string;
  visibility: string;
  published_at: string | null;
  author?: { id: number; name: string };
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
          <a
            v-if="data.auth.isAuthenticated"
            href="/profil"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
            >Profil</a
          >
        </div>
      </div>
    </header>

    <main class="mx-auto mt-6 grid w-full max-w-7xl grid-cols-1 gap-6 px-4 lg:grid-cols-12 lg:px-8">
      <aside class="space-y-4 lg:col-span-3">
        <section class="card p-4">
          <div class="mb-4 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-tlink-blue text-sm font-semibold text-white">
              {{ initials }}
            </div>
            <div>
              <p class="font-semibold">{{ data.auth.user?.name ?? 'Visiteur' }}</p>
              <p class="text-xs text-slate-500">Reseau Fondation Tunivisions</p>
            </div>
          </div>
          <p class="text-sm text-slate-600">
            {{ data.auth.isAuthenticated ? 'Connecte a la communaute T.Link.' : 'Connecte-toi pour publier, reagir et commenter.' }}
          </p>
        </section>

        <section class="card p-4">
          <h2 class="mb-2 font-display text-base font-semibold text-tlink-blue">Entites</h2>
          <ul class="space-y-1 text-sm text-slate-600">
            <li v-for="entity in data.entities" :key="entity.id">{{ entity.name }}</li>
          </ul>
        </section>
      </aside>

      <section class="space-y-4 lg:col-span-6">
        <form v-if="data.auth.isAuthenticated" action="/posts" method="post" class="card p-4">
          <input type="hidden" name="_token" :value="data.csrf" />
          <label for="composer" class="mb-2 block text-sm font-medium text-slate-700">Publier une actualite</label>
          <textarea
            id="composer"
            v-model="form.content"
            name="content"
            rows="4"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue"
            placeholder="Partage une actualite de ton club, projet ou evenement..."
            required
          ></textarea>
          <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <select
              v-model="form.visibility"
              name="visibility"
              class="rounded-xl border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="public">Public</option>
              <option value="authenticated">Communaute connectee</option>
              <option value="entity">Entite</option>
              <option value="department">Departement</option>
              <option value="club">Club</option>
              <option value="restricted">Audience restreinte</option>
            </select>
            <button class="rounded-xl bg-tlink-cyan px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-600" type="submit">
              Publier
            </button>
          </div>
        </form>

        <article v-for="post in latestPosts" :key="post.id" class="card p-4">
          <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">{{ post.author?.name ?? 'Membre Tunivisions' }}</p>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ post.visibility }}</span>
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ post.content }}</p>
          <div class="mt-3 flex items-center gap-3 text-xs text-slate-500">
            <span>{{ post.type }}</span>
            <span>{{ post.published_at ? new Date(post.published_at).toLocaleString('fr-FR') : 'Brouillon' }}</span>
          </div>
        </article>

        <div v-if="latestPosts.length === 0" class="card p-6 text-center text-sm text-slate-500">
          Aucun post pour le moment.
        </div>
      </section>

      <aside class="space-y-4 lg:col-span-3">
        <section class="card p-4">
          <h2 class="mb-2 font-display text-base font-semibold text-tlink-blue">We Are Carthage</h2>
          <p class="mb-3 text-xs text-slate-500">24 gouvernorats configures</p>
          <ul class="grid grid-cols-2 gap-1 text-xs text-slate-600">
            <li v-for="gov in data.governorates" :key="gov.id">{{ gov.name }}</li>
          </ul>
        </section>

        <section class="card bg-gradient-to-br from-tlink-blue to-tlink-blue-700 p-4 text-white">
          <h3 class="font-display text-lg font-semibold">Prochaine etape</h3>
          <p class="mt-2 text-sm text-blue-100">
            Validation des memberships, roles scopes, et modules projets/evenements.
          </p>
        </section>
      </aside>
    </main>
  </div>
</template>
