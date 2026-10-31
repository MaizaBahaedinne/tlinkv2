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

const props = defineProps<{ entries: CalendarEntry[]; csrf: string; challengesUrl?: string }>();

const pad = (value: number) => String(value).padStart(2, '0');
const dayKey = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const today = dayKey(new Date());

const cursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const selected = ref<string | null>(null);

const byDay = computed(() => {
  const map = new Map<string, CalendarEntry[]>();
  for (const entry of props.entries) {
    map.set(entry.day, [...(map.get(entry.day) ?? []), entry]);
  }
  return map;
});

const monthLabel = computed(() => cursor.value.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }));

// Grille du mois, semaine commençant le lundi.
const cells = computed(() => {
  const year = cursor.value.getFullYear();
  const month = cursor.value.getMonth();
  const offset = (new Date(year, month, 1).getDay() + 6) % 7;
  const total = new Date(year, month + 1, 0).getDate();
  const list: Array<{ key: string; day: number } | null> = Array.from({ length: offset }, () => null);
  for (let day = 1; day <= total; day += 1) {
    list.push({ key: `${year}-${pad(month + 1)}-${pad(day)}`, day });
  }
  return list;
});

const monthPrefix = computed(() => `${cursor.value.getFullYear()}-${pad(cursor.value.getMonth() + 1)}`);

// Jour choisi, sinon les prochaines activités du mois affiché.
const listed = computed(() => {
  if (selected.value) {
    return byDay.value.get(selected.value) ?? [];
  }
  return props.entries.filter((entry) => entry.day.startsWith(monthPrefix.value) && entry.day >= today);
});

const heading = computed(() => {
  if (!selected.value) {
    return 'Prochaines activités du mois';
  }
  const [year, month, day] = selected.value.split('-').map(Number);
  return new Date(year, month - 1, day).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
});

function shiftMonth(step: number) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + step, 1);
  selected.value = null;
}

function pick(key: string) {
  selected.value = selected.value === key ? null : key;
}

function dayLabel(entry: CalendarEntry) {
  const [year, month, day] = entry.day.split('-').map(Number);
  return `${new Date(year, month - 1, day).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })} · ${entry.time}`;
}
</script>

<template>
  <section class="card p-4" aria-label="Calendrier des activités">
    <div class="mb-3 flex items-center justify-between">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Calendrier</p>
      <div class="flex items-center gap-1">
        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Mois précédent" @click="shiftMonth(-1)">
          <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
        </button>
        <span class="min-w-[7.5rem] text-center text-sm font-semibold capitalize text-slate-800">{{ monthLabel }}</span>
        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Mois suivant" @click="shiftMonth(1)">
          <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-7 gap-y-1 text-center text-[11px] font-semibold uppercase text-slate-400" aria-hidden="true">
      <span v-for="(name, index) in ['L', 'M', 'M', 'J', 'V', 'S', 'D']" :key="index">{{ name }}</span>
    </div>
    <div class="mt-1 grid grid-cols-7 gap-1">
      <template v-for="(cell, index) in cells" :key="index">
        <span v-if="cell === null"></span>
        <button
          v-else
          type="button"
          class="relative flex h-8 items-center justify-center rounded-lg text-xs"
          :class="[
            selected === cell.key ? 'bg-tlink-blue font-semibold text-white' : cell.key === today ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-700 hover:bg-slate-50',
            byDay.has(cell.key) && selected !== cell.key ? 'font-semibold' : '',
          ]"
          :aria-pressed="selected === cell.key"
          :aria-label="`${cell.day}${byDay.has(cell.key) ? ', ' + byDay.get(cell.key)?.length + ' activité(s)' : ''}`"
          @click="pick(cell.key)"
        >
          {{ cell.day }}
          <span v-if="byDay.has(cell.key)" class="absolute bottom-0.5 h-1 w-1 rounded-full" :class="selected === cell.key ? 'bg-white' : 'bg-tlink-blue'"></span>
        </button>
      </template>
    </div>

    <p class="mb-2 mt-4 text-xs font-semibold capitalize text-slate-500">{{ heading }}</p>
    <div v-if="listed.length === 0" class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500">
      <p v-if="props.entries.length === 0">
        Aucune activité à venir pour votre profil. Elles apparaissent ici dès qu’un club en publie dans vos périmètres.
      </p>
      <p v-else>Aucune activité {{ selected ? 'ce jour-là' : 'à venir ce mois-ci' }}.</p>
      <a v-if="props.challengesUrl" :href="props.challengesUrl" class="mt-2 inline-block font-semibold text-tlink-blue hover:underline">Voir les défis</a>
    </div>

    <ul v-else class="space-y-2">
      <li v-for="entry in listed" :key="entry.id" class="rounded-xl border border-slate-200 p-3">
        <a :href="entry.url" class="text-sm font-semibold text-slate-900 hover:text-tlink-blue">{{ entry.title }}</a>
        <p class="mt-0.5 text-xs text-slate-500">{{ dayLabel(entry) }}<span v-if="entry.location"> · {{ entry.location }}</span></p>
        <p class="text-xs text-slate-500">{{ entry.type }}<span v-if="entry.club"> · {{ entry.club }}</span><span v-if="entry.capacity !== null"> · {{ entry.registered }}/{{ entry.capacity }} places</span></p>

        <div class="mt-2">
          <template v-if="entry.state === 'registered'">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Inscrit</span>
            <a v-if="entry.scan_url" :href="entry.scan_url" class="ml-2 text-xs font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scanner</a>
          </template>
          <span v-else-if="entry.state === 'waitlisted'" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><i class="fa-regular fa-clock" aria-hidden="true"></i> Liste d’attente</span>
          <span v-else-if="entry.state === 'closed'" class="text-xs text-slate-500">Inscriptions closes</span>
          <a v-else-if="entry.needs_form" :href="entry.url" class="inline-flex rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">
            S’inscrire
          </a>
          <form v-else :action="entry.register_url" method="post">
            <input type="hidden" name="_token" :value="props.csrf" />
            <button type="submit" class="rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">
              {{ entry.state === 'full' ? 'Rejoindre la liste d’attente' : 'S’inscrire' }}
            </button>
          </form>
        </div>
      </li>
    </ul>
  </section>
</template>
