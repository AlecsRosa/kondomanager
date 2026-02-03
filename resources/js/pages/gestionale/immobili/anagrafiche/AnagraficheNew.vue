<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link, Head, useForm } from '@inertiajs/vue3';
import GestionaleLayout from '@/layouts/GestionaleLayout.vue';
import ImmobileLayout from '@/layouts/gestionale/ImmobileLayout.vue';
import { usePermission } from "@/composables/permissions";
import { Button } from '@/components/ui/button';
import { List, Plus, LoaderCircle, Info} from 'lucide-vue-next';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { useDateConverter } from '@/composables/useDateConverter';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import vSelect from "vue-select";
import { unformat } from 'v-money3';
import MoneyInput from '@/components/MoneyInput.vue'
import type { Building } from '@/types/buildings';
import type { BreadcrumbItem } from '@/types';
import type { Immobile } from '@/types/gestionale/immobili';
import type { Anagrafica } from '@/types/anagrafiche';
import type { DropdownType } from '@/types/dropdown';

const props = defineProps<{
  condominio: Building;
  immobile: Immobile;
  anagrafiche: Anagrafica[];
}>()

const moneyOptions = ref({
  prefix: '',              
  suffix: '',              
  thousands: '.',          
  decimal: ',',          
  precision: 2, 
  disableNegative: false,        
  allowBlank: false,
  masked: true 
})

const { generatePath, generateRoute } = usePermission();
const { toBackend } = useDateConverter();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Gestionale', href: generatePath('gestionale/:condominio', { condominio: props.condominio.id }) },
  { title: props.condominio.nome, href: '#' },
  { title: 'immobili', href: generatePath('gestionale/:condominio/immobili', { condominio: props.condominio.id }) },
  { title: props.immobile.nome, href: generatePath('gestionale/:condominio/immobili/:immobile', { condominio: props.condominio.id, immobile: props.immobile.id }) },
  { title: 'associa anagrafica', href: '#' },
]);

const tipologia = [
  {
      label: 'Proprietario',
      id: 'proprietario',
  },
  {
      label: "Inquilino",
      id: 'inquilino',
  },
  {
      label: "Usufruttuario",
      id: 'usufruttuario',
  }
];

const form = useForm({
  tipologia: '',
  data_inizio: '',
  data_fine: '',
  quota: '',
  saldo_iniziale: '',
  note: '',
  anagrafica_id: '',

});

const statoSaldo = computed(() => {
    // 1. Puliamo il valore da punti e virgole per capire il numero reale
    // Nota: usa le stesse moneyOptions che passo al componente
    const rawValue = unformat(form.saldo_iniziale, moneyOptions.value); 
    const value = Number(rawValue);

    if (value === 0) return null;

    if (value > 0) {
        return {
            label: 'A DEBITO (Deve ancora versare)',
            color: 'text-red-600',
        };
    } else {
        return {
            label: 'A CREDITO (Ha versato in eccedenza)',
            color: 'text-green-600',
        };
    }
});

const submit = () => {

    form.data_inizio = toBackend(form.data_inizio);
    form.data_fine   = toBackend(form.data_fine);
    
    form.post(route(...generateRoute('gestionale.immobili.anagrafiche.store', { condominio: props.condominio.id, immobile: props.immobile.id })), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
        }
    });
};

</script>

<template>

    <Head title="Associa anagrafica immobile" />

    <GestionaleLayout :breadcrumbs="breadcrumbs">

      <ImmobileLayout>

          <form class="space-y-2" @submit.prevent="submit">

            <!-- Action buttons -->
            <div class="flex flex-col lg:flex-row lg:justify-end gap-2 w-full">
              <Button :disabled="form.processing" class="h-8 w-full lg:w-auto">
                <Plus class="w-4 h-4" v-if="!form.processing" />
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Salva
              </Button>

              <Link
                as="button"
                :href="generatePath('gestionale/:condominio/immobili/:immobile/anagrafiche', { condominio: props.condominio.id, immobile: props.immobile.id })"
                class="w-full lg:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary/90"
              >
                <List class="w-4 h-4" />
                <span>Elenco</span>
              </Link>
            </div>

            <Separator class="my-4" />

            <div class="bg-white dark:bg-muted rounded space-y-4 mt-3" >

              <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
  
                <div class="sm:col-span-3">
                  <Label for="tipologia">Tipologia</Label>
                  <v-select
                    class="w-full"
                    :options="tipologia"
                    label="label"
                    v-model="form.tipologia"
                    :reduce="(d: DropdownType) => d.id"
                    placeholder="Seleziona tipologia"
                  />
                  <InputError :message="form.errors.tipologia" />
                </div>

                <div class="sm:col-span-3">
                  <div class="flex items-center gap-1 mb-2">
                    <Label for="anagrafica_id">Anagrafica</Label>
                    
                    <HoverCard>
                      <HoverCardTrigger as-child>
                        <button type="button" class="cursor-pointer flex items-center">
                          <Info class="w-4 h-4 text-muted-foreground hover:text-primary transition-colors" />
                        </button>
                      </HoverCardTrigger>
                      <HoverCardContent class="w-80 z-50">
                        <div class="space-y-3">
                          <h4 class="text-sm font-semibold flex items-center gap-2">
                            <Info class="w-4 h-4" /> Chi visualizzi qui?
                          </h4>
                          <div class="text-sm space-y-2 text-muted-foreground">
                            <p>
                              L'elenco mostra solo le anagrafiche che:
                            </p>
                            <ul class="list-disc pl-4 space-y-1">
                              <li>Sono registrate in <strong>questo Condominio</strong>.</li>
                              <li>NON sono ancora state associate a <strong>questo Immobile</strong>.</li>
                            </ul>
                            <Separator class="my-2"/>
                            
                            <div class="text-xs leading-relaxed">
                              <span class="font-semibold text-foreground">Non trovi la persona?</span><br>
                              Vai alla
                              <Link 
                                :href="generatePath('anagrafiche')" 
                                class="text-primary font-medium hover:underline hover:text-primary/80 transition-colors inline-flex items-center gap-0.5"
                              >
                                gestione anagrafiche
                              </Link>
                              per crearla, poi torna qui.
                            </div>

                          </div>
                        </div>
                      </HoverCardContent>
                    </HoverCard>
                  </div>

                  <v-select
                    id="anagrafica_id"
                    class="w-full"
                    :options="anagrafiche"
                    v-model="form.anagrafica_id"
                    :reduce="(d: Anagrafica) => d.id"
                    label="nome"
                    placeholder="Cerca o seleziona anagrafica"
                  >
                    <template #option="{ nome, indirizzo }">
                      <div class="flex flex-col py-1">
                        <span class="font-medium text-sm">{{ nome }}</span>
                        <span class="text-xs text-muted-foreground">{{ indirizzo }}</span>
                      </div>
                    </template>

                    <template #selected-option="{ nome, indirizzo }">
                      <div class="flex items-center gap-2 text-sm">
                        <span class="font-medium">{{ nome }}</span>
                        <span class="text-muted-foreground" v-if="indirizzo">– {{ indirizzo }}</span>
                      </div>
                    </template>
                  </v-select>

                  <InputError :message="form.errors.anagrafica_id" />
                </div>

              </div>

              <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
     
                <div class="sm:col-span-3">
                  <Label for="quota">Quota</Label>

                  <HoverCard>
                    <HoverCardTrigger as-child>
                      <button type="button" class="cursor-pointer">
                        <Info class="ml-1 w-4 h-4 text-muted-foreground" />
                      </button>
                    </HoverCardTrigger>
                    <HoverCardContent class="w-80 z-50">
                      <div class="flex justify-between space-x-4">
                        <div class="space-y-1">
                          <h4 class="text-sm font-semibold">Quota anagrafica</h4>
                          <p class="text-sm">
                            In questo campo puoi inserire la quota di proprietà dell'anagrafica
                          </p>
                        </div>
                      </div>
                    </HoverCardContent>
                  </HoverCard>
                  
                  <Input
                    id="quota" 
                    placeholder="Quota anagrafica" 
                    v-model="form.quota" 
                    v-on:focus="form.clearErrors('quota')"
                  />

                  <InputError :message="form.errors.quota" />
                </div>

                <div class="sm:col-span-3">
                  <Label for="saldo">Saldo esercizio precedente</Label>

                  <HoverCard>
                    <HoverCardTrigger as-child>
                      <button type="button" class="cursor-pointer">
                        <Info class="ml-1 w-4 h-4 text-muted-foreground" />
                      </button>
                    </HoverCardTrigger>
                    <HoverCardContent class="w-80 z-50">
                      <div class="space-y-2">
                        <h4 class="text-sm font-semibold">Guida al Saldo</h4>
                        <div class="text-sm space-y-2">
                          <p>
                            <span class="font-bold text-red-600">Valore Positivo:</span><br>
                            Indica un <strong>Debito</strong>. L'anagrafica non ha saldato tutto l'anno precedente.
                          </p>
                          <div class="border-t my-2"></div>
                          <p>
                            <span class="font-bold text-green-600">Valore Negativo (col meno -):</span><br>
                            Indica un <strong>Credito</strong>. L'anagrafica ha pagato più del dovuto l'anno scorso.
                          </p>
                        </div>
                      </div>
                    </HoverCardContent>
                  </HoverCard>

                  <MoneyInput
                    id="saldo"
                    v-model="form.saldo_iniziale"
                    :money-options="moneyOptions"
                    placeholder="0,00"
                    @focus="form.clearErrors('saldo_iniziale')"
                  />

                  <InputError :message="form.errors.saldo_iniziale" />

                  <div v-if="statoSaldo" 
                      :class="['mt-2 text-xs font-medium transition-all', statoSaldo.color]">
                      {{ statoSaldo.label }}
                  </div>

                  <p v-else class="text-xs text-muted-foreground mt-1">
                    Usa il segno <strong>-</strong> (meno) per i crediti a favore dell'anagrafica.
                  </p>
                </div>

              </div>

              <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                  <Label for="data_nascita">Data inizio</Label>
                  <VueDatePicker
                    v-model="form.data_inizio"
                    class="w-full"
                    format="dd/MM/yyyy"
                    locale="it"
                    :enable-time-picker="false"
                    auto-apply
                    placeholder="Data inizio"
                  />
                  <InputError :message="form.errors.data_inizio" />
                </div>

                <div class="sm:col-span-3">
                  <Label for="data_nascita">Data fine</Label>
                  <VueDatePicker
                    v-model="form.data_fine"
                    class="w-full"
                    format="dd/MM/yyyy"
                    locale="it"
                    :enable-time-picker="false"
                    auto-apply
                    placeholder="Data fine"
                  />
                  <InputError :message="form.errors.data_fine" />
                </div>

              </div>

              <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                  <div class="sm:col-span-6">
                    <Label for="note">Note</Label>
                    <Textarea 
                        id="note" 
                        placeholder="Inserisci una nota qui" 
                        v-model="form.note" 
                        v-on:focus="form.clearErrors('note')"
                    />

                    <InputError :message="form.errors.note" />
                  </div>

              </div>

            </div>

          </form>

      </ImmobileLayout>

    </GestionaleLayout>

  </template>

  <style src="vue-select/dist/vue-select.css"></style>