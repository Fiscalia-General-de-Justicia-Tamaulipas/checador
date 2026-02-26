<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { debounce } from '@/utils/debounce';
import axios from 'axios';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import CardTitle from '@/Components/CardTitle.vue';
import CardText from '@/Components/CardText.vue';
import InputSelect from "@/Components/InputSelect.vue";
import InputError from '@/Components/InputError.vue';
import InputDate from '@/Components/InputDate.vue';
import SuccessButton from '@/Components/SuccessButton.vue';
import WarningButton from '@/Components/WarningButton.vue';
import AnimateSpin from '@/Components/Icons/AnimateSpin.vue';
import DownloadIcon from '@/Components/Icons/DownloadIcon.vue';
import ChevronRightIcon from '@/Components/Icons/ChevronRightIcon.vue';
import CheckSquareIcon from '@/Components/Icons/CheckSquareIcon.vue';

const props = defineProps({
    years: {
        type: Array,
        default: [2024,2023,2022,2021,2020]
    },
    incidentStatuses: Array,
    generalDirections: Array,
    employees: Array,
    reportTypes: Object,
    periods: Object,
    options: {
        type: Object,
        default: {
            generalDirecctionId: 0,
            year: 0,
            period: 0,
            type: '',
            dateGeneration: ''
        }
    }
});

const toast = useToast();

const form = useForm({
    general_direction_id: props.options.generalDirecctionId,
    year: props.options.year,
    period: props.options.period,
    report_type: props.options.type,
});

const formJob = useForm({
    date: props.options.dateGeneration
});

const loading = ref(false);
const makingReport = ref(false);

watch(form, (oldValue, newValue)=>{
    debounce(()=>{
        getIncidents();
    }, 750)
});

onMounted(()=>{
    //
});

function getIncidents(){

    // prevet call the data if the general direction and the period are not selected
    if( form.general_direction_id == null || form.general_direction_id <= 0 || !form.period){
        return;
    }

    loading.value = true;

    // prepared the query params
    var params = [];
    params.push(`gdi=${form.general_direction_id}`);
    params.push(`t=${form.report_type}`);
    params.push(`y=${form.year}`);
    params.push(`p=${form.period}`);

    // call for the data
    router.visit("?" + params.join("&"), {
        replace: true,
        preserveState: true,
        only: ["options","periods","employees"],
        onError: ()=>{
            toast.error("Error al actualizar los datos, intente de nuevo o comuniquese con el administrador.")
        },
        onFinish: ()=>{
            loading.value = false;
        }
    });

}

function reportTypeChanged(){
    form.period = undefined;

    var params = [];
    params.push(`gdi=${form.general_direction_id}`);
    params.push(`t=${form.report_type}`);
    params.push(`y=${form.year}`);

    router.visit("?" + params.join("&"), {
        replace: true,
        preserveState: true,
        only: ["options","periods","employees" ],
        onError: ()=>{
            toast.error("Error al actualizar los datos, intente de nuevo o comuniquese con el administrador.")
        },
        onFinish: ()=>{
            loading.value = false;
        }
    });
}

function makeReportClick(){

    if(makingReport.value){
        return;
    }

    makingReport.value = true;

    // * prepared payload
    const dataPayload = {
        'general_direction_id': form.general_direction_id,
        'year' : form.year,
        'period' : form.period,
        'report_type' : form.report_type
    };

    // * make the report
    const url = route('incidents.report.make', dataPayload);
    const link = document.createElement('a');
    link.href = url;
    document.body.appendChild(link);
    link.click();

    // clean up
    window.URL.revokeObjectURL(url);

    setTimeout(()=>{
        makingReport.value = false;
    },1000)

}

function visitIncidenceEmployee(employee){

    var _year = form.year;
    var _month = undefined;

     if (typeof form.period === 'string') {
        _month = form.period.split('-')[0];
    } else if (typeof form.period === 'number') {
        _month = form.period;
    }

    if(_month === undefined){
        toast.warning('Periodo seleccionado no valido.');
        return;
    }

    router.visit( route('incidents.employee.index', {
        "employee_number": employee.employeeNumber,
        "year": _year,
        "month": _month
    }));
}

function handleMakeIncidentsJob(){
    formJob.post( route('incidents.job.make'), {
        onSuccess:((i)=>{
            toast.success("Tarea generada con exito.");
        }),
        onError:((err)=>{
            var keys = Object.keys(err);
            keys.forEach(k => {
                toast.warning(err[k]);
            });
        })
    });
}

</script>

<template>

    <Head title="Empleado - Incidencias" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Consulta de Incidencias</h2>
        </template>

        <div class="px-4 py-4 h-container rounded-lg max-w-screen-xl mx-auto grid" :class="[ $page.props.auth.user.level_id == 1 ?'grid-rows-incidentsIndexAdmin' :'grid-rows-incidentsIndex' ]">

            <Card v-if="$page.props.auth.user.level_id == 1" class="outline outline-1 outline-gray-300 bg-orange-200 dark:outline-gray-500" :shadow="false">
                <template #content>
                    <div class="grid grid-cols-[1fr_14rem_7rem] gap-1">

                        <CardTitle class="my-auto">
                            Generar incidencias para todo el personal
                        </CardTitle>

                        <div class="flex items-center">
                            <label for="date" class="px-2">Fecha: </label>
                            <InputDate v-model="formJob.date" id="date" />
                        </div>

                        <WarningButton type="Button" class="ml-auto" v-on:click="handleMakeIncidentsJob">
                            <CheckSquareIcon class="w-5 h-5 mr-1" />
                            <span>Generar</span>
                        </WarningButton>

                    </div>
                </template>
            </Card>

            <Card class="outline outline-1 outline-gray-300 dark:outline-gray-500" :shadow="false">
                <template #content>
                    <form @submit.prevent="makeReportClick" class="flex pt-1 gap-1 items-center">

                        <InputSelect v-model="form.general_direction_id" id="general_direction_id" class="max-w-[24rem]" :disabled="$page.props.auth.user.level_id > 1">
                            <option value="" class="uppercase"> Seleccione una opcion</option>
                            <option v-for="element in generalDirections" :value="element.id" class="uppercase"> {{ element.name }}</option>
                        </InputSelect>

                        <InputSelect v-model="form.year" id="year" class="max-w-[8rem]">
                            <option v-for="y in years" :value="y"> {{ y }}</option>
                        </InputSelect>

                        <InputSelect v-model="form.report_type" id="report_type" class="max-w-[12rem]" v-on:change="reportTypeChanged">
                            <option v-for="(key, index) in Object.keys(reportTypes)" :key="index" :value="key">{{ reportTypes[key] }}</option>
                        </InputSelect>

                        <InputSelect v-model="form.period" id="period" class="max-w-[18rem]">
                            <option value=""> Seleccione una opcion</option>
                            <option v-for="(key, index) in Object.keys(periods)" :key="index" :value="key"> {{ periods[key] }}</option>
                        </InputSelect>

                        <SuccessButton type="submit" class="ml-auto">
                            <AnimateSpin v-if="makingReport || loading" class="w-5 h-5 mr-1 text-white" />
                            <DownloadIcon v-else class="w-5 h-5 mr-1" />
                            <span>Descargar</span>
                        </SuccessButton>

                    </form>
                </template>
            </Card>

            <div class="h-full overflow-y-auto pb-4">
                <table class="table w-full shadow text-sm text-left border rtl:text-right text-gray-500 dark:text-gray-400 dark:border-gray-500">
                    <thead class="sticky top-0 z-20 text-xs uppercase text-gray-700 border bg-gradient-to-b from-gray-50 to-slate-100 dark:from-gray-800 dark:to-gray-700 dark:text-gray-200 dark:border-gray-500">
                        <AnimateSpin v-if="loading" class="w-5 h-5 mx-2 absolute top-2.5" />
                        <tr>
                            <th scope="col" class="w-1/8 text-center px-6 py-3 ">#</th>

                            <th scope="col" class="w-2/8 text-center px-6 py-3 tracking-wider">
                                Nombre
                            </th>
                            <th scope="col" class="w-2/8 text-center px-6 py-3 uppercase tracking-wider">
                                Unidad
                            </th>
                            <th scope="col" class="w-1/8 text-center px-6 py-3 uppercase tracking-wider">
                                # Incidencias
                            </th>
                            <th scope="col" class="w-2/8 before:relative px-6 py-3">
                                <span class="sr-only">Información</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="employees && employees.length>0" v-for="(employee, index) in employees" :key="index">
                            <td class="text-sm text-center font-medium text-gray-900 px-2">
                                {{ index + 1 }}
                            </td>

                            <td class="px-6 py-4 flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" :src="employee.photo" alt="User photo">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ employee.name }}
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <div class="text-sm text-gray-600">
                                    {{ employee.abbreviation }}
                                </div>
                                <div class="text-sm text-gray-900">
                                    {{ employee.direction?.name || '-' }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center whitespace-normal text-sm text-gray-500">
                                {{ employee.totalIncidents }}
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div v-on:click="visitIncidenceEmployee(employee)" class="inline-flex items-center pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:outline-none focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700 transition duration-150 ease-in-out gap-2 shadow bg-slate-200 px-4 py-1 cursor-pointer">
                                    <span>Incidencias</span>
                                    <ChevronRightIcon class="w-4 h-4 ml-1" />
                                </div>
                            </td>
                        </tr>

                        <tr v-else>
                            <td colspan="5" class="text-center text-yellow-600 py-6">Sin información que mostrar</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </AuthenticatedLayout>
</template>
<style>
.h-container {
    height: calc(100vh - 7rem);
}
</style>