<script setup lang="ts">
import { computed, ref } from 'vue';
import GestionaleLayout from '@/layouts/GestionaleLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { usePermission } from "@/composables/permissions";
import CondominioDropdown from "@/components/CondominioDropdown.vue";
import { AlertTriangle, CheckCircle2, ArrowRight, X, Wallet } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import type { Building } from '@/types/buildings';

const props = defineProps<{
  condominio: Building;
  condomini: Building[];
  esercizio: any;
  copertura: {
    preventivo: string;
    pianificato: string;
    scoperto: string;
    percentuale: number;
    is_completo: boolean;
    orfani: Array<{ id: number; nome: string; importo: string; gestione: string }>;
    scoperto_count: number;
  } | null;
}>()

const { generatePath } = usePermission();
const showOrphansModal = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Gestionale', href: generatePath('gestionale/:condominio', { condominio: props.condominio.id }) },
    { title: props.condominio.nome, component: "condominio-dropdown" } as any,
]);

// Helper per chiudere il modal con ESC
const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') showOrphansModal.value = false;
};
</script>

<template>
    <Head title="Dashboard gestionale" />

    <GestionaleLayout :breadcrumbs="breadcrumbs">
        <template #breadcrumb-condominio>
            <CondominioDropdown :condominio="props.condominio" :condomini="props.condomini" />
        </template>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                
                <div v-if="copertura" class="relative flex flex-col justify-between overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:bg-slate-900 p-5 shadow-sm transition-all hover:shadow-md">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Audit Copertura Preventivo</h3>
                            <div class="flex items-center gap-1.5">
                                <span v-if="copertura.is_completo" class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span v-else class="flex h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                                <span class="text-[10px] font-bold uppercase" :class="copertura.is_completo ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ copertura.is_completo ? 'Allineato' : 'Incompleto' }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-900 dark:text-white">{{ copertura.pianificato }}</span>
                            <span class="text-xs font-medium text-slate-400">su {{ copertura.preventivo }}</span>
                        </div>

                        <div class="mt-4 h-2.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div 
                                class="h-full transition-all duration-1000 ease-in-out shadow-[0_0_10px_rgba(0,0,0,0.1)]"
                                :class="copertura.is_completo ? 'bg-emerald-500' : 'bg-gradient-to-r from-amber-400 to-amber-600'"
                                :style="{ width: copertura.percentuale + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 border-t pt-4">
                        <div class="flex items-center justify-between">
                            <div v-if="!copertura.is_completo" 
                                 @click="showOrphansModal = true"
                                 class="group cursor-pointer">
                                <p class="text-[10px] font-bold text-amber-600 flex items-center gap-1 group-hover:underline">
                                    <AlertTriangle class="w-3 h-3" />
                                    Mancano {{ copertura.scoperto }}
                                </p>
                                <p class="text-[9px] text-slate-400 uppercase tracking-tighter">{{ copertura.scoperto_count }} voci non associate</p>
                            </div>
                            <div v-else>
                                <p class="text-[10px] font-bold text-emerald-600 flex items-center gap-1">
                                    <CheckCircle2 class="w-3 h-3" />
                                    Bilancio 100% coperto
                                </p>
                            </div>

                            <Link 
                                :href="generatePath('gestionale/:condominio/esercizi/:esercizio/piani-rate', { condominio: condominio.id, esercizio: esercizio.id })"
                                class="inline-flex items-center gap-1 rounded-lg bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600 dark:text-slate-300 transition-colors hover:bg-primary hover:text-white"
                            >
                                Gestisci <ArrowRight class="w-3 h-3" />
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="relative aspect-video flex items-center justify-center rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                    <span class="text-[10px] font-bold uppercase text-slate-400">Modulo Fiscale in arrivo</span>
                </div>
                <div class="relative aspect-video flex items-center justify-center rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                    <span class="text-[10px] font-bold uppercase text-slate-400">Modulo Fornitori in arrivo</span>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showOrphansModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-sm p-4" @click.self="showOrphansModal = false">
                <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white dark:bg-slate-900 shadow-2xl animate-in zoom-in-95 duration-200">
                    <div class="flex items-center justify-between border-b p-6">
                        <div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">Audit Spese Scoperte</h3>
                            <p class="text-xs text-slate-500 uppercase font-bold tracking-tight">Elenco voci non incluse nei piani rate</p>
                        </div>
                        <button @click="showOrphansModal = false" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                            <div v-for="orfano in copertura?.orfani" :key="orfano.id" 
                                 class="group flex justify-between items-center p-4 border rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:border-amber-200 dark:hover:border-amber-900/50 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="bg-amber-100 dark:bg-amber-900/30 p-2 rounded-lg text-amber-600">
                                        <Wallet class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-slate-800 dark:text-slate-200">{{ orfano.nome }}</p>
                                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-tighter">{{ orfano.gestione }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-mono font-black text-slate-900 dark:text-white">{{ orfano.importo }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <Button variant="outline" class="flex-1 font-bold text-xs uppercase" @click="showOrphansModal = false">
                                Ho capito
                            </Button>
                            <Link :href="generatePath('gestionale/:condominio/esercizi/:esercizio/piani-rate', { condominio: condominio.id, esercizio: esercizio.id })" 
                                  class="flex-1">
                                <Button class="w-full font-bold text-xs uppercase bg-amber-600 hover:bg-amber-700">
                                    Vai ai piani rate
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </GestionaleLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>