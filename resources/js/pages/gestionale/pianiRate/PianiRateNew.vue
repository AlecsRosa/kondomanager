<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import GestionaleLayout from '@/layouts/GestionaleLayout.vue';
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import InputError from '@/components/InputError.vue'
import { Checkbox } from '@/components/ui/checkbox'
import { Plus, LoaderCircle, List, AlertTriangle, CheckCircle } from 'lucide-vue-next'
import vSelect from 'vue-select'
import axios from 'axios';
import { usePermission } from '@/composables/permissions'
import type { Building } from '@/types/buildings'
import type { Esercizio } from '@/types/gestionale/esercizi'
import type { Gestione } from '@/types/gestionale/gestioni'

const props = defineProps<{
  condominio: Building
  esercizio: Esercizio
  gestioni: Gestione[]
  saldoInfo: {
    saldo: number
    applicabile: boolean
    motivo: string
    gestione_utilizzatrice?: any
    is_primo_anno?: boolean
  }
}>()

const { generateRoute, generatePath } = usePermission()

const showRecurrence = ref(false)
const capitoliDisponibili = ref([]);
const isLoadingCapitoli = ref(false);

const frequencies = [
  { label: 'Mensile', value: 'MONTHLY' },
  { label: 'Settimanale', value: 'WEEKLY' },
  { label: 'Annuale', value: 'YEARLY' }
]

const weekdays = [
  { label: 'Lunedì', value: 'MO' },
  { label: 'Martedì', value: 'TU' },
  { label: 'Mercoledì', value: 'WE' },
  { label: 'Giovedì', value: 'TH' },
  { label: 'Venerdì', value: 'FR' },
  { label: 'Sabato', value: 'SA' },
  { label: 'Domenica', value: 'SU' }
]

const form = useForm({
  gestione_id: '',
  nome: '',
  descrizione: '',
  metodo_distribuzione: 'prima_rata',
  numero_rate: 12,
  giorno_scadenza: 5,
  note: '',
  genera_subito: true,
  recurrence_enabled: false,
  recurrence_frequency: 'MONTHLY',
  recurrence_interval: 1,
  recurrence_by_day: [] as string[], 
  capitoli_ids: [] as number[], 
})

// REATTIVITÀ SALDO
const mostraDistribuzioneSaldo = computed(() => {
  return props.saldoInfo.applicabile && props.saldoInfo.saldo !== 0;
});

watch(() => form.gestione_id, async (newGestioneId) => {
  form.capitoli_ids = [];
  capitoliDisponibili.value = [];
  if (!newGestioneId) return;

  isLoadingCapitoli.value = true;
  try {
    const response = await axios.get(route('admin.gestionale.fetch-capitoli-gestione', {
      condominio: props.condominio.id
    }), { params: { gestione_id: newGestioneId } });
    capitoliDisponibili.value = response.data;
  } catch (error) {
    console.error("Errore caricamento capitoli:", error);
  } finally {
    isLoadingCapitoli.value = false;
  }
});

watch(showRecurrence, (enabled) => {
  form.recurrence_enabled = enabled
  if (!enabled) form.recurrence_by_day = []
})

const usingByDay = computed(() =>
  showRecurrence.value &&
  Array.isArray(form.recurrence_by_day) &&
  form.recurrence_by_day.length > 0
)

const toggleDay = (dayValue: string) => {
  const index = form.recurrence_by_day.indexOf(dayValue);
  if (index > -1) {
    form.recurrence_by_day.splice(index, 1); // Rimuove se presente
  } else {
    form.recurrence_by_day.push(dayValue); // Aggiunge se assente
  }
}

const submit = () => {
  form.post(route(...generateRoute(
    'gestionale.esercizi.piani-rate.store',
    { condominio: props.condominio.id, esercizio: props.esercizio.id }
  )), {
    preserveScroll: true,
    onSuccess: () => form.reset()
  })
}
</script>

<template>
  <Head title="Crea nuovo piano rate" />

  <GestionaleLayout>
    <div class="px-4 py-6 w-full">
      <div class="w-full shadow ring-1 ring-black/5 md:rounded-lg p-6 bg-white dark:bg-slate-900">
        
        <form @submit.prevent="submit" class="space-y-6">
          
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b pb-4">
            <div>
              <h1 class="text-xl font-bold">Nuovo piano rate</h1>
              <p class="text-sm text-gray-500">Nuovo piano rate per {{ esercizio.nome }}</p>
            </div>
            <div class="flex gap-2">
              <Button :disabled="form.processing" class="h-9">
                <Plus class="w-4 h-4" v-if="!form.processing" />
                <LoaderCircle v-else class="h-4 w-4 animate-spin" />
                Salva piano rate
              </Button>
              <Link
                as="button"
                :href="generatePath('gestionale/:condominio/esercizi/:esercizio/piani-rate', {
                  condominio: condominio.id,
                  esercizio: esercizio.id
                })"
                class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-white shadow-sm"
              >
                <List class="w-4 h-4" />
                Elenco piani
              </Link>
            </div>
          </div>

          <div v-if="!saldoInfo.is_primo_anno">
            <div v-if="saldoInfo.applicabile" 
                 class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <CheckCircle class="w-5 h-5 text-blue-600 shrink-0" />
                <div>
                  <h4 class="font-bold text-blue-900 dark:text-blue-100 text-sm">Saldi anagrafiche iniziali disponibili</h4>
                  <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">{{ saldoInfo.motivo }}</p>
                </div>
              </div>
            </div>

            <div v-else 
                 class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0" />
                <div>
                  <h4 class="font-bold text-amber-900 dark:text-amber-100 text-sm">Saldi anagrafiche non disponibili</h4>
                  <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">{{ saldoInfo.motivo }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="nome" class="text-sm font-semibold">Nome piano rate</Label>
                <Input id="nome" class="w-full" v-model="form.nome" placeholder="Es. Ordinario 2026" />
                <InputError :message="form.errors.nome" />
              </div>

              <div class="space-y-2">
                <Label for="gestione_id" class="text-sm font-semibold">Gestione</Label>
                <v-select
                  class="w-full v-select-custom"
                  id="gestione_id"
                  :options="gestioni"
                  label="nome"
                  v-model="form.gestione_id"
                  :reduce="g => g.id"
                  placeholder="Seleziona gestione"
                />
                <InputError :message="form.errors.gestione_id" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="descrizione" class="text-sm font-semibold text-gray-600 dark:text-gray-400">
                Descrizione / Note interne
              </Label>
              <Textarea 
                id="descrizione" 
                class="w-full min-h-[80px] resize-none" 
                v-model="form.descrizione" 
                placeholder="Aggiungi dettagli sul criterio di riparto o note per l'ufficio..."
              />
            </div>
          </div>

          <div class="bg-gray-50 dark:bg-slate-800/50 p-4 rounded-lg border relative">
            <Label :class="{ 'opacity-50': !form.gestione_id }">Filtra per capitoli di spesa (Opzionale)</Label>
            
            <div class="relative mt-2">
              <v-select
                multiple
                v-model="form.capitoli_ids"
                :options="capitoliDisponibili"
                label="nome"
                :reduce="c => c.id"
                :disabled="!form.gestione_id || isLoadingCapitoli"
                :placeholder="!form.gestione_id ? 'Seleziona prima una gestione...' : 'Includi tutto il bilancio'"
                class="w-full v-select-custom"
              >
                <template #spinner>
                  <LoaderCircle v-if="isLoadingCapitoli" class="w-4 h-4 animate-spin mr-2" />
                </template>
              </v-select>
            </div>
            <p class="text-[11px] text-gray-500 mt-2 italic">Seleziona voci specifiche se vuoi un piano rate parziale.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            <div>
              <Label>Numero rate</Label>
              <Input v-model.number="form.numero_rate" class="mt-1 w-full" />
              <InputError :message="form.errors.numero_rate" />
            </div>
            <div v-if="!usingByDay">
              <Label>Giorno scadenza</Label>
              <Input v-model.number="form.giorno_scadenza" class="mt-1 w-full" />
            </div>
            <div v-if="mostraDistribuzioneSaldo">
              <Label class="text-blue-600 font-semibold">Distribuzione saldo iniziale</Label>
              <v-select
                class="mt-1 w-full v-select-custom"
                :options="[{label: 'Sulla prima rata', value: 'prima_rata'}, {label: 'Su tutte le rate', value: 'tutte_rate'}]"
                v-model="form.metodo_distribuzione"
                :reduce="opt => opt.value"
                :clearable="false"
              />
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-6 pt-4 border-t">
            <div class="flex items-center gap-2">
              <Checkbox id="genera_subito" v-model="form.genera_subito" />
              <Label for="genera_subito" class="cursor-pointer font-medium">Genera subito le rate</Label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox id="recurrenceToggle" v-model="showRecurrence" />
              <Label for="recurrenceToggle" class="cursor-pointer font-medium text-primary">Ricorrenza automatica avanzata</Label>
            </div>
          </div>

          <div v-if="showRecurrence" class="bg-blue-50/50 dark:bg-blue-900/10 p-5 rounded-lg border border-blue-200 space-y-4 animate-in slide-in-from-top-2 duration-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <Label>Frequenza</Label>
                <v-select :options="frequencies" v-model="form.recurrence_frequency" :reduce="o => o.value" class="mt-1 bg-white v-select-custom" />
              </div>
              <div>
                <Label>Intervallo</Label>
                <Input min="1" v-model="form.recurrence_interval" class="mt-1 w-full bg-white" />
              </div>
            </div>

            <div>
                <Label class="mb-3 block text-xs font-bold uppercase tracking-wider text-gray-500">
                  Giorni della settimana
                </Label>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                  <div 
                    v-for="day in weekdays" 
                    :key="day.value" 
                    class="flex items-center gap-2 bg-white dark:bg-slate-900 p-3 rounded-lg border shadow-sm hover:border-primary/50 transition-colors cursor-pointer"
                    @click="toggleDay(day.value)"
                  >
                    <Checkbox 
                      :id="'day-' + day.value" 
                      :checked="form.recurrence_by_day.includes(day.value)"
                      @update:checked="toggleDay(day.value)"
                    />
                    <label 
                      :for="'day-' + day.value" 
                      class="text-xs font-medium cursor-pointer select-none"
                    >
                      {{ day.label }}
                    </label>
                  </div>
                </div>
                <InputError :message="form.errors.recurrence_by_day" class="mt-2" />
              </div>
            </div>

          <div class="pt-2 border-t">
            <Label for="note">Note aggiuntive per il documento</Label>
            <Textarea id="note" v-model="form.note" class="mt-1 w-full" />
          </div>

        </form>
      </div>
    </div>
  </GestionaleLayout>
</template>

<style src="vue-select/dist/vue-select.css"></style>