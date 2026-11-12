<script setup lang="ts">
type Item = { id: number; name: string; photo_url: string | null; position: string | null; ago: string | null };

const props = defineProps<{ total: number; items: Item[]; url: string }>();

function initials(name: string) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}
</script>

<template>
  <section class="card p-4" aria-label="Demandes d’adhésion en attente">
    <div class="mb-3 flex items-center justify-between">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adhésions en attente</p>
      <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ props.total }}</span>
    </div>

    <p v-if="props.items.length === 0" class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500">Aucune demande en attente.</p>

    <ul v-else class="space-y-3">
      <li v-for="item in props.items" :key="item.id" class="flex items-center gap-3">
        <img v-if="item.photo_url" :src="item.photo_url" :alt="item.name" class="h-10 w-10 shrink-0 rounded-full object-cover" loading="lazy" />
        <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tlink-blue/10 text-xs font-bold text-tlink-blue" aria-hidden="true">{{ initials(item.name) }}</span>
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-slate-900">{{ item.name }}</p>
          <p v-if="item.position" class="truncate text-xs text-slate-600">{{ item.position }}</p>
          <p v-if="item.ago" class="text-xs text-slate-400">{{ item.ago }}</p>
        </div>
      </li>
    </ul>

    <a v-if="props.items.length" :href="props.url" class="mt-3 block w-full rounded-xl border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-50">
      {{ props.total > props.items.length ? `Voir les ${props.total} demandes` : 'Traiter les demandes' }}
    </a>
  </section>
</template>
