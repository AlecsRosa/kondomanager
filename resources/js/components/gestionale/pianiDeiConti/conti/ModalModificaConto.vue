<script setup lang="ts">

import { useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import { useCapitoliConti, type CapitoloDropdown } from '@/composables/useCapitoliConti'
import vSelect from 'vue-select'
import MoneyInput from '@/components/MoneyInput.vue'
import { Lock, AlertTriangle } from 'lucide-vue-next' // Aggiunta icona Lock
import type { Conto } from '@/types/gestionale/conti'

interface Emits {
  (e: 'update:show', value: boolean): void
  (e: 'success'): void
}

interface Props {
  show: boolean
  conto: Conto | null
  condominioId: number
  esercizioId: number
  pianoContoId: number
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isCapitolo = ref(false)
const isSottoConto = ref(false)
const { capitoli, isLoading: isLoadingCapitoli, fetchCapitoliConti } = useCapitoliConti()

const moneyOptions = ref({
  prefix: '',              
  suffix: '',              
  thousands: '.',          
  decimal: ',',          
  precision: 2,            
  allowBlank: false,
  masked: true 
})

const form = useForm({
  nome: '',
  tipo: 'spesa' as 'spesa' | 'entrata',
  importo: '',
  descrizione: '',
  note: '',
  parent_id: null as number | null,
  isCapitolo: false,
  isSottoConto: false,
})

// Carica i dati quando il modal si apre
watch(() => props.show, (newVal) => {
  if (newVal && props.conto) {
    fetchCapitoliConti(props.condominioId, props.pianoContoId)
  }
})

// Funzione per trovare l'oggetto capitolo per ID
const findCapitoloById = (id: number | null) => {
  if (!id) return null
  return capitoli.value.find(c => c.id === id) || null
}

const selectedCapitolo = computed({
  get: () => findCapitoloById(form.parent_id),
  set: (val: CapitoloDropdown | null) => {
    form.parent_id = val ? val.id : null
  }
})

const extractNumericValue = (importoFormattato: string): number => {
  if (!importoFormattato) return 0
  const numericString = importoFormattato
    .replace('€', '')
    .replace(/\s/g, '')
    .replace(/\./g, '') 
    .replace(',', '.') 
  return parseFloat(numericString) || 0
}

const isContoCapitolo = computed(() => {
  if (!props.conto) return false
  const importoNumerico = extractNumericValue(props.conto.importo)
  const hasZeroImporto = importoNumerico === 0
  const hasSottoconti = props.conto.sottoconti && props.conto.sottoconti.length > 0
  return (hasZeroImporto && hasSottoconti) || (hasZeroImporto && !props.conto.parent_id)
})

// *** NUOVA COMPUTED PROPERTY PER IL BLOCCO ***
const isImportoLocked = computed(() => {
  // È bloccato se il backend dice che ha rate emesse (aggiungi questa prop al tipo TypeScript se manca)
  // @ts-ignore (ignora errore TS finché non aggiorni l'interfaccia Conto)
  return props.conto?.has_rate_emesse === true;
})

watch(() => props.conto, (newConto) => {
  if (newConto) {
    form.nome = newConto.nome
    form.tipo = newConto.tipo
    form.descrizione = newConto.descrizione || ''
    form.note = newConto.note || ''
    form.parent_id = newConto.parent_id
    
    isCapitolo.value = isContoCapitolo.value
    isSottoConto.value = !!newConto.parent_id
    form.isCapitolo = isContoCapitolo.value
    form.isSottoConto = !!newConto.parent_id
    
    if (!isContoCapitolo.value) {
      form.importo = newConto.importo
    } else {
      form.importo = ''
    }
  }
}, { immediate: true })

watch(isCapitolo, (val) => {
  if (val) {
    isSottoConto.value = false
    form.parent_id = null
    form.importo = ''
  }
  form.isCapitolo = val 
})

watch(isSottoConto, (val) => {
  if (val) {
    isCapitolo.value = false
    form.isCapitolo = false
  }
  form.isSottoConto = val 
})

const closeModal = () => {
  emit('update:show', false)
}

const resetForm = () => {
  form.reset()
  isCapitolo.value = false
  isSottoConto.value = false
}

const onDropdownCapitoliOpen = () => {
  if (capitoli.value.length === 0) {
    fetchCapitoliConti(props.condominioId, props.pianoContoId)
  }
}

const submit = () => {
  if (!props.conto) return

  const routeParams = {
    condominio: props.condominioId,
    esercizio: props.esercizioId,
    pianoConto: props.pianoContoId,
    conto: props.conto.id,
  }

  form.transform((data) => ({
    ...data,
    // Se è bloccato, non inviamo l'importo modificato (o inviamo quello vecchio per sicurezza)
    importo: isCapitolo.value ? 0 : data.importo,
    parent_id: isSottoConto.value ? data.parent_id : null,
  })).put(route('admin.gestionale.esercizi.piani-conti.conti.update', routeParams), {
    preserveScroll: true,
    onSuccess: () => {
      resetForm()
      emit('success')
      closeModal()
    },
    onError: (errors) => {
      console.error('Errore nella modifica:', errors)
    },
  })
}
</script>

<template>


  <Dialog v-model:open="props.show" @update:open="closeModal">
    <DialogContent class="sm:max-w-[650px]">
      <DialogHeader>
        <DialogTitle>Modifica voce di spesa</DialogTitle>
      </DialogHeader>

      <div class="grid gap-4 py-4 overflow-y-auto px-6">
        <div class="flex flex-col justify-between h-[60dvh]">

          <form v-if="props.conto" @submit.prevent="submit" class="space-y-4 mt-4">
            <input type="hidden" v-model="form.isCapitolo" />
            <input type="hidden" v-model="form.isSottoConto" />

            <div>
              <Label for="nome">Nome</Label>
              <Input id="nome" v-model="form.nome" placeholder="Es. Spese ascensore" />
              <InputError :message="form.errors.nome" />
            </div>

            <div>
              <Label for="descrizione">Descrizione</Label>
              <Textarea id="descrizione" v-model="form.descrizione" placeholder="Descrizione..." />
            </div>

            <div v-if="!isCapitolo" class="flex items-center gap-6 pb-2">
              <Label class="font-medium">Tipo di spesa</Label>
              <div class="flex items-center gap-2">
                <input type="radio" id="spesa" value="spesa" v-model="form.tipo" />
                <Label for="spesa">Spesa (uscita)</Label>
              </div>
              <div class="flex items-center gap-2">
                <input type="radio" id="entrata" value="entrata" v-model="form.tipo" />
                <Label for="entrata">Entrata</Label>
              </div>
            </div>

            <div v-if="isSottoConto">
              <Label>Capitolo padre</Label>
              <v-select
                :options="capitoli"
                label="nome"
                v-model="selectedCapitolo"
                placeholder="Seleziona capitolo padre"
                :reduce="(c: CapitoloDropdown) => c"
                @open="onDropdownCapitoliOpen"
                :loading="isLoadingCapitoli"
                :clearable="true"
              >
              </v-select>
              <InputError :message="form.errors.parent_id" />
            </div>

            <div v-if="!isCapitolo">
              <div class="flex justify-between items-center mb-1">
                <Label for="importo">Importo</Label>
                <div v-if="isImportoLocked" class="flex items-center text-xs text-amber-600 bg-amber-50 px-2 py-1 rounded border border-amber-200">
                  <Lock class="w-3 h-3 mr-1" />
                  Bloccato da rate emesse
                </div>
              </div>
              
              <div class="relative">
                <MoneyInput
                  id="importo"
                  v-model="form.importo"
                  :money-options="moneyOptions"
                  :lazy="true" 
                  placeholder="0,00"
                  @focus="form.clearErrors('importo')"
                  :disabled="isImportoLocked" 
                  :class="{'opacity-60 bg-gray-100 cursor-not-allowed': isImportoLocked}"
                />
                
                <div v-if="isImportoLocked" class="text-[11px] text-gray-500 mt-1">
                  Per modificare l'importo devi creare un conguaglio o annullare le rate.
                </div>
              </div>
              
              <InputError :message="form.errors.importo" />
            </div>

            <div>
              <Label for="note">Note</Label>
              <Textarea id="note" v-model="form.note" placeholder="Note opzionali..." />
            </div>

            <DialogFooter class="flex justify-end space-x-2 mt-6">
              <Button type="button" variant="outline" @click="closeModal">Annulla</Button>
              <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Salvataggio...' : 'Salva modifiche' }}
              </Button>
            </DialogFooter>
          </form>

        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<style src="vue-select/dist/vue-select.css"></style>