<script setup>
import { onMounted} from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageTitle from '@/Components/PageTitle.vue';
import Card from '@/Components/Card.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputSelect from "@/Components/InputSelect.vue";
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

const props = defineProps({
    breadcrumbs: Object,
    generalDirections: Array,
    generalDirectionId: Number,
    years: {
        type: Array,
        default: [
            2024,
            2023,
            2022,
            2021,
            2020
        ]
    },
    months: {
        type: Array,
        default: [
            {value: 1, label: "ENE"},
            {value: 2, label: "FEB"},
            {value: 3, label: "MAR"},
            {value: 4, label: "ABR"},
            {value: 5, label: "MAY"},
            {value: 6, label: "JUN"},
            {value: 7, label: "JUL"},
            {value: 8, label: "AGO"},
            {value: 9, label: "SEP"},
            {value: 10, label: "OCT"},
            {value: 11, label: "NOV"},
            {value: 12, label: "DIC"},
        ]
    }
});

const toast = useToast();

const formDaily = useForm({
    generalDirectionId: props.generalDirectionId ?? 0,
    date: undefined,
    allEmployees: false
});

const formMonthly = useForm({
    generalDirectionId: props.generalDirectionId ?? 0,
    year: undefined,
    month: undefined,
    allEmployees: false
});

onMounted(()=>{
    // set the current month
    const date = new Date();
    const currentMonth = date.getMonth() + 1;

    formMonthly.month = currentMonth;
    formMonthly.year = date.getFullYear();
    formDaily.date = date.toISOString().split("T")[0];
});

function handleReportDailyClick(){
    router.visit( route('reports.daily.create', {
        "gd": formDaily.generalDirectionId,
        "d": formDaily.date,
        "a": formDaily.allEmployees
    }), {
        onError:((err)=>{
            const {message} = err;
            if(message){
                toast.warning(message);
            } else {
                toast.error("Ocurrió un error al generar el reporte. Por favor, intente de nuevo en unos minutos.");
            }
        })
    });
}

function handleReportMonthlyClick(){
    router.visit( route('reports.monthly.create', {
        "gd": formMonthly.generalDirectionId,
        "y": formMonthly.year,
        "m": formMonthly.month,
        "a": formMonthly.allEmployees
    }), {
        onError:((err)=>{
            const {message} = err;
            if(message){
                toast.warning(message);
            } else {
                toast.error("Ocurrió un error al generar el reporte. Por favor, intente de nuevo en unos minutos.");
            }
        })
    });
}
</script>

<template>
    <Head title="Generar Reportes" />

    <AuthenticatedLayout>

        <template #header>
            <Breadcrumb :breadcrumbs="breadcrumbs" />
        </template>

        <div class="flex flex-col gap-6 pt-12 pb-4 py-4 rounded-lg max-w-screen-xl mx-auto select-none">

            <!-- employee data -->
            <Card class="outline outline-1 outline-gray-300 dark:outline-gray-500" :shadow="false">
                <template #header>
                    <PageTitle>Reporte Diario</PageTitle>
                </template>
                <template #content>
                    <form class="flex gap-4 items-center pb-2" @submit.prevent="handleReportDailyClick">

                        <div role="form-group">
                            <InputLabel for="dailyGeneralDirectionId" value="Area" />
                            <InputSelect id="dailyGeneralDirectionId" v-model="formDaily.generalDirectionId" class="min-w-64 max-w-[24rem]" required :disabled="$page.props.auth.user.level_id > 2">
                                <option value=""> Selecione una opcion</option>
                                <option v-for="item in generalDirections" :key="item.id" :value="item.id"> {{ item.name }}</option>
                            </InputSelect>
                            <InputError :message="formDaily.errors.generalDirectionId" />
                        </div>

                        <div role="form-group">
                            <InputLabel for="date" value="Dia"/>
                            <InputDate id="date" v-model="formDaily.date" class="px-4" required/>
                            <InputError :message="formDaily.errors.date" />
                        </div>

                        <div role="form-group">
                            <InputLabel for="dailyAllEmployees" value="Incluir todas las áreas" />
                            <input type="checkbox" class="ml-4 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" id="dailyAllEmployees" style="width: 1.5rem; height: 1.5rem;" v-model="formDaily.allEmployees" />
                            <InputError :message="formDaily.errors.allEmployees" />
                        </div>

                        <PrimaryButton type="submit" class="ml-auto">
                            <span> Generar Reporte </span>
                        </PrimaryButton>
                        
                    </form>
                </template>
            </Card>

            <!-- employee data -->
            <Card class="outline outline-1 outline-gray-300 dark:outline-gray-500" :shadow="false">
                <template #header>
                    <PageTitle>Reporte Mensual</PageTitle>
                </template>
                <template #content>
                    <form class="flex gap-4 items-center pb-2" @submit.prevent="handleReportMonthlyClick">

                        <div role="form-group">
                            <InputLabel for="monthlyGeneralDirectionId" value="Area" />
                            <InputSelect id="monthlyGeneralDirectionId" v-model="formMonthly.generalDirectionId" class="min-w-64 max-w-[24rem]" :disabled="$page.props.auth.user.level_id > 2" >
                                <option value="">Seleccione una opción</option>
                                <option v-for="item in generalDirections" :key="item.id" :value="item.id"> {{ item.name }}</option>
                            </InputSelect>
                        </div>

                        <div role="form-group">
                            <InputLabel for="year" value="Año"/>
                            <InputSelect id="year" v-model="formMonthly.year" class="w-[12rem]" >
                                <option value="" disabled>Seleccione una opción</option>
                                <option v-for="y in years" :key="y" :value="y"> {{ y }}</option>
                            </InputSelect>
                        </div>

                        <div role="form-group">
                            <InputLabel for="month" value="Mes"/>
                            <InputSelect id="month" v-model="formMonthly.month" class="w-[12rem]">
                                <option value="" disabled>Seleccione una opción</option>
                                <option v-for="m in months" :key="m.value" :value="m.value"> {{ m.label }}</option>
                            </InputSelect>
                        </div>
                        

                        <div role="form-group">
                            <InputLabel for="monthlyAllEmployees" value="Incluir todas las áreas" />
                            <input type="checkbox" class="ml-4 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" id="monthlyAllEmployees" style="width: 1.5rem; height: 1.5rem;" v-model="formMonthly.allEmployees" />
                        </div>

                        <PrimaryButton type="submit" class="ml-auto">
                            <span> Generar Reporte </span>
                        </PrimaryButton>
                        
                    </form>
                </template>
            </Card>

        </div>

    </AuthenticatedLayout>
</template>
