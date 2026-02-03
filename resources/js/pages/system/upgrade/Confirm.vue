<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Rocket, AlertTriangle, Loader2, CheckCircle2, ArrowRight } from 'lucide-vue-next';

const props = defineProps({
    currentVersion: String,
    newVersion: String,
    needsUpgrade: Boolean, // Ricevuto dal controller
    errors: Object
});

const form = useForm({});

const startUpgrade = () => {
    form.post(route('system.upgrade.run'));
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50/50 p-4">
        <Card class="w-full max-w-md shadow-lg border-t-4" 
              :class="needsUpgrade ? 'border-t-blue-600' : 'border-t-green-500'">
            
            <CardHeader class="text-center pb-2">
                <div v-if="needsUpgrade" class="mx-auto bg-blue-100 p-3 rounded-full w-fit mb-4">
                    <Rocket class="w-8 h-8 text-blue-600" />
                </div>
                <div v-else class="mx-auto bg-green-100 p-3 rounded-full w-fit mb-4">
                    <CheckCircle2 class="w-8 h-8 text-green-600" />
                </div>

                <CardTitle class="text-2xl">
                    {{ needsUpgrade ? 'Aggiornamento disponibile' : 'Sistema aggiornato' }}
                </CardTitle>
                
                <CardDescription>
                    <div class="flex items-center justify-center gap-2 mt-2">
                        <span class="text-xs font-medium text-muted-foreground uppercase">Attuale:</span>
                        <Badge variant="secondary">{{ currentVersion }}</Badge>
                        <ArrowRight v-if="needsUpgrade" class="w-3 h-3 text-muted-foreground" />
                        <Badge v-if="needsUpgrade" variant="default" class="bg-blue-600">{{ newVersion }}</Badge>
                    </div>
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4 pt-4">
                <template v-if="needsUpgrade">
                    <Alert variant="destructive" v-if="errors.msg">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>Errore</AlertTitle>
                        <AlertDescription>{{ errors.msg }}</AlertDescription>
                    </Alert>

                    <Alert class="bg-amber-50 border-amber-200 text-amber-800">
                        <AlertTriangle class="h-4 w-4 text-amber-600" />
                        <AlertTitle class="text-amber-800 font-semibold text-sm">Pronto per l'aggiornamento?</AlertTitle>
                        <AlertDescription class="text-amber-700 text-xs mt-1">
                            L'operazione eseguirà le migrazioni del database e rigenererà la cache di sistema.
                        </AlertDescription>
                    </Alert>
                </template>

                <div v-else class="text-center py-4 text-sm text-gray-600">
                    <p>Non ci sono aggiornamenti del database pendenti.</p>
                    <p class="mt-1 font-medium text-green-600 italic">Kondomanager è in esecuzione correttamente.</p>
                </div>
            </CardContent>

            <CardFooter>
                <Button 
                    v-if="needsUpgrade"
                    class="w-full" 
                    size="lg" 
                    :disabled="form.processing"
                    @click="startUpgrade"
                >
                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Aggiornamento in corso...' : 'Avvia aggiornamento database' }}
                </Button>

                <Button v-else as-child variant="outline" class="w-full" size="lg">
                    <Link :href="route('admin.dashboard')">
                        Torna alla dashboard
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>