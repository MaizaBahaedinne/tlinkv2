<script setup lang="ts">
import { computed, ref } from 'vue';

type CalendarEntry = {
  id: number;
  title: string;
  club: string | null;
  type: string;
  day: string;
  time: string;
  location: string | null;
  state: 'open' | 'full' | 'closed' | 'registered' | 'waitlisted';
  needs_form: boolean;
  registered: number;
  capacity: number | null;
  url: string;
  register_url: string;
  scan_url: string | null;
};

const VISIBLE = 4;

const props = defineProps<{ entries: CalendarEntry[]; csrf: string; challengesUrl?: string }>();

const expanded = ref(false);
const shown = computed(() => (expanded.value ? props.entries : props.entries.slice(0, VISIBLE)));
const hidden = computed(() => Math.max(0, props.entries.length - VISIBLE));

function when(entry: CalendarEntry) {
  const [year, month, day] = entry.day.split('-').map(Number);
  return `${new Date(year, month - 1, day).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })} · ${entry.time}`;
}
</script>

<template>
  <section class="card p-4" aria-label="Activités à venir">
    <div class="mb-3 flex items-center justify-between">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activités à venir</p>
      <a v-if="props.challengesUrl" :href="props.challengesUrl" class="text-xs font-semibold text-tlink-blue hover:underline">Défis</a>
    </div>

    <p v-if="props.entries.length === 0" class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500">
      Aucune activité publique à venir pour le moment.
    </p>

    <ul v-else class="space-y-2">
      <li v-for="entry in shown" :key="entry.id" class="rounded-xl border border-slate-200 p-3">
        <a :href="entry.url" class="text-sm font-semibold text-slate-900 hover:text-tlink-blue">{{ entry.title }}</a>
        <p class="mt-0.5 text-xs text-slate-500">{{ when(entry) }}<span v-if="entry.location"> · {{ entry.location }}</span></p>
        <p class="text-xs text-slate-500">{{ entry.type }}<span v-if="entry.club"> · {{ entry.club }}</span><span v-if="entry.capacity !== null"> · {{ entry.registered }}/{{ entry.capacity }} places</span></p>

        <div class="mt-2">
          <template v-if="entry.state === 'registered'">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Inscrit</span>
            <a v-if="entry.scan_url" :href="entry.scan_url" class="ml-2 text-xs font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scanner</a>
          </template>
          <span v-else-if="entry.state === 'waitlisted'" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><i class="fa-regular fa-clock" aria-hidden="true"></i> Liste d’attente</span>
          <span v-else-if="entry.state === 'closed'" class="text-xs text-slate-500">Inscriptions closes</span>
          <a v-else-if="entry.needs_form" :href="entry.url" class="inline-flex rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">S’inscrire</a>
          <form v-else :action="entry.register_url" method="post">
            <input type="hidden" name="_token" :value="props.csrf" />
            <button type="submit" class="rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">
              {{ entry.state === 'full' ? 'Rejoindre la liste d’attente' : 'S’inscrire' }}
            </button>
          </form>
        </div>
      </li>
    </ul>

    <button
      v-if="hidden > 0"
      type="button"
      class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
      :aria-expanded="expanded"
      @click="expanded = !expanded"
    >
      {{ expanded ? 'Voir moins' : `Voir les ${hidden} autres activités` }}
    </button>
  </section>
</template>
